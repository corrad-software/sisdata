<?php

namespace App\Support\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider for every SISDATA module under app/Modules/<Nama>.
 *
 * A module only has to extend this and set $code; routes, migrations and
 * translations are picked up from the conventional folders if they exist:
 *
 *   routes.php            → mounted at /api/v1/{code}, `api` middleware group
 *   Database/migrations/  → run by `php artisan migrate`
 *   lang/                 → __('{code}::file.key')
 *
 * Modules talk to each other only through another module's Contracts/ or
 * Events/ namespace — enforced by tests/Architecture/ModuleBoundaryTest.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Short module code, e.g. "dms". Used as URL prefix and translation namespace. */
    protected string $code;

    public function boot(): void
    {
        $base = $this->modulePath();

        if (is_file($base.'/routes.php') && ! $this->app->routesAreCached()) {
            Route::prefix('api/v1/'.$this->code)
                ->middleware('api')
                ->name($this->code.'.')
                ->group($base.'/routes.php');
        }

        if (is_dir($base.'/Database/migrations')) {
            $this->loadMigrationsFrom($base.'/Database/migrations');
        }

        if (is_dir($base.'/lang')) {
            $this->loadTranslationsFrom($base.'/lang', $this->code);
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    protected function modulePath(): string
    {
        return dirname((new \ReflectionClass($this))->getFileName());
    }
}
