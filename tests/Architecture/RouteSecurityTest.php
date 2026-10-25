<?php

namespace Tests\Architecture;

use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SRS-GEN-01 / SRS-SEC-12: no /api/v1 route is reachable without an
 * authenticated principal (SSO/Sanctum session, PRO API key or PWS portal
 * client), apart from a reviewed list of public endpoints — each of which is
 * rate limited.
 */
class RouteSecurityTest extends TestCase
{
    /** "METHOD uri" of the deliberately public endpoints. */
    private const PUBLIC = [
        'POST api/v1/login', 'POST api/v1/register', 'GET api/v1/settings',
        'GET api/v1/adm/penyelenggaraan',                 // maintenance banner for the login page
        'GET api/v1/pjk/awam/katalog', 'GET api/v1/pjk/awam/agihan',
        'POST api/v1/pjk/gateway/panggilan-balik',        // payment gateway webhook (signature-verified)
        'GET api/v1/adu/awam/rujukan', 'POST api/v1/adu/awam/aduan', 'POST api/v1/adu/awam/semak',
        'POST api/v1/ast/awam/qr/{ref}/imbas',
        'POST api/v1/pws/awam/token',                     // portal client credentials → token
    ];

    /** Public endpoints that need no throttle (no input processed). */
    private const UNTHROTTLED = ['POST api/v1/login', 'POST api/v1/register', 'GET api/v1/settings', 'GET api/v1/adm/penyelenggaraan'];

    public function test_SRS_GEN_01_every_api_route_requires_an_authenticated_principal_or_is_reviewed_public(): void
    {
        $unexpected = [];
        foreach ($this->apiRoutes() as $key => $middleware) {
            $authenticated = collect($middleware)->contains(fn (string $m) => Str::startsWith($m, 'auth') || Str::contains($m, ['Klien', 'pro.api']));
            if (! $authenticated && ! in_array($key, self::PUBLIC, true)) {
                $unexpected[] = $key;
            }
        }

        $this->assertSame([], $unexpected, 'Unauthenticated API routes not on the reviewed public list.');
    }

    public function test_SRS_GEN_01_public_endpoints_still_exist_and_are_rate_limited(): void
    {
        $routes = $this->apiRoutes();
        foreach (self::PUBLIC as $key) {
            $this->assertArrayHasKey($key, $routes, "Public route {$key} no longer exists — update the reviewed list.");
            if (! in_array($key, self::UNTHROTTLED, true)) {
                $this->assertTrue(collect($routes[$key])->contains(fn (string $m) => Str::startsWith($m, 'throttle')), "{$key} must be throttled.");
            }
        }
    }

    /** @return array<string, list<string>> "METHOD uri" => middleware */
    private function apiRoutes(): array
    {
        $out = [];
        /** @var Route $route */
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (! Str::startsWith($route->uri(), 'api/v1')) {
                continue;
            }
            $middleware = array_values(array_filter(Arr::wrap($route->gatherMiddleware()), 'is_string'));
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $out["{$method} {$route->uri()}"] = $middleware;
            }
        }

        return $out;
    }
}
