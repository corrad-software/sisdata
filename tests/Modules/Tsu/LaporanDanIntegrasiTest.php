<?php

namespace Tests\Modules\Tsu;

use App\Modules\Tsu\Kpi\LewatSasaran;
use App\Modules\Tsu\Kpi\PurataTempoh;
use App\Modules\Tsu\Kpi\UrusanAktif;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Models\LantikanJurukur;
use App\Modules\Tsu\Models\PanelJurukur;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Services\UrusanSearchProvider;
use App\Modules\Tsu\Support\AliranTsu;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\Engine;
use App\Modules\Wfe\Support\WfeException;
use Database\Seeders\TsuAliranSeeder;
use Illuminate\Support\Facades\DB;

class LaporanDanIntegrasiTest extends TsuTestCase
{
    public function test_SRS_TSU_16_accepted_resurvey_is_proposed_to_mod_pro_for_verification_not_written(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $lot = $this->lot(keluasan: 50000);
        $u = $this->daftar($officer, 'hak_milik_kekal', [$lot]);
        $this->sampaiKe($u, 'pengesahan_hasil_ukur', $officer, $officer);
        [$lat, $lng] = self::PUSAT_PETALING;
        $h = $this->actingAs($this->user(self::GIS))->postJson('/api/v1/tsu/urusan/'.$u->id.'/hasil-ukur', ['format_fail' => 'geojson', 'sistem_koordinat' => 4326, 'geojson' => $this->petak($lat, $lng, 0.00098), 'lot_id' => $lot, 'keluasan_dokumen' => 47000])->json('data.0.id');
        $this->actingAs($this->user(self::GIS))->postJson('/api/v1/tsu/hasil-ukur/'.$h.'/sahkan', ['status_pengesahan' => 'diterima'])->assertOk();
        $this->selesaikan($u, $officer, $officer);

        $spatial = DB::table('data_spatial')->where('lot_id', $lot)->orderBy('versi')->get();
        $this->assertCount(2, $spatial);
        $this->assertFalse((bool) $spatial[0]->semasa);
        $this->assertSame(['belum_disahkan', true, 'MOD-TSU', 2], [$spatial[1]->status_pengesahan, (bool) $spatial[1]->semasa, $spatial[1]->sumber_sistem, (int) $spatial[1]->versi]);
        $this->assertSame('50000.0000', (string) DB::table('lot_tanah')->where('id', $lot)->value('keluasan'), 'keluasan induk TIDAK ditulis terus');
        $v = DB::table('versi_rekod_induk')->where('id_rekod', $lot)->where('status_pengesahan', 'menunggu')->sole();
        $this->assertTrue((bool) $v->kritikal);
        $this->assertCount(1, $this->workflow->of('pro.perubahan_kritikal'), 'pengesahan pegawai kedua MOD-PRO');
        $this->assertSame((int) $spatial[1]->id, (int) DB::table('hasil_ukur')->where('id', $h)->value('id_spatial_dicadang'));
    }

    public function test_SRS_TSU_26_works_report_by_status_district_surveyor_cost_and_duration_with_export(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $a = $this->daftar($officer, 'pelupusan', [$this->lot()], ['kaedah_pelupusan' => 'jualan', 'tarikh_mula' => today()->subDays(40)->toDateString()]);
        $b = $this->daftar($officer, 'tukar_syarat', [$this->lot('100901')]);
        $j = PanelJurukur::query()->create(['nama_firma' => 'Firma Demarkasi', 'no_lesen_jurukur' => 'JTB-77', 'nama_jurukur_bertanggungjawab' => 'J', 'status_panel' => 'aktif', 'tarikh_tamat_lesen' => today()->addYear()]);
        (new LantikanJurukur)->forceFill(['urusan_id' => $b->id, 'jurukur_id' => $j->id, 'no_surat_setuju_terima' => 'S', 'nilai_kontrak' => '5000.00', 'tarikh_mula' => today(), 'tempoh_siap_hari' => 30, 'tarikh_jangka_siap' => today()->addDays(30), 'skop_kerja' => 'Demarkasi', 'status' => 'aktif'])->save();
        KosUrusan::query()->create(['urusan_id' => $a->id, 'kategori_kos' => 'fi_permohonan', 'amaun' => '120.50', 'status_bayaran' => 'dibayar']);
        KosUrusan::query()->create(['urusan_id' => $a->id, 'kategori_kos' => 'kos_lain', 'amaun' => '79.50', 'status_bayaran' => 'belum_bayar']);

        $viewer = $this->user(['tsu.laporan.lihat', 'tsu.laporan.eksport']);
        $rows = $this->actingAs($viewer)->getJson('/api/v1/tsu/laporan/kerja')->assertOk()->json('data');
        $this->assertCount(2, $rows);
        $ra = collect($rows)->firstWhere('id', $a->id);
        $this->assertSame(['200.00', 40, 'Pelupusan tanah'], [$ra['jumlah_kos'], $ra['tempoh_hari'], $ra['jenis_nama']]);
        $this->assertSame([$b->id], array_column($this->actingAs($viewer)->getJson('/api/v1/tsu/laporan/kerja?jurukur_id='.$j->id)->json('data'), 'id'));
        $this->assertSame([$b->id], array_column($this->actingAs($viewer)->getJson('/api/v1/tsu/laporan/kerja?kod_daerah=1009')->json('data'), 'id'));
        $this->assertSame([$a->id], array_column($this->actingAs($viewer)->getJson('/api/v1/tsu/laporan/kerja?jenis_urusan=pelupusan&status=aktif')->json('data'), 'id'));
        $this->assertSame([], $this->actingAs($this->user(['tsu.laporan.lihat'], ['1001']))->getJson('/api/v1/tsu/laporan/kerja')->json('data'), 'skop daerah');

        $csv = $this->actingAs($viewer)->get('/api/v1/tsu/laporan/kerja/eksport')->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('No. rujukan', $body);
        $this->assertStringContainsString($a->no_rujukan_mais, $body);
        $this->assertAudited('eksport', ['modul' => 'tsu']);
        $this->actingAs($this->user(['tsu.laporan.lihat']))->get('/api/v1/tsu/laporan/kerja/eksport')->assertForbidden();
    }

