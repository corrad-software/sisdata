<?php

namespace Tests\Modules\Rbc;

use App\Models\User;
use App\Modules\Rbc\Events\LoginFailed;
use App\Modules\Rbc\Events\SuspiciousActivityDetected;
use App\Modules\Rbc\Events\UserLoggedIn;
use App\Modules\Rbc\Events\UserLoggedOut;
use App\Modules\Rbc\Models\AmaranKeselamatan;
use App\Modules\Rbc\Models\LogLogMasuk;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Modules\Rbc\Concerns\InteractsWithRbc;
use Tests\TestCase;

/** SRS-RBC-21/22: login attempt log and suspicious-activity alerts. */
class LoginMonitoringTest extends TestCase
{
    use InteractsWithRbc, RefreshDatabase;

    public function test_SRS_RBC_21_successful_and_failed_logins_are_recorded_with_context(): void
    {
        Event::fake([UserLoggedIn::class, LoginFailed::class]);
        $user = $this->userWithPassword();

        $this->login('pegawai@mais.test', 'Salah#Laluan01', ip: '192.0.2.10')->assertUnprocessable();
        $this->login('pegawai@mais.test', ip: '192.0.2.10')->assertOk();
        $this->login('tiada@mais.test', ip: '203.0.113.9')->assertUnprocessable();

        [$failed, $ok, $unknown] = LogLogMasuk::query()->orderBy('id')->get()->all();

        $this->assertSame([$user->id, false, 'kata_laluan_salah'], [$failed->user_id, $failed->berjaya, $failed->sebab]);
        $this->assertSame([$user->id, true, null], [$ok->user_id, $ok->berjaya, $ok->sebab]);
        $this->assertSame('192.0.2.10', $ok->alamat_ip);
        $this->assertSame(['Chrome', 'macOS', 'desktop'], [$ok->pelayar, $ok->platform, $ok->peranti]);
        $this->assertSame('Shah Alam, Selangor', $ok->lokasi);
        $this->assertSame([null, 'tiada@mais.test', 'pengguna_tidak_wujud', 'Singapura'], [$unknown->user_id, $unknown->pengecam, $unknown->sebab, $unknown->lokasi]);

        Event::assertDispatched(UserLoggedIn::class, fn ($e) => $e->user->is($user) && $e->method === 'kata_laluan');
        Event::assertDispatched(LoginFailed::class, fn ($e) => $e->reason === 'pengguna_tidak_wujud' && $e->identifier === 'tiada@mais.test' && $e->user === null);
    }

    public function test_SRS_RBC_21_logout_dispatches_event(): void
    {
        Event::fake([UserLoggedOut::class]);
        $user = $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();

        $this->api('POST', '/api/v1/logout')->assertNoContent();

        Event::assertDispatched(UserLoggedOut::class, fn ($e) => $e->user->is($user));
    }

