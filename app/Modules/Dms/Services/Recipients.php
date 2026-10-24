<?php

namespace App\Modules\Dms\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Collection;

/**
 * Who receives DMS operational alerts (quarantine, quota): active users that
 * MOD-RBC says hold a given permission. NTF admins can still re-route a type.
 */
class Recipients
{
    public function __construct(private AccessControl $access) {}

    /** @return Collection<int, User> */
    public function withPermission(string $permission): Collection
    {
        $found = collect();
        User::query()->where('is_active', true)->orderBy('id')->chunk(500, function ($users) use ($permission, $found) {
            foreach ($users as $user) {
                if ($this->access->allows($user, $permission)) {
                    $found->push($user);
                }
            }
        });

        return $found;
    }
}
