<?php

namespace Tests\Modules\Pws;

use App\Modules\Pws\Http\Middleware\AuthenticateKlien;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/** SRS-PWS-02/03/20: separate system, WordPress theme/plugin, no master or personal records left in the gateway. */
class SempadanSistemTest extends PwsTestCase
{
    public function test_SRS_PWS_02_portal_reaches_sisdata_only_through_the_versioned_client_authenticated_api(): void
    {
        $awam = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/pws/awam/'));
        $this->assertGreaterThan(15, $awam->count());
        foreach ($awam as $r) {
            if ($r->uri() === 'api/v1/pws/awam/token') {
                continue;
            }
            $mw = collect($r->gatherMiddleware())->map(fn ($m) => (string) $m);
            $this->assertTrue($mw->contains(fn ($m) => str_starts_with($m, AuthenticateKlien::class)), $r->uri().' tanpa pengesahan klien');
            $this->assertFalse($mw->contains(fn ($m) => str_contains($m, 'auth:sanctum')), $r->uri().' menggunakan sesi SISDATA');
        }
        // A SISDATA staff session is no substitute for the portal's client credentials.
        $this->actingAs($this->pegawai())->getJson('/api/v1/pws/awam/kandungan')->assertUnauthorized();
    }

    public function test_SRS_PWS_03_wordpress_theme_and_plugin_pass_php_lint_and_are_fully_bilingual(): void
    {
        $root = dirname(__DIR__, 3).'/pws';
        $this->assertFileExists($root.'/wp-content/themes/hartanah-wakaf-selangor/style.css');
        $this->assertFileExists($root.'/wp-content/plugins/sisdata-connector/sisdata-connector.php');
        exec('php '.escapeshellarg($root.'/bin/check-i18n.php').' '.escapeshellarg($root.'/wp-content').' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->getExtension() === 'php') {
                exec('php -l '.escapeshellarg($f->getPathname()).' 2>&1', $lint, $c);
                $this->assertSame(0, $c, $f->getPathname());
            }
        }
        $css = (string) file_get_contents($root.'/wp-content/themes/hartanah-wakaf-selangor/assets/css/main.css');
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('hws-skip', (string) file_get_contents($root.'/wp-content/themes/hartanah-wakaf-selangor/header.php'));
    }

    public function test_SRS_PWS_20_no_master_record_or_personal_data_is_kept_by_the_gateway_after_delivery(): void
    {
        $token = $this->token();
        $premis = $this->premis();
        $this->awam('POST', 'permohonan/wakaf', ['nama_pewakaf' => 'Pewakaf Sintetik', 'no_pengenalan' => '700101-10-1234', 'emel' => 'a@contoh.invalid', 'persetujuan_pdpa' => '1'], $token, $this->kunci('a'))
            ->assertCreated();
        $this->awam('POST', 'permohonan/sewaan', ['kod_premis' => $premis->kod_premis, 'jenis_entiti' => 'individu', 'nama' => 'Penyewa Sintetik', 'no_pengenalan' => '900101-10-5678',
            'emel' => 'b@contoh.invalid', 'persetujuan_pdpa' => '1'], $token, $this->kunci('b'), ['lampiran' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertCreated();

        $dump = (string) json_encode([DB::table('pws_permohonan')->get(), DB::table('pws_log_integrasi')->get(), DB::table('pws_idempotensi')->get()]);
        foreach (['700101-10-1234', '900101-10-5678', 'a@contoh.invalid', 'b@contoh.invalid', 'Pewakaf Sintetik', 'Penyewa Sintetik'] as $pii) {
            $this->assertStringNotContainsString($pii, $dump);
        }
        $this->assertSame(0, DB::table('pws_permohonan')->whereNotNull('kandungan')->count());
    }
}
