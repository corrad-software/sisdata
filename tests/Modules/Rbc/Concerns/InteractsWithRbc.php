<?php

namespace Tests\Modules\Rbc\Concerns;

use App\Models\User;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Services\RbcAccessControl;
use App\Modules\Rbc\Services\SecurityPolicy;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Testing\TestResponse;

/**
 * Drives the API like the real SPA: one session cookie carried between
 * requests, auth guards forgotten between them (so every request is
 * authenticated from the session, as in production), fixed browser UA/IP.
 */
trait InteractsWithRbc
{
    protected ?string $sessionCookie = null;

    protected string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    protected string $clientIp = '192.0.2.10';

    protected const STRONG_PASSWORD = 'Kukuh#Laluan2026';

    /** Called by Laravel's setUpTraits(): sessions on the database driver, as in production. */
    protected function setUpInteractsWithRbc(): void
    {
        config(['session.driver' => 'database']);
    }

    protected function admin(): User
    {
        return User::factory()->withRole(BaseRoles::PENTADBIR_SISTEM)->create();
    }

    protected function userWithPassword(string $email = 'pegawai@mais.test', string $password = self::STRONG_PASSWORD, array $attributes = []): User
    {
        return User::factory()->create(['email' => $email, 'password' => bcrypt($password), ...$attributes]);
    }

    protected function login(string $email, string $password = self::STRONG_PASSWORD, ?string $ip = null, ?string $ua = null): TestResponse
    {
        $this->sessionCookie = null;

        return $this->api('POST', '/api/v1/login', ['email' => $email, 'password' => $password], $ip, $ua);
    }

    /** @param array<string, mixed> $data */
    protected function api(string $method, string $uri, array $data = [], ?string $ip = null, ?string $ua = null): TestResponse
    {
        // A fresh "process" per request: no guard or session state carried over in memory.
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        RbcAccessControl::flushAll();

        $request = $this->withCredentials()->withHeaders([
            'Referer' => 'http://localhost:3000',
            'User-Agent' => $ua ?? $this->userAgent,
        ])->withServerVariables(['REMOTE_ADDR' => $ip ?? $this->clientIp]);

        $this->defaultCookies = [];
        if ($this->sessionCookie !== null) {
            $request = $request->withUnencryptedCookie(config('session.cookie'), $this->sessionCookie);
        }

        $response = $request->json($method, $uri, $data);

        if ($cookie = $response->getCookie(config('session.cookie'), false)) {
            $this->sessionCookie = $cookie->getValue();
        }

        // Don't leak this "browser" into later plain actingAs() calls.
        $this->defaultCookies = [];
        $this->defaultHeaders = [];
        $this->serverVariables = [];

        return $response;
    }

    /** @param array<string, mixed> $values */
    protected function setPolicy(array $values): void
    {
        app(SecurityPolicy::class)->update($values, null);
    }

    protected function role(string $code): Peranan
    {
        return Peranan::query()->where('kod', $code)->firstOrFail();
    }
}
