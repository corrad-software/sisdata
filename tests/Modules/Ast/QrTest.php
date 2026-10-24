<?php

namespace Tests\Modules\Ast;

use App\Modules\Ast\Models\AsetQr;
use App\Modules\Ast\Models\AsetQrLogImbasan;
use App\Modules\Ast\Support\KodQr;
use Illuminate\Support\Facades\DB;

class QrTest extends AstTestCase
{
    public function test_SRS_AST_07_batch_label_print_of_at_least_20_assets_with_template(): void
    {
        $u = $this->unitAset();
        $lot = $this->lot();
        $ids = [];
        for ($i = 0; $i < 21; $i++) {
            $ids[] = $this->daftar($u, $lot, ['nama_aset' => 'Aset '.$i, 'kos_perolehan' => 1000])->id;
        }
        foreach (['kecil', 'sederhana', 'besar'] as $t) {
            $r = $this->actingAs($u)->post('/api/v1/ast/label', ['id_aset' => $ids, 'templat' => $t], ['Accept' => 'application/json'])->assertOk();
            $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', (string) $r->getContent());
        }
        $this->assertSame(21, AsetQr::query()->where('bilangan_cetakan', 3)->count());
        $this->assertAudited('cetak', ['modul' => 'ast', 'nama_entiti' => 'aset_qr']);
        // Single label too.
        $this->actingAs($u)->post('/api/v1/ast/label', ['id_aset' => [$ids[0]], 'templat' => 'besar'], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($this->user(['ast.aset.lihat']))->postJson('/api/v1/ast/label', ['id_aset' => $ids, 'templat' => 'kecil'])->assertForbidden();
    }

    public function test_SRS_AST_07_label_contains_scannable_qr_of_public_link(): void
    {
        $qr = app(KodQr::class);
        $url = $qr->pautan('ABCDEFGHJK12');
        $m = $qr->matriks($url);
        $this->assertGreaterThanOrEqual(25, count($m));
        // Finder patterns (7×7) in three corners — a valid QR symbol.
        foreach ([[0, 0], [0, count($m) - 7], [count($m) - 7, 0]] as [$y, $x]) {
            $this->assertTrue($m[$y][$x] && $m[$y][$x + 6] && $m[$y + 6][$x] && $m[$y + 3][$x + 3]);
            $this->assertFalse($m[$y + 1][$x + 1]);
        }
        $this->assertStringStartsWith("\x89PNG", $qr->png($url, 2));
        $this->assertStringContainsString('<svg', $qr->svg($url));
    }

    public function test_SRS_AST_08_09_public_scan_without_account_shows_minimum_identification_only(): void
    {
        $a = $this->daftar($this->unitAset(), $this->lot(), ['kos_perolehan' => 345678]);
        $r = $this->tanpaAkaun()->postJson('/api/v1/ast/awam/qr/'.$a->qr_ref.'/imbas')->assertOk();
        $r->assertJsonPath('data.paparan', 'minimum')->assertJsonPath('data.nama_aset', 'Surau Ujian')->assertJsonPath('data.no_rujukan_aset', $a->no_rujukan_aset)
            ->assertJsonPath('data.pemilik', 'Majlis Agama Islam Selangor (MAIS)');
        $body = (string) $r->getContent();
        $this->assertStringNotContainsString('345678', $body, 'value must never reach an unauthenticated scanner');
        $this->assertStringNotContainsString($a->id, $body, 'no internal id');
        $this->assertArrayNotHasKey('pegawai', $r->json('data'));
        $this->assertArrayNotHasKey('pautan', $r->json('data'));
        // Unknown QR.
        $this->postJson('/api/v1/ast/awam/qr/ZZZZZZZZZZZZ/imbas')->assertNotFound();
    }

    public function test_SRS_AST_09_registered_scanner_in_scope_gets_full_profile_actions(): void
    {
        $owner = $this->unitAset();
        $a = $this->daftar($owner, $this->lot());
        $this->actingAs($this->user(['ast.aset.lihat', 'ast.lapangan.kemas_kini', 'ast.pemeriksaan.cipta'], ['1008']))->postJson('/api/v1/ast/awam/qr/'.$a->qr_ref.'/imbas')->assertOk()
            ->assertJsonPath('data.paparan', 'penuh')->assertJsonPath('data.pautan', '/admin/ast/aset/'.$a->id)
            ->assertJsonPath('data.tindakan', ['lihat_profil', 'periksa_lapangan', 'rekod_pemeriksaan']);
        // Registered but out of scope / without permission → minimum.
        $this->actingAs($this->user(['ast.aset.lihat'], ['1009']))->postJson('/api/v1/ast/awam/qr/'.$a->qr_ref.'/imbas')->assertOk()->assertJsonPath('data.paparan', 'minimum');
        $this->actingAs($this->user([]))->postJson('/api/v1/ast/awam/qr/'.$a->qr_ref.'/imbas')->assertOk()->assertJsonPath('data.paparan', 'minimum');
    }

    public function test_SRS_AST_10_every_scan_is_logged_with_time_identity_and_device_location(): void
    {
        $owner = $this->unitAset();
        $a = $this->daftar($owner, $this->lot());
        $this->tanpaAkaun()->postJson('/api/v1/ast/awam/qr/'.$a->qr_ref.'/imbas', ['latitud' => 3.1, 'longitud' => 101.6, 'ketepatan_m' => 8])->assertOk();
        $scanner = $this->user(['ast.aset.lihat']);
        $this->actingAs($scanner)->postJson('/api/v1/ast/awam/qr/'.$a->qr_ref.'/imbas')->assertOk();

        $logs = AsetQrLogImbasan::query()->where('id_aset', $a->id)->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertNull($logs[0]->id_pengguna);
        $this->assertSame('3.1000000', $logs[0]->latitud_peranti);
        $this->assertSame('minimum', $logs[0]->jenis_paparan);
        $this->assertSame($scanner->id, $logs[1]->id_pengguna);
        $this->assertSame('penuh', $logs[1]->jenis_paparan);
        $this->assertTrue($logs[1]->cap_masa->isToday());
        $this->actingAs($owner)->getJson('/api/v1/ast/aset/'.$a->id.'/imbasan')->assertOk()->assertJsonPath('meta.total', 2);
        $this->actingAs($this->user(['ast.aset.lihat']))->getJson('/api/v1/ast/aset/'.$a->id.'/imbasan')->assertForbidden();
    }

    public function test_SRS_AST_06_qr_can_be_deactivated_individually(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $this->actingAs($u)->postJson('/api/v1/ast/qr/'.$a->qr_ref.'/status', ['status_aktif' => false, 'sebab' => 'Label hilang'])->assertOk();
        $this->tanpaAkaun()->postJson('/api/v1/ast/awam/qr/'.$a->qr_ref.'/imbas')->assertOk()->assertJsonPath('data.paparan', 'tidak_aktif');
        $this->assertSame('tidak_aktif', DB::table('aset_qr_log_imbasan')->orderByDesc('id')->value('jenis_paparan'));
        $this->actingAs($this->user(['ast.aset.lihat', 'ast.aset.kemas_kini']))->postJson('/api/v1/ast/qr/'.$a->qr_ref.'/status', ['status_aktif' => true])->assertForbidden();
    }
}
