<?php

namespace App\Modules\Wfe\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use Illuminate\Support\Facades\Schema;

/**
 * Stand-in until MOD-RBC binds a real ApproverDirectory:
 *  - role holders: the legacy users.role column;
 *  - position: users.jawatan when that column exists;
 *  - supervisor: users.penyelia_id when that column exists, otherwise the
 *    holders of the role in parameter wfe.peranan_penyelia_lalai.
 */
class DefaultApproverDirectory implements ApproverDirectory
{
    /** @var array<string, bool> */
    private array $columns = [];

    public function __construct(private Parameters $parameters) {}

    public function usersWithRole(string $roleCode): array
    {
        return User::query()->where('role', $roleCode)->where('is_active', true)->orderBy('id')->get()->all();
    }

    public function usersWithPosition(string $positionCode): array
    {
        if (! $this->hasColumn('jawatan')) {
            return [];
        }

        return User::query()->where('jawatan', $positionCode)->where('is_active', true)->orderBy('id')->get()->all();
    }

    public function positionOf(User $user): ?string
    {
        return $this->hasColumn('jawatan') ? ($user->getAttribute('jawatan') ?: null) : null;
    }

    public function supervisorsOf(User $user): array
    {
        if ($this->hasColumn('penyelia_id') && $user->getAttribute('penyelia_id')) {
            $supervisor = User::query()->whereKey($user->getAttribute('penyelia_id'))->where('is_active', true)->first();
            if ($supervisor) {
                return [$supervisor];
            }
        }

        $role = (string) $this->parameters->get('wfe.peranan_penyelia_lalai', 'admin');

        return array_values(array_filter($this->usersWithRole($role), fn (User $u) => $u->id !== $user->id));
    }

    private function hasColumn(string $column): bool
    {
        return $this->columns[$column] ??= Schema::hasColumn('users', $column);
    }
}
