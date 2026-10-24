<?php

namespace App\Modules\Cki\Support;

use App\Models\User;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * Officers to notify about a tax record: users holding a permission whose
 * district scope covers the record's district (Pegawai Daerah see only
 * their district; state-level officers see all). Resolved through MOD-NTF's
 * RecipientDirectory and MOD-RBC's AccessControl.
 */
class Penerima
{
    /** @var array<string, list<User>> */
    private array $cache = [];

    public function __construct(private RecipientDirectory $directory, private AccessControl $access) {}

    /** @return list<User> */
    public function untuk(string $permission, ?string $kodDaerah = null): array
    {
        $users = $this->cache[$permission] ??= $this->directory->resolve(RecipientDirectory::KEBENARAN, $permission);
        if ($kodDaerah === null) {
            return $users;
        }

        return array_values(array_filter($users, function (User $u) use ($kodDaerah) {
            $scope = $this->access->scope($u, AccessControl::SCOPE_DAERAH);

            return $scope === null || in_array($kodDaerah, $scope, true);
        }));
    }
}
