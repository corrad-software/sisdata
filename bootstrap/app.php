<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiErrorRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->statefulApi();

        // TLS ends at the Coolify proxy. Next.js then calls this API on the
        // Docker network, so the client IP and scheme arrive in X-Forwarded-*.
        $middleware->trustProxies(at: '*');

        // There is no web "login" route (Next.js owns /login): an API call
        // without "Accept: application/json" must get the JSON 401, not a
        // 500 from building route('login').
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : (string) config('services.keycloak.login_page', '/login'));

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(new ApiErrorRenderer);
    })->create();
