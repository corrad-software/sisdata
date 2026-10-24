<?php

namespace App\Modules\Rbc\Services;

use App\Modules\Rbc\Contracts\PermissionRegistry;
use InvalidArgumentException;

/**
 * In-memory permission catalogue, filled by every module's provider boot()
 * (see the PermissionRegistry contract). PermissionSync persists it to the
 * `kebenaran` table and applies base-role defaults to new keys.
 */
class PermissionCatalogue implements PermissionRegistry
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/';

    /** @var array<string, array{label: string, permissions: array<string, string>}> */
    private array $modules = [];

    public function register(string $module, string $label, array $permissions): void
    {
        foreach (array_keys($permissions) as $key) {
            if (! preg_match(self::KEY_PATTERN, $key) || ! str_starts_with($key, $module.'.')) {
                throw new InvalidArgumentException("Invalid permission key [{$key}] for module [{$module}]: expected \"{$module}.<fungsi>.<tindakan>\".");
            }
        }

        $existing = $this->modules[$module]['permissions'] ?? [];

        $this->modules[$module] = [
            'label' => $label,
            'permissions' => array_merge($existing, $permissions),
        ];
    }

    public function all(): array
    {
        ksort($this->modules);

        return $this->modules;
    }

    /** @return array<string, string> key => label, every registered permission */
    public function flat(): array
    {
        $flat = [];
        foreach ($this->modules as $module) {
            $flat += $module['permissions'];
        }
        ksort($flat);

        return $flat;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->flat());
    }

    public function moduleLabel(string $module): string
    {
        return $this->modules[$module]['label'] ?? $module;
    }
}
