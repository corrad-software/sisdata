<?php

namespace Tests\Modules\Pws;

use App\Models\User;
use App\Modules\Kba\Contracts\KnowledgeBase;
use App\Modules\Kba\Contracts\LlmGateway;
use App\Modules\Kes\Contracts\MfaAssurance;
use App\Modules\Pws\Models\KlienApi;
use App\Modules\Pws\PwsServiceProvider;
use App\Modules\Pws\Services\KlienService;
use App\Modules\Tkt\Contracts\TicketIntake;
use Database\Seeders\PwsSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Modules\Pjk\PjkTestCase;
use Tests\Modules\Pws\Support\FakeKnowledgeBase;
use Tests\Modules\Pws\Support\FakeLlm;
use Tests\Modules\Pws\Support\FakeTicketIntake;
use Tests\Modules\Pws\Support\SwitchableMfa;

/**
 * MOD-PWS tests run the public gateway against the REAL MOD-PJK, MOD-PER and
 * MOD-ADU HTTP APIs in-process (catalogue, portal applications, payments
 * with the mock gateway + signed webhook, waqf cases, public complaints),
 * real RBC (the integration account seeded by PwsSeeder) and real AUD.
 * Faked at their contracts: WFE, DMS, NTF (from PjkTestCase), the local LLM,
 * KBA, TKT (built in parallel) and MFA.
 */
abstract class PwsTestCase extends PjkTestCase
{
    protected FakeLlm $llm;

    protected FakeTicketIntake $tickets;

    protected FakeKnowledgeBase $kb;

    protected SwitchableMfa $mfa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(LlmGateway::class, $this->llm = new FakeLlm);
        $this->app->instance(TicketIntake::class, $this->tickets = new FakeTicketIntake);
        $this->app->instance(KnowledgeBase::class, $this->kb = new FakeKnowledgeBase);
        $this->app->instance(MfaAssurance::class, $this->mfa = new SwitchableMfa);
        User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin', 'is_active' => true]); // WFE definition author
        $this->seed(PwsSeeder::class);
    }

    /** Officer with MOD-PWS permissions. */
    protected function pegawai(?array $permissions = null): User
    {
        return $this->user($permissions ?? array_keys(PwsServiceProvider::PERMISSIONS));
    }

    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $extra
     * @return array{0: KlienApi, 1: string}
     */
    protected function klien(array $scopes = ['baca', 'permohonan', 'bayaran', 'aduan', 'sokongan', 'analitik'], array $extra = []): array
    {
        return app(KlienService::class)->create(['nama' => 'Pelayan Portal Ujian', 'skop' => $scopes] + $extra, $this->pegawai());
    }

    protected function token(?array $scopes = null, array $extra = []): string
    {
        [$klien, $secret] = $this->klien($scopes ?? ['baca', 'permohonan', 'bayaran', 'aduan', 'sokongan', 'analitik'], $extra);

        return $this->awam('POST', 'token', ['grant_type' => 'client_credentials', 'client_id' => $klien->id_klien, 'client_secret' => $secret])
            ->assertOk()->json('access_token');
    }

    /**
     * Server-to-server call as the portal would make it (no Origin, no
     * Referer, no session).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function awam(string $method, string $uri, array $data = [], ?string $token = null, array $headers = [], array $files = []): TestResponse
    {
        $saved = $this->defaultHeaders;
        $this->defaultHeaders = [];
        $headers += ['Accept' => 'application/json', 'Accept-Language' => 'ms'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        try {
            $server = $this->transformHeadersToServerVars($headers);
            if ($method === 'GET') {
                return $this->call('GET', '/api/v1/pws/awam/'.$uri, $data, [], [], $server);
            }

            return $this->call($method, '/api/v1/pws/awam/'.$uri, $data, [], $files, $server);
        } finally {
            $this->defaultHeaders = $saved;
        }
    }

    protected function kunci(string $suffix = '1'): array
    {
        return ['Idempotency-Key' => 'ujian-kunci-'.$suffix];
    }
}
