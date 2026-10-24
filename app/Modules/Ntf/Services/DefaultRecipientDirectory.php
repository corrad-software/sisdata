<?php

namespace App\Modules\Ntf\Services;

use App\Models\User;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * Contract-only directory: walks active users and asks AccessControl. A user
 * unrestricted on a scope dimension (scope() === null) is NOT counted as a
 * member of every bahagian/daerah — announcements to "Daerah Klang" reach
 * officers explicitly scoped to Klang only.
 */
class DefaultRecipientDirectory implements RecipientDirectory
{
    public function __construct(private AccessControl $access) {}

    public function resolve(string $target, string $value): array
    {
        if ($target === self::PENGGUNA) {
            $user = ctype_digit($value) ? User::query()->where('is_active', true)->find((int) $value) : null;

            return $user ? [$user] : [];
        }

        $matches = [];
        foreach (User::query()->where('is_active', true)->orderBy('id')->lazy() as $user) {
            $hit = match ($target) {
                self::PERANAN => in_array($value, $this->access->roles($user), true),
                self::KEBENARAN => $this->access->allows($user, $value),
                self::BAHAGIAN => in_array($value, $this->access->scope($user, AccessControl::SCOPE_BAHAGIAN) ?? [], true),
                self::DAERAH => in_array($value, $this->access->scope($user, AccessControl::SCOPE_DAERAH) ?? [], true),
                default => false,
            };

            if ($hit) {
                $matches[] = $user;
            }
        }

        return $matches;
    }

    public function options(string $target): array
    {
        $values = [];
        foreach (User::query()->where('is_active', true)->lazy() as $user) {
            $found = match ($target) {
                self::PERANAN => $this->access->roles($user),
                self::BAHAGIAN => $this->access->scope($user, AccessControl::SCOPE_BAHAGIAN) ?? [],
                self::DAERAH => $this->access->scope($user, AccessControl::SCOPE_DAERAH) ?? [],
                default => [],
            };
            foreach ($found as $value) {
                $values[$value] = true;
            }
        }

        $values = array_keys($values);
        sort($values);

        return array_map(fn ($v) => ['value' => (string) $v, 'label' => (string) $v], $values);
    }
}