    public function test_SRS_RBC_21_login_log_is_searchable_and_exportable(): void
    {
        $this->userWithPassword();
        $this->login('pegawai@mais.test', 'Salah#Laluan01')->assertUnprocessable();
        $this->login('pegawai@mais.test')->assertOk();
        $this->login('lain@mais.test')->assertUnprocessable();

        $auditor = User::factory()->withRole(BaseRoles::JURUAUDIT)->create();

        $this->actingAs($auditor)->getJson('/api/v1/rbc/log-masuk?search=pegawai&berjaya=0')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.sebab_label', 'Kata laluan salah');
        $this->actingAs($auditor)->getJson('/api/v1/rbc/log-masuk?alamat_ip=192.0.2.10&dari='.now()->toDateString().'&hingga='.now()->toDateString())
            ->assertOk()->assertJsonPath('meta.total', 3);

        $response = $this->actingAs($auditor)->get('/api/v1/rbc/log-masuk/eksport?search=pegawai')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));

        $file = tempnam(sys_get_temp_dir(), 'rbc').'.xlsx';
        file_put_contents($file, $response->getContent());
        $rows = IOFactory::load($file)->getActiveSheet()->toArray();
        @unlink($file);

        $dataRows = array_slice($rows, 4);
        $this->assertCount(2, $dataRows);
        $this->assertSame('pegawai@mais.test', $dataRows[0][2]);

        $this->actingAs($auditor)->get('/api/v1/rbc/log-masuk/eksport?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs(User::factory()->withRole(BaseRoles::PENGGUNA_PAPARAN)->create())->getJson('/api/v1/rbc/log-masuk')->assertForbidden();
    }

    public function test_SRS_RBC_22_repeated_failures_raise_an_alert(): void
    {
        Event::fake([SuspiciousActivityDetected::class]);
        $this->setPolicy(['kunci_ambang_cubaan' => 10]);
        $this->userWithPassword();

        foreach (range(1, 5) as $i) {
            $this->login('pegawai@mais.test', 'Salah#Laluan0'.$i)->assertUnprocessable();
        }

        $alert = AmaranKeselamatan::query()->sole();
        $this->assertSame(AmaranKeselamatan::GAGAL_BERULANG, $alert->jenis);
        $this->assertSame(5, $alert->butiran['cubaan_pengecam']);
        Event::assertDispatched(SuspiciousActivityDetected::class, fn ($e) => $e->type === 'gagal_berulang' && $e->ip === $this->clientIp);
    }

    public function test_SRS_RBC_22_login_from_new_device_or_location_raises_alerts(): void
    {
        Event::fake([SuspiciousActivityDetected::class]);
        $this->userWithPassword();

        $this->login('pegawai@mais.test', ip: '192.0.2.10')->assertOk();
        $this->assertSame(0, AmaranKeselamatan::count(), 'first ever login is the baseline');

        $this->login('pegawai@mais.test', ip: '192.0.2.10')->assertOk();
        $this->assertSame(0, AmaranKeselamatan::count());

        $this->login('pegawai@mais.test', ip: '203.0.113.50', ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1')->assertOk();

        $this->assertEqualsCanonicalizing([AmaranKeselamatan::PERANTI_BAHARU, AmaranKeselamatan::LOKASI_BAHARU], AmaranKeselamatan::query()->pluck('jenis')->all());
        $this->assertTrue(LogLogMasuk::query()->latest('id')->first()->mencurigakan);
        Event::assertDispatched(SuspiciousActivityDetected::class, fn ($e) => $e->type === 'lokasi_baharu' && $e->details['lokasi'] === 'Singapura');
    }

    public function test_SRS_RBC_22_repeated_unauthorised_access_attempts_raise_an_alert(): void
    {
        Event::fake([SuspiciousActivityDetected::class]);
        $user = User::factory()->create();

        foreach (['/api/v1/users', '/api/v1/rbc/peranan', '/api/v1/rbc/polisi'] as $uri) {
            $this->actingAs($user)->getJson($uri)->assertForbidden();
        }

        $alert = AmaranKeselamatan::query()->sole();
        $this->assertSame([AmaranKeselamatan::CAPAIAN_TANPA_KEBENARAN, $user->id], [$alert->jenis, $alert->user_id]);
        $this->assertSame('rbc.polisi.lihat', $alert->butiran['kebenaran']);
    }

    public function test_SRS_RBC_22_admin_reviews_alerts(): void
    {
        $admin = $this->admin();
        $alert = AmaranKeselamatan::query()->create(['jenis' => AmaranKeselamatan::LOKASI_BAHARU, 'alamat_ip' => '203.0.113.1', 'butiran' => ['lokasi' => 'Singapura']]);

        $this->actingAs($admin)->getJson('/api/v1/rbc/amaran?belum_disemak=1')
            ->assertOk()->assertJsonPath('meta.belum_disemak', 1)->assertJsonPath('data.0.jenis_label', 'Log masuk dari lokasi baharu');

        $this->actingAs($admin)->putJson("/api/v1/rbc/amaran/{$alert->id}/semak")->assertOk();
        $this->assertSame($admin->id, $alert->fresh()->disemak_oleh);

        $this->actingAs(User::factory()->withRole(BaseRoles::JURUAUDIT)->create())->putJson("/api/v1/rbc/amaran/{$alert->id}/semak")->assertForbidden();
    }
}
