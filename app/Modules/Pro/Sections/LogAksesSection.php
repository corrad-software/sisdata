<?php

namespace App\Modules\Pro\Sections;

use App\Models\User;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Pro\Services\LotQuery;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\DB;

/** SRS-PRO-30: who viewed / exported / printed the profile — tab "Dokumen & Jejak", pro.jejak.lihat only. */
class LogAksesSection implements ProfileSection
{
    public function __construct(private LotQuery $lots, private AccessControl $access) {}

    public function key(): string
    {
        return 'log_akses';
    }

    public function tab(): string
    {
        return 'dokumen';
    }

    public function label(): string
    {
        return (string) __('pro::pro.seksyen.log_akses');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if (! $this->access->allows($user, 'pro.jejak.lihat') || $this->lots->find($user, $lotId) === null) {
            return null;
        }

        $rows = DB::table('log_akses_profil as l')->leftJoin('users as u', 'u.id', '=', 'l.id_pengguna')
            ->where('l.lot_id', $lotId)->orderByDesc('l.tarikh_masa')->limit(100)
            ->get(['l.tarikh_masa', 'l.jenis_capaian', 'l.saluran', 'l.alamat_ip', 'u.name']);

        return ['items' => $rows->map(fn ($r) => [
            'tarikh' => (string) $r->tarikh_masa,
            'label' => (string) __('pro::pro.jenis_capaian.'.$r->jenis_capaian),
            'nilai' => $r->name,
            'saluran' => $r->saluran,
            'alamat_ip' => $r->alamat_ip,
        ])->all()];
    }

    public function timeline(string $lotId, User $user): array
    {
        return [];
    }
}
