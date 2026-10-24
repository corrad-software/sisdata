<?php

namespace App\Modules\Adm\Services\Tugasan;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Contracts\Console\Kernel;

/**
 * Which artisan commands an admin may schedule from the UI. Arbitrary
 * commands (db:wipe, migrate:fresh, key:generate, down…) would turn the job
 * screen into a remote shell, so only SISDATA module commands
 * ("<modul>:…", "sisdata:…") plus a few safe framework housekeeping commands
 * are allowed.
 */
class ArahanDibenarkan
{
    public const FRAMEWORK_SAFE = [
        'model:prune', 'queue:prune-batches', 'queue:prune-failed', 'queue:retry', 'sanctum:prune-expired',
        'auth:clear-resets', 'horizon:snapshot', 'cache:prune-stale-tags',
    ];

    public const DENIED = ['adm:secret'];

    public function __construct(private Kernel $artisan) {}

    /** $command may be a bare name or a command line ("dms:arkib --hari=30"). */
    public function allowed(string $command): bool
    {
        $command = (string) strtok(trim($command), ' ');

        if (in_array($command, self::DENIED, true) || ! array_key_exists($command, $this->artisan->all())) {
            return false;
        }

        if (in_array($command, self::FRAMEWORK_SAFE, true)) {
            return true;
        }

        $prefix = strstr($command, ':', true);

        return $prefix !== false && in_array($prefix, $this->modulePrefixes(), true);
    }

    /** @return list<string> */
    public function list(): array
    {
        return array_values(array_filter(array_keys($this->artisan->all()), fn (string $c) => $this->allowed($c)));
    }

    /** @return list<string> */
    private function modulePrefixes(): array
    {
        $prefixes = ['sisdata'];
        foreach (config('modules.enabled', []) as $provider) {
            $instance = app()->getProvider($provider);
            if ($instance instanceof ModuleServiceProvider) {
                $prefixes[] = $instance->code();
            }
        }

        return $prefixes;
    }
}
