<?php

namespace App\Modules\Rbc\Contracts;

use App\Models\User;

/**
 * The single authorisation service (SRS-RBC-08, SRS-GEN-01). Every module asks
 * this — never its own role checks. Implemented by MOD-RBC; until a module's
 * permissions exist, `allows()` simply returns false (least privilege, RBC-09).
 *
 * Permission keys are "<modul>.<fungsi>.<tindakan>", lower-case, e.g.
 * "dms.dokumen.lihat", "rbc.peranan.kemas_kini". Actions (SRS-RBC-06):
 * lihat, cipta, kemas_kini, padam, lulus, eksport.
 *
 * Laravel's Gate is wired to this too, so `$user->can('dms.dokumen.lihat')`
 * and the `can:dms.dokumen.lihat` route middleware both work.
 */
interface AccessControl
{
    public const ACTIONS = ['lihat', 'cipta', 'kemas_kini', 'padam', 'lulus', 'eksport'];

    /** Scope types for record-level access (SRS-RBC-04/07). */
    public const SCOPE_DAERAH = 'daerah';

    public const SCOPE_MUKIM = 'mukim';

    public const SCOPE_BAHAGIAN = 'bahagian';

    public const SCOPE_KLASIFIKASI = 'klasifikasi';

    public function allows(User $user, string $permission): bool;

    /**
     * Allowed values of one scope type for this user, or null when the user is
     * unrestricted on that dimension. An empty array means "nothing".
     *
     * @return list<string>|null
     */
    public function scope(User $user, string $type): ?array;

    /**
     * Role codes the user holds right now (recorded in audit, SRS-AUD-04).
     *
     * @return list<string>
     */
    public function roles(User $user): array;

    /**
     * Every permission key the user holds — sent to the frontend so it can hide
     * what the user can't use. Never used for enforcement.
     *
     * @return list<string>
     */
    public function permissions(User $user): array;
}
