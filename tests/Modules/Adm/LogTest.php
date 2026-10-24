<?php

namespace Tests\Modules\Adm;

use App\Modules\Adm\Contracts\Integrations;
use App\Modules\Adm\Models\LogIntegrasi;
use App\Modules\Adm\Models\LogRalat;
use App\Modules\Adm\Services\Logging\Redactor;
use App\Modules\Adm\Services\ParameterService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;

class LogTest extends AdmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('api/v1/adm/__ujian-ralat', function () {
            throw new RuntimeException('Jadual lot_tanah rosak; password=Sulit123 token=abc.def');
        });
    }

    public function test_SRS_ADM_11_errors_are_logged_with_module_severity_code_user_ip_and_trace(): void
    {
        $user = $this->admin();
        $res = $this->actingAs($user)->getJson('/api/v1/adm/__ujian-ralat')->assertStatus(500);

        $row = LogRalat::query()->where('id_korelasi', $res->json('request_id'))->sole();
        $this->assertSame('error', $row->aras_keterukan);
        $this->assertSame('adm', $row->modul);
        $this->assertSame('RuntimeException', $row->kod_ralat);
        $this->assertSame($user->id, $row->id_pengguna);
        $this->assertSame('127.0.0.1', $row->alamat_ip);
        $this->assertStringContainsString('LogTest.php', (string) $row->jejak_tindanan);
    }

    public function test_SRS_ADM_12_the_reference_shown_to_the_user_finds_the_log_entry(): void
    {
        config(['app.debug' => false]);
        $admin = $this->admin();
        $res = $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/__ujian-ralat')->assertStatus(500);
        $requestId = $res->json('request_id');
        $this->assertSame($requestId, $res->headers->get('X-Request-Id'));

        // Acceptance criterion 4: what the user sees has no internals; the admin finds the entry by the ID.
        $this->assertStringNotContainsString('lot_tanah', (string) $res->getContent());
        $this->actingAs($admin)->getJson('/api/v1/adm/log/ralat?id_korelasi='.$requestId)
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.kod_ralat', 'RuntimeException');
    }

    public function test_SRS_ADM_11_search_filters_by_period_module_severity_code_and_user(): void
    {
        Log::warning('Amaran DMS', ['modul' => 'dms', 'kod_ralat' => 'DMS_KUOTA', 'id_pengguna' => 7]);
        Log::error('Ralat WFE', ['modul' => 'wfe', 'kod_ralat' => 'WFE_SLA']);
        Log::info('Info tidak masuk jadual'); // below warning: file/JSON only
        LogRalat::query()->create(['tarikh_masa' => now()->subDays(40), 'aras_keterukan' => 'error', 'modul' => 'dms', 'mesej' => 'lama']);

        $admin = $this->admin();
        $get = fn (string $qs) => $this->actingAs($admin)->getJson('/api/v1/adm/log/ralat?'.$qs)->assertOk();

        $this->assertSame(2, $get('modul=dms')->json('total'));
        $this->assertSame(1, $get('modul=dms&dari='.now()->subDays(7)->toDateString())->json('total'));
        $this->assertSame(1, $get('aras[]=warning')->json('total'));
        $this->assertSame('WFE_SLA', $get('kod_ralat=WFE')->json('data.0.kod_ralat'));
        $this->assertSame(1, $get('id_pengguna=7')->json('total'));
        $this->assertSame(0, LogRalat::query()->where('mesej', 'Info tidak masuk jadual')->count());
    }

    public function test_SRS_ADM_11_log_access_is_restricted_and_audited(): void
    {
        $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/log/ralat')->assertForbidden();
        $this->actingAs($this->userWith(['adm.log.lihat']))->getJson('/api/v1/adm/log/ralat/eksport')->assertForbidden();

        $this->actingAs($this->userWith(['adm.log.lihat']))->getJson('/api/v1/adm/log/ralat?modul=adm')->assertOk();
        $this->assertSame('adm_log_ralat', $this->audit->ofAction('lihat')[0]['entityType']);
    }

    public function test_SRS_ADM_14_application_and_integration_logs_export_by_period(): void
    {
        Log::error('Ralat untuk eksport', ['modul' => 'pro']);
        $this->app->make(Integrations::class)->log('jupem', false, 504, 3000, 'Gateway timeout', 'tarik_lot');

        $admin = $this->admin();
        $csv = $this->actingAs($admin)->get('/api/v1/adm/log/ralat/eksport?dari='.now()->toDateString())->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('Ralat untuk eksport', $csv->streamedContent());

        $this->actingAs($admin)->getJson('/api/v1/adm/log/integrasi?kod_integrasi=jupem')->assertOk()->assertJsonPath('data.0.status_http', 504);
        $this->assertStringContainsString('tarik_lot', $this->actingAs($admin)->get('/api/v1/adm/log/integrasi/eksport')->streamedContent());
        $this->assertCount(2, $this->audit->ofAction('eksport'));
    }

    public function test_SRS_ADM_14_retention_policy_prunes_old_rows_per_configured_period(): void
    {
        LogRalat::query()->create(['tarikh_masa' => now()->subDays(200), 'aras_keterukan' => 'error', 'mesej' => 'lama']);
        LogRalat::query()->create(['tarikh_masa' => now()->subDays(10), 'aras_keterukan' => 'error', 'mesej' => 'baru']);
        LogIntegrasi::query()->create(['kod_integrasi' => 'x', 'tarikh_masa' => now()->subDays(100), 'berjaya' => true]);

        $this->artisan('adm:pangkas-log')->assertSuccessful();
        $this->assertSame(0, LogRalat::query()->where('mesej', 'lama')->count());
        $this->assertSame(1, LogRalat::query()->where('mesej', 'baru')->count());
        $this->assertSame(1, LogIntegrasi::query()->count());

        // Shorter period configured → older integration rows go too.
        app(ParameterService::class)->store('adm.log_integrasi_simpan_hari', 30, 'ujian', null);
        $this->artisan('adm:pangkas-log')->assertSuccessful();
        $this->assertSame(0, LogIntegrasi::query()->count());
    }

    public function test_SRS_ADM_15_logs_never_store_passwords_tokens_keys_or_full_ic_numbers(): void
    {
        $res = $this->actingAs($this->admin())->getJson('/api/v1/adm/__ujian-ralat');
        $row = LogRalat::query()->where('id_korelasi', $res->json('request_id'))->sole();
        $this->assertStringNotContainsString('Sulit123', $row->mesej.$row->jejak_tindanan);

        Log::warning('Log masuk gagal untuk 850101-10-5523', [
            'modul' => 'rbc',
            'password' => 'P@ssw0rd!',
            'nested' => ['api_key' => 'k-123', 'header' => 'Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.abcdefghij'],
        ]);
        $row = LogRalat::query()->where('modul', 'rbc')->sole();
        $stored = json_encode($row->toArray());
        foreach (['P@ssw0rd!', 'k-123', 'eyJhbGciOiJIUzI1NiJ9', '850101-10-5523'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
        $this->assertStringContainsString('******-**-5523', $row->mesej);

        $this->app->make(Integrations::class)->log('jupem', false, 401, null, 'Denied for https://user:hunter2@api.jupem.gov.my/?token=xyz');
        $this->assertStringNotContainsString('hunter2', (string) LogIntegrasi::query()->value('mesej'));
        $this->assertStringNotContainsString('xyz', (string) LogIntegrasi::query()->value('mesej'));
    }

    public function test_SRS_ADM_15_redactor_patterns(): void
    {
        $this->assertSame('******-**-1234 dan ********5678', Redactor::maskIc('900101-14-1234 dan 900101145678'));
        $this->assertSame('Tarikh 20261024 kekal', Redactor::maskIc('Tarikh 20261024 kekal'));
        $this->assertStringNotContainsString('s3cr3t', (string) Redactor::string('{"client_secret":"s3cr3t"}'));
        $this->assertStringNotContainsString('AKIAABCDEFGHIJKLMNOP', (string) Redactor::string('key AKIAABCDEFGHIJKLMNOP'));
        $this->assertStringNotContainsString('MIIE', (string) Redactor::string("-----BEGIN RSA PRIVATE KEY-----\nMIIE\n-----END RSA PRIVATE KEY-----"));
        $this->assertSame(['kunci' => 'adm.x', 'kata_laluan' => Redactor::MASK], Redactor::array(['kunci' => 'adm.x', 'kata_laluan' => 'abc']));
    }

    public function test_SRS_ADM_15_structured_json_log_file_is_redacted_and_carries_request_id(): void
    {
        $path = storage_path('logs/sisdata.json-'.now()->format('Y-m-d').'.log');
        Log::warning('JSON token=zzz-secret', ['modul' => 'adm']);

        $last = json_decode((string) collect(file($path))->last(), true);
        $this->assertSame('WARNING', $last['level_name']);
        $this->assertStringNotContainsString('zzz-secret', $last['message']);
    }
}
