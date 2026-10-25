<?php

namespace Tests\Modules\Kba;

use App\Modules\Kba\Contracts\ArticleDrafts;
use App\Modules\Kba\Models\Artikel;
use App\Modules\Kba\Services\PengurusArtikel;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class EksportSokonganTest extends KbaTestCase
{
    public function test_SRS_KBA_20_whole_knowledge_base_exports_to_markdown_and_html(): void
    {
        $this->korpus();
        $admin = $this->user([...self::PENTADBIR, 'kba.sulit.lihat']);
        $this->actingAs($admin)->postJson('/api/v1/kba/artikel', ['tajuk' => 'Artikel Eksport', 'kandungan' => 'Isi artikel untuk eksport.'])->assertCreated();

        $res = $this->actingAs($admin)->get('/api/v1/kba/eksport?format=markdown')->assertOk();
        $this->assertSame('application/zip', $res->headers->get('Content-Type'));
        $files = $this->zipOf($res);
        $this->assertArrayHasKey('INDEKS.md', $files);
        $this->assertArrayHasKey('pemetaan-kebenaran.md', $files);
        $manual = collect($files)->first(fn ($c, $n) => str_starts_with($n, 'sumber/') && str_contains($c, 'Manual Pengguna Ujian'));
        $this->assertStringContainsString('## 2. Muat Naik Dokumen', $manual);
        $this->assertStringContainsString('25 MB', $manual);
        $this->assertNotNull(collect($files)->first(fn ($c, $n) => str_starts_with($n, 'artikel/') && str_contains($c, 'Sejarah versi')));
        $this->assertNotNull(collect($files)->first(fn ($c) => str_contains($c, 'Kunci penyulitan')), 'Sulit included for a user cleared for it');
        $this->assertAudited('eksport', ['modul' => 'kba', 'id_pengguna' => $admin->id]);

        $html = $this->zipOf($this->actingAs($admin)->get('/api/v1/kba/eksport?format=html')->assertOk());
        $this->assertArrayHasKey('INDEKS.html', $html);
        $this->assertStringContainsString('<!doctype html>', $html['INDEKS.html']);
        $this->assertStringContainsString('.html"', $html['INDEKS.html']);

        $this->actingAs($this->user())->get('/api/v1/kba/eksport')->assertForbidden();
        $limited = $this->zipOf($this->actingAs($this->user(self::PENTADBIR))->get('/api/v1/kba/eksport'));
        $this->assertNull(collect($limited)->first(fn ($c) => str_contains($c, 'Kunci penyulitan')), 'export applies the same access filter');
    }

    public function test_SRS_KBA_09_support_view_shows_design_integration_parameters_and_problem_history_per_module(): void
    {
        $this->sumber("## Modul 11 · Dokumen Digital (DMS)\n\n**Kod:** MOD-DMS. Dokumen disimpan dalam MinIO dengan penyulitan AES-256. Parameter dms.had_saiz_pejabat_mb mengawal had saiz.\n\n### Integrasi\n\nIntegrasi dengan ClamAV untuk imbasan antivirus.", ['tajuk' => 'SDS DMS', 'jenis_dokumen' => 'SDS', 'modul' => 'dms']);
        $id = app(ArticleDrafts::class)->fromTicket('TKT-9', ['tajuk' => 'Muat naik gagal', 'masalah' => 'Gagal muat naik.', 'punca' => 'Fail besar.', 'penyelesaian' => 'Mampatkan fail.', 'modul' => 'dms']);
        app(PengurusArtikel::class)->hantar(Artikel::query()->findOrFail($id), $this->user(self::PENTADBIR));
        event(new WorkflowCompleted('wf-1', PengurusArtikel::ALIRAN, PengurusArtikel::SUBJEK, (string) $id, 'lulus', null));

        $tech = $this->user(['kba.soalan.lihat', 'kba.sokongan.lihat']);
        $modules = $this->actingAs($tech)->getJson('/api/v1/kba/sokongan')->assertOk()->json('data');
        $this->assertContains('dms', array_column($modules, 'modul'));

        $view = $this->actingAs($tech)->getJson('/api/v1/kba/sokongan/dms')->assertOk()->json('data');
        $this->assertNotEmpty($view['reka_bentuk']);
        $this->assertNotEmpty($view['integrasi']);
        $this->assertSame('dms.had_saiz_pejabat_mb', $view['parameter'][0]['kunci']);
        $this->assertSame('Muat naik gagal', $view['sejarah_masalah'][0]['tajuk']);
        $this->assertSame('/admin/tkt/tiket/TKT-9', $view['sejarah_masalah'][0]['pautan_tiket']);

        $this->actingAs($this->user())->getJson('/api/v1/kba/sokongan/dms')->assertForbidden();
    }

    /** @return array<string, string> */
    private function zipOf(TestResponse $res): array
    {
        $base = $res->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);

        return $this->zipFiles($base->getFile()->getPathname());
    }

    /** @return array<string, string> */
    private function zipFiles(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $out = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $out[$name] = (string) $zip->getFromIndex($i);
        }
        $zip->close();

        return $out;
    }
}
