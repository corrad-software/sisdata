<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/platform/status — what's wired up in this environment:
 * enabled modules and the Postgres extensions SISDATA depends on.
 */
class StatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $extensions = DB::connection()->getDriverName() === 'pgsql'
            ? DB::table('pg_extension')->pluck('extversion', 'extname')->all()
            : [];

        return response()->json([
            'data' => [
                'app' => config('app.name'),
                'environment' => app()->environment(),
                'modules' => collect(config('modules.enabled', []))
                    ->map(fn (string $provider) => app()->getProvider($provider))
                    ->filter(fn ($provider) => $provider instanceof ModuleServiceProvider)
                    ->map(fn (ModuleServiceProvider $provider) => $provider->code())
                    ->values()
                    ->all(),
                'extensions' => $extensions,
                'message' => __('platform::status.ok'),
            ],
        ]);
    }
}
