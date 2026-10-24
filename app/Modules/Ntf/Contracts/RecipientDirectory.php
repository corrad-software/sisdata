<?php

namespace App\Modules\Ntf\Contracts;

use App\Models\User;

/**
 * Resolves groups of recipients (SRS-NTF-03 recipient rules, SRS-NTF-24
 * announcements). MOD-NTF ships a default that walks active users and asks
 * Rbc\Contracts\AccessControl for each user's roles / scopes / permissions —
 * correct but O(users). MOD-RBC can bind a query-based implementation later.
 */
interface RecipientDirectory
{
    public const PERANAN = 'peranan';

    public const KEBENARAN = 'kebenaran';

    public const BAHAGIAN = 'bahagian';

    public const DAERAH = 'daerah';

    public const PENGGUNA = 'pengguna';

    /**
     * Active users matching one target, e.g. ('peranan', 'pegawai_hartanah'),
     * ('daerah', '10'), ('kebenaran', 'ntf.saluran.kemas_kini'), ('pengguna', '42').
     *
     * @return list<User>
     */
    public function resolve(string $target, string $value): array;

    /**
     * Selectable values for one target type (for the announcement composer).
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(string $target): array;
}
