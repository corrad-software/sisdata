<?php

namespace Tests\Modules\Dsh;

use App\Modules\Dsh\Contracts\InsightGenerator;
use App\Modules\Dsh\Insight\OllamaInsight;
use App\Modules\Dsh\Insight\PeraturanInsight;
use App\Modules\Dsh\Models\LogPromptAi;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class AiPromptTest extends DshTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->lot(['kod_mukim' => '100803']);
        $this->lot(['kod_mukim' => '100803']);
        $this->lot(['kod_mukim' => '100901']);
    }

    private function ask(mixed $user, string $prompt, array $extra = []): array
    {
        return $this->actingAs($user)->postJson('/api/v1/dsh/ai/prompt', ['prompt' => $prompt, 'aras' => 'pengurusan', ...$extra])->assertOk()->json('data');
    }

    public function test_SRS_DSH_15_plain_language_question_answers_with_a_number(): void
    {
        $res = $this->ask($this->user(), 'Berapa jumlah lot tanah di Petaling?');
        $this->assertSame('dijawab', $res['status']);
        $this->assertSame(['pro.bilangan_lot'], $res['pertanyaan']['kpi']);
        $this->assertSame('1008', $res['pertanyaan']['penapis']['kod_daerah']);
        $this->assertSame(2, $res['jawapan']['kpi'][0]['nilai']);
        $this->assertSame('nilai', $res['jawapan']['jenis']);
        $this->assertStringContainsString('Jumlah tanah / aset: 2 lot', $res['jawapan']['teks']);

        $en = $this->ask($this->user(), 'Show the land area trend for 2026');
        $this->assertSame(['pro.keluasan'], $en['pertanyaan']['kpi']);
        $this->assertSame(2026, $en['pertanyaan']['penapis']['tahun']);
        $this->assertSame('carta', $en['jawapan']['jenis']);

        $list = $this->ask($this->user(), 'senarai lot tanah di Sepang');
        $this->assertSame('jadual', $list['jawapan']['jenis']);
        $this->assertSame(1, $list['jawapan']['jadual']['jumlah']);

        $unknown = $this->ask($this->user(), 'apa khabar hari ini');
        $this->assertSame('tidak_difahami', $unknown['status']);
        $this->assertNotEmpty($unknown['jawapan']['cadangan']);
    }

    public function test_SRS_DSH_16_prompt_changes_the_personal_layout(): void
    {
        $user = $this->user();
        $res = $this->ask($user, 'buang widget keluasan tanah');
        $this->assertSame('susun_atur', $res['jawapan']['jenis']);
        $this->assertSame(['kpi:pro.keluasan'], $res['jawapan']['susun_atur']['widget']);
        $layout = collect($this->actingAs($user)->getJson('/api/v1/dsh/susun-atur?aras=pengurusan')->json('data'));
        $this->assertTrue($layout->firstWhere('kod', 'kpi:pro.keluasan')['tersembunyi']);

        $this->ask($user, 'tambah widget keluasan ke dashboard');
        $layout = collect($this->actingAs($user)->getJson('/api/v1/dsh/susun-atur?aras=pengurusan')->json('data'));
        $this->assertFalse($layout->firstWhere('kod', 'kpi:pro.keluasan')['tersembunyi']);
        $this->assertSame('kpi:pro.keluasan', $layout->first()['kod'], 'shown widgets move to the top');
    }

    public function test_SRS_DSH_17_district_officer_asking_about_another_district_gets_own_scope_and_is_audited(): void
    {
        $officer = $this->user(self::EKSEKUTIF, ['1009']);
        $res = $this->ask($officer, 'Berapa jumlah lot tanah di Petaling?');

        $this->assertSame('terhad', $res['status']);
        $this->assertArrayNotHasKey('kod_daerah', $res['pertanyaan']['penapis']);
        $this->assertSame(1, $res['jawapan']['kpi'][0]['nilai'], 'answer limited to Sepang (own scope), never Petaling');
        $this->assertStringContainsString('Petaling', $res['jawapan']['nota'][0]);

        $log = LogPromptAi::query()->findOrFail($res['id']);
        $this->assertSame('terhad', $log->status);
        $this->assertSame(['1009'], $log->skop_rbac['daerah']);
        $this->assertSame('1008', $log->pertanyaan_dijana['daerah_diminta']);
        $this->assertAudited('prompt_ai', ['modul' => 'dsh', 'id_pengguna' => $officer->id, 'status_transaksi' => 'ditolak']);

        // Every prompt is logged, answered or not.
        $this->ask($officer, 'aduan terbuka');
        $this->assertSame(2, LogPromptAi::query()->where('id_pengguna', $officer->id)->count());
        $this->actingAs($officer)->getJson('/api/v1/dsh/ai/log')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_SRS_DSH_17_prompt_cannot_reach_kpis_outside_the_users_permissions(): void
    {
        $user = $this->user(['dsh.dashboard.lihat', 'dsh.ai.lihat', 'dsh.pengurusan.lihat', 'pro.lot.lihat']);
        $res = $this->ask($user, 'nilai portfolio tanah');
        $this->assertNotContains('pro.nilai_portfolio', $res['pertanyaan']['kpi']);

        $this->actingAs($this->user(['dsh.dashboard.lihat', 'dsh.pengurusan.lihat']))->postJson('/api/v1/dsh/ai/prompt', ['prompt' => 'jumlah tanah'])->assertForbidden();
    }

    public function test_SRS_DSH_18_default_engine_is_local_rules_and_no_request_leaves_the_server(): void
    {
        $this->assertInstanceOf(PeraturanInsight::class, app(InsightGenerator::class));
        $res = $this->ask($this->user(), 'jumlah tanah');
        $this->assertSame('peraturan', $res['enjin']);
        $insights = $this->actingAs($this->user())->getJson('/api/v1/dsh/ai/insight?aras=pengurusan')->assertOk();
        $insights->assertJsonPath('enjin', 'peraturan');
        $this->assertNotEmpty($insights->json('data'));
        Http::assertNothingSent();
    }

    public function test_SRS_DSH_18_ai_adapter_refuses_public_endpoints(): void
    {
        foreach (['https://api.openai.com', 'http://8.8.8.8:11434', 'ftp://127.0.0.1'] as $url) {
            try {
                OllamaInsight::assertLocal($url);
                $this->fail("{$url} should be refused");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach (['http://127.0.0.1:11434', 'http://localhost:11434', 'http://10.20.0.5:11434', 'http://ollama.mais.internal'] as $url) {
            OllamaInsight::assertLocal($url);
        }

        // Misconfigured to a public host → the provider falls back to rules.
        config(['dsh.ai.pemacu' => 'ollama', 'dsh.ai.ollama_url' => 'https://ai.example.com']);
        $this->app->forgetInstance(InsightGenerator::class);
        $this->assertInstanceOf(PeraturanInsight::class, app(InsightGenerator::class));
    }

    public function test_SRS_DSH_18_local_ollama_adapter_output_is_validated_against_the_catalogue(): void
    {
        config(['dsh.ai.pemacu' => 'ollama', 'dsh.ai.ollama_url' => 'http://127.0.0.1:11434']);
        $this->app->forgetInstance(InsightGenerator::class);
        $this->assertInstanceOf(OllamaInsight::class, app(InsightGenerator::class));
        Http::fake(['127.0.0.1:11434/*' => Http::response(['response' => json_encode(['niat' => 'nilai', 'kpi' => ['pro.bilangan_lot', 'rahsia.tiada'], 'penapis' => ['kod_daerah' => '1009']])])]);

        $res = $this->ask($this->user(), 'how many lots in Sepang');
        $this->assertSame('ollama', $res['enjin']);
        $this->assertSame(['pro.bilangan_lot'], $res['pertanyaan']['kpi'], 'keys outside the catalogue are dropped');
        $this->assertSame(1, $res['jawapan']['kpi'][0]['nilai']);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'http://127.0.0.1:11434/api/generate'));
    }
}
