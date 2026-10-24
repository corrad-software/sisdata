<?php

namespace App\Modules\Kes\Support;

use App\Models\User;
use App\Modules\Kes\Contracts\MfaAssurance;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Legal-specific access control (SRS-KES-13/14, security controls) on top of
 * the RBC permission + scope checks:
 *  - every case record needs `kes.kes.lihat` AND the explicit Rahsia grant
 *    `kes.rahsia.lihat` (never given by a wildcard base-role rule) AND MFA;
 *  - record scope: daerah / mukim of the case's primary lot + klasifikasi
 *    (AccessControl::scope), so a district officer reads only cases on lots
 *    under their control;
 *  - a user linked to a panel law firm (kes_panel_peguam.id_pengguna) sees
 *    only cases where that firm holds an approved appointment.
 */
class Akses
{
    public const LIHAT = ['kes.kes.lihat', 'kes.rahsia.lihat'];

    /** @var array<int, list<int>> */
    private array $panel = [];

    public function __construct(private AccessControl $access, private MfaAssurance $mfa) {}

    /** Permission check only (no MFA) — for background/aggregate callers. */
    public function bolehLihat(User $user): bool
    {
        return $user->can('kes.kes.lihat') && $user->can('kes.rahsia.lihat');
    }

    /** Permissions + MFA for the current request (record and document access). */
    public function bolehCapai(User $user): bool
    {
        return $this->bolehLihat($user) && $this->mfa->satisfied($user, request());
    }

    /** Callers load $kes through hadkan(), so visibility is already established. */
    public function bolehUrus(User $user, KesMahkamah $kes): bool
    {
        return $user->can('kes.kes.kemas_kini') && ! $kes->status_arkib;
    }

    /**
     * Apply $user's case scope to a KesMahkamah query.
     *
     * @param  Builder<KesMahkamah>  $query
     * @return Builder<KesMahkamah>
     */
    public function hadkan(Builder $query, User $user): Builder
    {
        $query->withoutGlobalScope('access');
        $model = $query->getModel();
        foreach ($model->accessScopeColumns() as $type => $column) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed !== null) {
                $query->whereIn($model->qualifyColumn($column), $allowed);
            }
        }
        $panel = $this->panelUntuk($user);
        if ($panel !== []) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('kes_peguam as kp')
                ->whereColumn('kp.kes_mahkamah_id', 'kes_mahkamah.id')
                ->whereIn('kp.kes_panel_peguam_id', $panel)->where('kp.status_lantikan', 'aktif'));
        }

        return $query;
    }

    /** @return list<int> panel firm ids the user acts for (empty = not external counsel) */
    public function panelUntuk(User $user): array
    {
        return $this->panel[$user->id] ??= DB::table('kes_panel_peguam')->where('id_pengguna', $user->id)
            ->where('status', 'aktif')->pluck('id')->map(fn ($v) => (int) $v)->values()->all();
    }

    public function lupa(): void
    {
        $this->panel = [];
    }
}
