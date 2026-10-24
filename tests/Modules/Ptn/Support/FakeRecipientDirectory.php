<?php

namespace Tests\Modules\Ptn\Support;

use App\Models\User;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Rbc\Contracts\AccessControl;

/** Role holders set explicitly; permission targets ask the real AccessControl. */
class FakeRecipientDirectory implements RecipientDirectory
{
    /** @var array<string, list<int>> */
    public array $roles = [];

    public function resolve(string $target, string $value): array
    {
        if ($target === self::PERANAN) {
            return User::query()->whereIn('id', $this->roles[$value] ?? [])->get()->all();
        }
        if ($target === self::KEBENARAN) {
            $access = app(AccessControl::class);

            return User::query()->where('is_active', true)->orderBy('id')->get()->filter(fn (User $u) => $access->allows($u, $value))->values()->all();
        }

        return [];
    }

    public function options(string $target): array
    {
        return [];
    }
}
