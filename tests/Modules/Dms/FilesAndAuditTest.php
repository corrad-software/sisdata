<?php

namespace Tests\Modules\Dms;

use App\Models\User;
use App\Modules\Dms\Models\DokumenVersi;
use App\Modules\Dms\Services\Watermark\Watermarker;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Dms\Support\TestFiles;

class FilesAndAuditTest extends DmsTestCase
{
    private function link(User $user, string $docId, string $kind, ?int $version = null): string
    {
        return $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$docId}/pautan", array_filter(['jenis' => $kind, 'versi' => $version]))
            ->assertOk()->json('data.url');
    }

    public function test_SRS_DMS_02_pdf_and_images_preview_inline_in_the_browser(): void
    {
        $user = $this->userWith();
        $pdf = $this->uploadDoc($user, TestFiles::pdf());
        $png = $this->uploadDoc($user, TestFiles::png());
        $docx = $this->uploadDoc($user, TestFiles::docx());

        $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$pdf->id}")->assertJsonPath('data.pratonton', 'pdf');
        $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$docx->id}")->assertJsonPath('data.pratonton', 'teks');

        $response = $this->actingAs($user)->get($this->link($user, $pdf->id, 'pratonton'))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $this->actingAs($user)->get($this->link($user, $png->id, 'pratonton'))->assertOk()->assertHeader('Content-Type', 'image/png');

        // Office documents: no server-side renderer — the API says so and the UI shows the extracted text.
        $this->actingAs($user)->get($this->link($user, $docx->id, 'pratonton'))->assertStatus(415);
    }

    public function test_SRS_DMS_26_sulit_preview_and_print_carry_a_dynamic_watermark(): void
    {
        $user = $this->userWith([...self::PEGAWAI, 'dms.sulit.lihat']);
        $pdf = $this->uploadDoc($user, TestFiles::pdf('Hak milik sulit'), ['klasifikasi' => 'sulit']);
        $png = $this->uploadDoc($user, TestFiles::png('w.png', 600, 400), ['klasifikasi' => 'sulit']);

        $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$pdf->id}")->assertJsonPath('data.tanda_air', true);

        $preview = $this->actingAs($user)->get($this->link($user, $pdf->id, 'pratonton'))->assertOk();
        $content = $preview->getContent();
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertNotSame($pdf->versiSemasa->cincangan_fail, hash('sha256', $content));
        $plain = $this->pdfText($content);
        if ($plain !== null) {
            $this->assertStringContainsString('SULIT - ID '.$user->id, $plain);
            $this->assertStringContainsString(now()->format('d/m/Y'), $plain);
            $this->assertStringContainsString('Hak milik sulit', $plain); // original content kept
        }

        $image = $this->actingAs($user)->get($this->link($user, $png->id, 'cetak'))->assertOk();
        $this->assertSame('image/jpeg', $image->headers->get('Content-Type'));
        $gd = imagecreatefromstring($image->getContent());
        $this->assertSame([600, 400], [imagesx($gd), imagesy($gd)]);
        // Original was a flat colour; the watermark changed pixels.
        $colours = [];
        for ($x = 0; $x < 600; $x += 7) {
            for ($y = 0; $y < 400; $y += 7) {
                $colours[imagecolorat($gd, $x, $y)] = true;
            }
        }
        $this->assertGreaterThan(5, count($colours));

        // Download of the original is not watermarked (watermark is for preview/print).
        $download = $this->actingAs($user)->get($this->link($user, $pdf->id, 'muat_turun'))->assertOk();
        $this->assertSame($pdf->versiSemasa->cincangan_fail, hash('sha256', $download->streamedContent()));
    }

    public function test_SRS_DMS_26_watermarker_handles_pdfs_the_free_parser_cannot_read(): void
    {
        $watermarker = app(Watermarker::class);
        if (! is_executable(trim((string) shell_exec('command -v pdftoppm')))) {
            $this->markTestSkipped('pdftoppm not installed');
        }
        // A PDF 1.5 with a cross-reference stream (as produced by most modern tools).
        $path = tempnam(sys_get_temp_dir(), 'xref');
        file_put_contents($path, $this->pdfWithXrefStream());

        $out = $watermarker->pdf($path, 'RAHSIA - ID 1 Ujian - 24/10/2026 10:00:00');
        $this->assertStringStartsWith('%PDF', $out);
        @unlink($path);
    }

    public function test_SRS_DMS_24_upload_download_preview_print_view_archive_are_audited(): void
    {
        $user = $this->userWith([...self::PEGAWAI, 'dms.rahsia.lihat', 'dms.arkib.kemas_kini']);
        $doc = $this->uploadDoc($user, TestFiles::pdf(), ['klasifikasi' => 'rahsia']);

        $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$doc->id}")->assertOk();
        $this->actingAs($user)->get($this->link($user, $doc->id, 'muat_turun'))->assertOk()->streamedContent();
        $this->actingAs($user)->get($this->link($user, $doc->id, 'pratonton'))->assertOk();
        $this->actingAs($user)->get($this->link($user, $doc->id, 'cetak'))->assertOk();
        $this->actingAs($user)->post("/api/v1/dms/dokumen/{$doc->id}/versi", ['fail' => TestFiles::pdf('v2')], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/arkib")->assertOk();

        foreach (['muat_naik', 'lihat', 'muat_turun', 'pratonton', 'cetak', 'versi_baharu', 'arkib'] as $action) {
            $this->assertCount(1, $this->audit->of($action, $doc->id), "missing audit: {$action}");
        }
        $this->assertSame(1, $this->audit->of('muat_turun', $doc->id)[0]['meta']['versi']);
        $this->assertTrue($this->audit->of('cetak', $doc->id)[0]['meta']['tanda_air']);
    }

    public function test_SRS_DMS_22_signed_links_are_short_lived_user_bound_and_tamper_proof(): void
    {
        $user = $this->userWith();
        $other = $this->userWith();
        $doc = $this->uploadDoc($user);
        $url = $this->link($user, $doc->id, 'muat_turun');

        $this->assertStringStartsWith('/api/v1/dms/fail/', $url);
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($user)->get(str_replace('muat_turun', 'pratonton', $url))->assertForbidden();
        $this->actingAs($user)->get(preg_replace('/u=\d+/', 'u='.$other->id, $url))->assertForbidden();

        $this->travel(6)->minutes();
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->travelBack();

        // Access is re-checked when the link is used.
        $fresh = $this->link($user, $doc->id, 'muat_turun');
        $this->acl->permissions[$user->id] = [];
        $this->actingAs($user)->get($fresh)->assertForbidden();
    }

    public function test_SRS_DMS_25_quota_usage_is_shown_and_80_percent_alerts_admins(): void
    {
        $admin = $this->userWith(['dms.kuota.lihat']);
        $user = $this->userWith();
        $this->params->set('dms.kuota_gb', 1);

        $this->uploadDoc($user, TestFiles::pdf());
        $this->actingAs($admin)->getJson('/api/v1/dms/kuota')->assertOk()
            ->assertJsonPath('data.kuota_bait', 1024 ** 3)
            ->assertJsonPath('data.amaran', false)
            ->assertJsonPath('data.ikut_kategori.0.kod', 'laporan');
        $this->assertCount(0, $this->notifier->of('dms.kuota_amaran'));
        $this->actingAs($user)->getJson('/api/v1/dms/kuota')->assertForbidden();

        // Simulate 85 % usage.
        DB::table('dokumen_versi')->update(['saiz_bait' => (int) (0.85 * 1024 ** 3)]);
        $this->artisan('dms:semak-kuota')->assertSuccessful();
        $sent = $this->notifier->of('dms.kuota_amaran');
        $this->assertCount(1, $sent);
        $this->assertSame([$admin->id], $sent[0]['recipients']);
        $this->assertSame('85', $sent[0]['data']['peratus']);
        $this->assertSame('dms.kuota_amaran:'.now()->toDateString(), $sent[0]['dedupe']);

        // Full quota: further uploads rejected.
        DB::table('dokumen_versi')->update(['saiz_bait' => 1024 ** 3]);
        $this->upload($user, TestFiles::pdf())->assertJsonPath('keputusan.0.sebab', 'kuota_penuh');
    }

    public function test_SRS_DMS_18_api_rejects_users_without_permission(): void
    {
        $this->getJson('/api/v1/dms/dokumen')->assertUnauthorized();

        $nobody = $this->userWith([]);
        $doc = $this->uploadDoc($this->userWith());
        foreach ([
            ['get', '/api/v1/dms/dokumen'], ['get', "/api/v1/dms/dokumen/{$doc->id}"], ['get', "/api/v1/dms/dokumen/{$doc->id}/versi"],
            ['post', "/api/v1/dms/dokumen/{$doc->id}/pautan"], ['get', '/api/v1/dms/kategori'], ['get', '/api/v1/dms/konfigurasi'],
            ['delete', "/api/v1/dms/dokumen/{$doc->id}"], ['post', "/api/v1/dms/dokumen/{$doc->id}/arkib"], ['get', '/api/v1/dms/pelupusan'],
        ] as [$method, $uri]) {
            $this->actingAs($nobody)->json($method, $uri)->assertForbidden();
        }
        $this->upload($nobody, TestFiles::pdf())->assertForbidden();
        $this->actingAs($nobody)->getJson('/api/v1/dms/dokumen/not-a-uuid')->assertForbidden();
        $this->actingAs($this->userWith(['dms.dokumen.lihat']))->getJson('/api/v1/dms/dokumen/not-a-uuid')->assertNotFound();
    }

    public function test_SRS_DMS_01_single_repository_for_every_module(): void
    {
        $user = $this->userWith();
        foreach (['hmt' => 'hak_milik', 'pjk' => 'kontrak_pajakan', 'cuk' => 'cukai', 'adu' => 'aduan'] as $module => $entity) {
            $this->uploadDoc($user, TestFiles::pdf(), ['modul_sumber' => $module, 'jenis_entiti_induk' => $entity, 'id_rekod_induk' => '1']);
        }

        $this->assertSame(4, DokumenVersi::query()->count());
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen')->assertJsonPath('meta.total', 4);
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?modul_sumber=pjk')->assertJsonPath('meta.total', 1);
    }

    private function pdfText(string $pdf): ?string
    {
        $bin = trim((string) shell_exec('command -v pdftotext'));
        if ($bin === '') {
            return null;
        }
        $path = tempnam(sys_get_temp_dir(), 'wm');
        file_put_contents($path, $pdf);
        $text = shell_exec(escapeshellarg($bin).' -q '.escapeshellarg($path).' -');
        @unlink($path);

        return (string) $text;
    }

    /** Minimal PDF 1.5 whose cross-reference table is a compressed XRef stream. */
    private function pdfWithXrefStream(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 200] /Contents 4 0 R /Resources << >> >>',
            4 => "<< /Length 0 >>\nstream\n\nendstream",
        ];
        $out = "%PDF-1.5\n";
        $offsets = [];
        foreach ($objects as $n => $body) {
            $offsets[$n] = strlen($out);
            $out .= "{$n} 0 obj\n{$body}\nendobj\n";
        }
        $xrefOffset = strlen($out);
        $rows = pack('CNn', 0, 0, 65535);
        foreach ($offsets as $offset) {
            $rows .= pack('CNn', 1, $offset, 0);
        }
        $rows .= pack('CNn', 1, $xrefOffset, 0);
        $data = gzcompress($rows);
        $out .= "5 0 obj\n<< /Type /XRef /Size 6 /W [1 4 2] /Root 1 0 R /Filter /FlateDecode /Length ".strlen($data)." >>\nstream\n{$data}\nendstream\nendobj\n";

        return $out."startxref\n{$xrefOffset}\n%%EOF\n";
    }
}
