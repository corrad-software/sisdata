<?php

namespace App\Modules\Dms\Services\Access;

use App\Models\User;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Record-level document access (SRS-DMS-13/19), always decided through
 * MOD-RBC's AccessControl — never role strings.
 *
 * A user sees a document when they hold dms.dokumen.lihat AND one of:
 *  - they uploaded it;
 *  - an explicit, unexpired grant on that document names them or one of their roles;
 *  - it is inside their daerah / klasifikasi scope and
 *      Awam/Dalaman → nothing more needed,
 *      Sulit  → dms.sulit.lihat  (explicit per-role/per-user permission in RBC),
 *      Rahsia → dms.rahsia.lihat.
 *
 * The same rule exists twice — as SQL (search results AND counts, 13) and
 * in PHP (single-document checks) — and tests pin both to the same cases.
 */
class DocumentAccess
{
    public const VIEW = 'dms.dokumen.lihat';

    public const SULIT = 'dms.sulit.lihat';

    public const RAHSIA = 'dms.rahsia.lihat';

    public function __construct(private AccessControl $access) {}

    public function allows(User $user, string $permission): bool
    {
        return $this->access->allows($user, $permission);
    }

    public function canView(User $user, Dokumen $doc): bool
    {
        if (! $this->access->allows($user, self::VIEW)) {
            return false;
        }
        if ($doc->id_pencipta !== null && $doc->id_pencipta === $user->id) {
            return true;
        }
        if ($this->hasGrant($user, $doc)) {
            return true;
        }

        $daerah = $this->access->scope($user, AccessControl::SCOPE_DAERAH);
        if ($daerah !== null && $doc->kod_daerah !== null && ! in_array($doc->kod_daerah, $daerah, true)) {
            return false;
        }
        $klasifikasi = $this->access->scope($user, AccessControl::SCOPE_KLASIFIKASI);
        if ($klasifikasi !== null && ! in_array($doc->klasifikasi_keselamatan, $klasifikasi, true)) {
            return false;
        }

        return match ($doc->klasifikasi_keselamatan) {
            'sulit' => $this->access->allows($user, self::SULIT),
            'rahsia' => $this->access->allows($user, self::RAHSIA),
            default => true,
        };
    }

    /**
     * Restrict a Dokumen query to what $user may see.
     *
     * @param  Builder<Dokumen>  $query
     * @return Builder<Dokumen>
     */
    public function scopeVisible(Builder $query, User $user): Builder
    {
        if (! $this->access->allows($user, self::VIEW)) {
            return $query->whereRaw('1 = 0');
        }

        $daerah = $this->access->scope($user, AccessControl::SCOPE_DAERAH);
        $klasifikasi = $this->access->scope($user, AccessControl::SCOPE_KLASIFIKASI);
        $levels = ['awam', 'dalaman'];
        if ($this->access->allows($user, self::SULIT)) {
            $levels[] = 'sulit';
        }
        if ($this->access->allows($user, self::RAHSIA)) {
            $levels[] = 'rahsia';
        }
        if ($klasifikasi !== null) {
            $levels = array_values(array_intersect($levels, $klasifikasi));
        }
        $roles = $this->access->roles($user);

        return $query->where(function (Builder $q) use ($user, $daerah, $levels, $roles) {
            $q->where('dokumen.id_pencipta', $user->id)
                ->orWhereExists(fn (QueryBuilder $sub) => $this->grantSubquery($sub, $user, $roles))
                ->orWhere(function (Builder $scoped) use ($daerah, $levels) {
                    $levels === [] ? $scoped->whereRaw('1 = 0') : $scoped->whereIn('dokumen.klasifikasi_keselamatan', $levels);
                    if ($daerah !== null) {
                        $scoped->where(fn (Builder $d) => $d->whereNull('dokumen.kod_daerah')->orWhereIn('dokumen.kod_daerah', $daerah));
                    }
                });
        });
    }

    public function hasGrant(User $user, Dokumen $doc): bool
    {
        return $doc->kebenaran()
            ->where(fn ($q) => $q->whereNull('tamat_pada')->orWhere('tamat_pada', '>', now()))
            ->where(function ($q) use ($user) {
                $q->where(fn ($p) => $p->where('jenis_penerima', 'pengguna')->where('penerima', (string) $user->id));
                $roles = $this->access->roles($user);
                if ($roles !== []) {
                    $q->orWhere(fn ($p) => $p->where('jenis_penerima', 'peranan')->whereIn('penerima', $roles));
                }
            })
            ->exists();
    }

    /** @param list<string> $roles */
    private function grantSubquery(QueryBuilder $sub, User $user, array $roles): void
    {
        $sub->selectRaw('1')
            ->from('dokumen_kebenaran as k')
            ->whereColumn('k.dokumen_id', 'dokumen.id')
            ->where(fn ($q) => $q->whereNull('k.tamat_pada')->orWhere('k.tamat_pada', '>', now()))
            ->where(function ($q) use ($user, $roles) {
                $q->where(fn ($p) => $p->where('k.jenis_penerima', 'pengguna')->where('k.penerima', (string) $user->id));
                if ($roles !== []) {
                    $q->orWhere(fn ($p) => $p->where('k.jenis_penerima', 'peranan')->whereIn('k.penerima', $roles));
                }
            });
    }
}