    public function test_SRS_TSU_27_management_report_active_by_type_district_stage_average_duration_annual_cost_external_backlog(): void
    {
        $officer = $this->user(self::PEGAWAI);
        $u1 = $this->daftar($officer, 'tukar_syarat', [$this->lot()]);
        $u2 = $this->daftar($officer, 'tukar_syarat', [$this->lot('100901')]);
        $u3 = $this->daftar($officer, 'hak_milik_kekal', [$this->lot()]);
        // u3 finished in 30 days; u2 stuck at the Land Office past target.
        $u3->forceFill(['status' => 'selesai', 'tarikh_mula' => today()->subDays(30), 'tarikh_siap' => today()])->save();
        UrusanPeringkat::query()->where('urusan_id', $u2->id)->where('no_urutan', 1)->update(['pihak_bertanggungjawab' => 'pejabat_tanah', 'tarikh_sasaran' => today()->subDays(9)]);
        KosUrusan::query()->create(['urusan_id' => $u1->id, 'kategori_kos' => 'premium', 'amaun' => '1000.10', 'status_bayaran' => 'dibayar', 'tarikh_bayaran' => '2025-06-01']);
        KosUrusan::query()->create(['urusan_id' => $u1->id, 'kategori_kos' => 'premium', 'amaun' => '0.90', 'status_bayaran' => 'dibayar', 'tarikh_bayaran' => '2025-07-01']);
        KosUrusan::query()->create(['urusan_id' => $u2->id, 'kategori_kos' => 'fi_pelan', 'amaun' => '50', 'status_bayaran' => 'belum_bayar', 'tarikh_invois' => today()]);

        $d = $this->actingAs($this->user(['tsu.laporan.lihat']))->getJson('/api/v1/tsu/laporan/ringkasan')->assertOk()->json('data');
        $this->assertSame(2, collect($d['aktif_mengikut_jenis'])->firstWhere('kod', 'tukar_syarat')['nilai']);
        $this->assertEqualsCanonicalizing([['kod' => '1008', 'nama' => 'Petaling', 'nilai' => 1], ['kod' => '1009', 'nama' => 'Sepang', 'nilai' => 1]], $d['aktif_mengikut_daerah']);
        $this->assertSame(2, collect($d['aktif_mengikut_peringkat'])->firstWhere('peringkat', 'Penyediaan pelan dan dokumen')['nilai']);
        $this->assertEquals(30, collect($d['purata_tempoh'])->firstWhere('kod', 'hak_milik_kekal')['purata_hari']);
        $y2025 = collect($d['kos_tahunan'])->firstWhere('tahun', 2025);
        $this->assertSame(['1001.00', '1001.00'], [$y2025['jumlah'], $y2025['premium']]);
        $this->assertCount(1, $d['tertunggak_pihak_luar']);
        $this->assertSame([$u2->no_rujukan_mais, 9, 'Pejabat Tanah Daerah'], [$d['tertunggak_pihak_luar'][0]['no_rujukan_mais'], $d['tertunggak_pihak_luar'][0]['hari_lewat'], $d['tertunggak_pihak_luar'][0]['pihak']]);

        // Dashboard KPIs (MOD-DSH) — scoped to the viewer.
        $viewer = $this->user(['tsu.laporan.lihat']);
        $this->assertSame(2, app(UrusanAktif::class)->compute($viewer)['nilai']);
        $this->assertSame(1, app(UrusanAktif::class)->compute($this->user(['tsu.laporan.lihat'], ['1009']))['nilai']);
        $this->assertEquals(30, app(PurataTempoh::class)->compute($viewer)['nilai']);
        $late = app(LewatSasaran::class)->compute($viewer);
        $this->assertSame(1, $late['nilai']);
        $this->assertSame(1, collect($late['pecahan'])->firstWhere('kod', 'pihak:luar')['nilai']);
        // Global search.
        $hits = app(UrusanSearchProvider::class)->search($u1->no_rujukan_mais, $viewer);
        $this->assertSame([], $hits, 'tiada kebenaran urusan.lihat');
        $this->assertSame($u1->id, app(UrusanSearchProvider::class)->search($u1->no_rujukan_mais, $officer)[0]['id']);
    }

