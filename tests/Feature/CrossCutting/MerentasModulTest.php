<?php

namespace Tests\Feature\CrossCutting;

use App\Models\User;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cross-cutting SRS requirements (SRS §5.2 GEN, §12 NFR, §13 SEC) that are
 * provable in code. Mapped in docs/kesediaan-produksi.md.
 */
class MerentasModulTest extends TestCase
{
    use RefreshDatabase;

    public function test_SRS_SEC_05_api_responses_carry_browser_hardening_headers(): void
    {
        $this->getJson('/api/v1/user')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_SRS_SEC_05_hsts_is_sent_only_over_https(): void
    {
        $this->getJson('/api/v1/user')->assertHeaderMissing('Strict-Transport-Security');
        $this->getJson('https://localhost/api/v1/user')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_SRS_SEC_09_local_passwords_are_stored_as_adaptive_hashes(): void
    {
        $user = User::factory()->create(['password' => 'Rahsia@Panjang123']);

        $stored = (string) $user->fresh()->getRawOriginal('password');
        $this->assertNotSame('Rahsia@Panjang123', $stored);
        $this->assertContains(Hash::info($stored)['algoName'], ['bcrypt', 'argon2i', 'argon2id']);
        $this->assertTrue(Hash::check('Rahsia@Panjang123', $stored));
    }

    public function test_SRS_SEC_11_session_cookie_is_http_only_and_same_site(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
        $this->assertFalse(config('session.expire_on_close'));

        $cookie = collect($this->get('/sanctum/csrf-cookie')->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertContains(strtolower((string) $cookie->getSameSite()), ['lax', 'strict']);
    }

    public function test_SRS_SEC_12_state_changing_spa_request_without_csrf_token_is_refused(): void
    {
        $this->app['env'] = 'local'; // the CSRF middleware is skipped while running unit tests
        try {
            $this->withHeaders(['Referer' => 'http://localhost:3000/login', 'Origin' => 'http://localhost:3000'])
                ->postJson('/api/v1/login', ['email' => 'admin@example.com', 'password' => 'x'])
                ->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_SRS_NFR_05_list_endpoints_cap_the_page_size(): void
    {
        $user = User::factory()->withRole(BaseRoles::PENGGUNA_PAPARAN)->create();

        $this->actingAs($user)->getJson('/api/v1/pro/lot?per_page=200')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/pro/lot?per_page=100000')->assertUnprocessable()->assertJsonValidationErrors('per_page');

        // Endpoints that clamp instead of validating.
        $officer = User::factory()->withPermissions(['pro.import.cipta', 'pro.kualiti.lihat'])->create();
        $this->actingAs($officer)->getJson('/api/v1/pro/import?per_page=100000')->assertOk()->assertJsonPath('meta.per_page', 200);
    }

    public function test_SRS_GEN_09_system_messages_are_in_malay(): void
    {
        $this->assertSame('ms', app()->getLocale());
        $this->getJson('/api/v1/user')->assertJsonPath('message', 'Tidak disahkan. Sila log masuk.');
        $this->postJson('/api/v1/login', [])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', fn (string $m) => str_contains($m, 'wajib') || str_contains($m, 'diperlukan'));
    }
}
