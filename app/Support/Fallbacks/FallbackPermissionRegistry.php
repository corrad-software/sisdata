<?php

namespace App\Support\Fallbacks;

use App\Modules\Rbc\Contracts\PermissionRegistry;

/** Stand-in for PermissionRegistry until its owning module binds the real one. See App\Support\Fallbacks. */
class FallbackPermissionRegistry implements PermissionRegistry
{
    /** @var array<string, array{label: string, permissions: array<string, string>}> */
    private array $modules = [];

    public function register(string $module, string $label, array $permissions): void
    {
        $this->modules[$module] = ['label' => $label, 'permissions' => $permissions];
    }

    public function all(): array
    {
        return $this->modules;
    }
}