    public function test_sample_command_generates_flagged_synthetic_urusan_without_touching_master_lots(): void
    {
        foreach (range(1, 12) as $i) {
            $this->lot($i % 2 ? '100803' : '100901', [3.1 + $i * 0.01, 101.6]);
        }
        $this->user(self::PEGAWAI);
        $before = DB::table('lot_tanah')->count();
        $this->artisan('tsu:sample', ['--count' => 10, '--seed' => 5])->assertSuccessful();
        $this->assertSame(10, UrusanTanah::query()->withoutGlobalScope('access')->where('sintetik', true)->count());
        $this->assertSame(0, UrusanTanah::query()->withoutGlobalScope('access')->where('sintetik', false)->count());
        $this->assertSame($before, DB::table('lot_tanah')->count(), 'tiada lot baharu dicipta');
        $this->assertSame(0, DB::table('lot_tanah')->where('status_lot', 'arkib')->count(), 'tiada lot diarkib');
        $this->assertGreaterThan(1, UrusanTanah::query()->withoutGlobalScope('access')->distinct()->count('jenis_urusan'));
        $this->assertTrue(PanelJurukur::query()->where('sintetik', true)->exists());
        $this->assertSame([], $this->notifier->sent);
    }

    public function test_SRS_TSU_05_seeded_wfe_flow_requires_mfa_at_highest_disposal_stage_and_blocks_self_approval(): void
    {
        $this->app->forgetInstance(WorkflowEngine::class);
        $this->app->bind(WorkflowEngine::class, Engine::class);
        $officer = $this->user([...self::PEGAWAI, 'wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $this->seed(TsuAliranSeeder::class);
        $this->seed(TsuAliranSeeder::class); // idempotent
        $this->assertSame(4, DB::table('wf_definisi')->where('modul', 'tsu')->count());

        $kb = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $top = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        Kumpulan::query()->where('kod', 'tsu_ketua_bahagian')->firstOrFail()->ahli()->sync([$officer->id, $kb->id]);
        Kumpulan::query()->where('kod', 'tsu_pengurusan')->firstOrFail()->ahli()->sync([$top->id]);

        $u = $this->daftar($officer, 'pelupusan', [$this->lot()], ['kaedah_pelupusan' => 'jualan', 'justifikasi' => 'Tidak ekonomik; hasil jualan untuk pembangunan wakaf am.']);
        $this->sampaiKe($u, 'kelulusan_pelupusan', $officer, $kb);
        $stage = $this->semasa($u);
        $instance = $this->actingAs($officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertOk()->json('data.id_aliran_kerja');
        $engine = app(Engine::class);
        $task = fn () => Tugasan::query()->where('id_instans', $instance)->where('status', 'menunggu')->firstOrFail();
        try {
            $engine->decide($task(), $officer, 'lulus', 'Saya lulus sendiri');
            $this->fail('Pendaftar meluluskan sendiri');
        } catch (WfeException $e) {
            $this->assertSame('pengasingan_tugas', $e->errorCode);
        }
        $engine->decide($task(), $kb, 'lulus', 'Disemak dan disokong.');
        try {
            $engine->decide($task(), $top, 'lulus', 'Lulus');
            $this->fail('Kelulusan tertinggi tanpa MFA');
        } catch (WfeException $e) {
            $this->assertSame('mfa_diperlukan', $e->errorCode);
        }
        $this->assertSame('menunggu_kelulusan', $stage->refresh()->status);
        $engine->decide($task(), $top, 'lulus', 'Diluluskan Mesyuarat Majlis.', ['mfa_at' => now()]);
        $this->assertSame('selesai', $stage->refresh()->status);
        $this->assertSame('permohonan_ptd', $this->semasa($u)->kod);
        $this->assertTrue((bool) DB::table('urusan_keputusan')->where('urusan_id', $u->id)->where('kod_peringkat_wfe', AliranTsu::PERINGKAT_TERTINGGI)->value('indikator_mfa'));
    }
}
