<?php

namespace Tests\Modules\Pro;

use App\Modules\Pro\Models\CadanganDuplikasi;
use App\Modules\Pro\Models\DataSpatial;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\RekodArkib;
use App\Modules\Pro\Models\StewardshipData;
use App\Modules\Pro\Models\TugasanKualitiData;
use App\Modules\Pro\Services\PengurusArkib;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GovernanceTest extends ProTestCase
{
    // ---- SRS-PRO-11 / PRO-12 ------------------------------------------------------

    public function test_SRS_PRO_11_stewardship_register_covers_every_main_data_component(): void
    {
        $user = $this->officer(['pro.stewardship.lihat']);
        $rows = collect($this->actingAs($user)->getJson('/api/v1/pro/stewardship')->assertOk()->json('data'));

        foreach (['Lot tanah dan geometri', 'Hak milik dan pemerolehan', 'Wakaf', 'Baitulmal', 'Pajakan dan sewaan', 'Cukai dan bayaran', 'Kes perundangan', 'Data spatial', 'Pengguna dan peranan'] as $component) {
            $entry = $rows->firstWhere('komponen_data', $component);
            $this->assertNotNull($entry, $component);
            $this->assertNotEmpty($entry['jawatan_data_owner']);
            $this->assertNotEmpty($entry['bahagian_data_steward']);
            $this->assertNotEmpty($entry['peranan_tanggungjawab']);
        }

        $this->actingAs($user)->postJson('/api/v1/pro/stewardship', ['komponen_data' => 'X'])->assertForbidden();
        $admin = $this->officer(['pro.stewardship.lihat', 'pro.stewardship.kemas_kini']);
        $steward = $this->officer([]);
        $id = $rows->firstWhere('komponen_data', 'Wakaf')['id'];
        $this->actingAs($admin)->putJson("/api/v1/pro/stewardship/{$id}", ['id_data_steward' => $steward->id])->assertOk()->assertJsonPath('data.id_data_steward', $steward->id);
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'daftar_stewardship_data', 'id_rekod' => $id]);
        $this->assertStringContainsString('Komponen Data', $this->actingAs($admin)->get('/api/v1/pro/stewardship/eksport?format=csv')->assertOk()->streamedContent());
    }

    public function test_SRS_PRO_12_quality_failures_go_to_the_correct_steward_with_notification_and_corrective_action(): void
    {
        $wakafSteward = $this->officer(['pro.kualiti.lihat', 'pro.kualiti.kemas_kini']);
        $baitulmalSteward = $this->officer(['pro.kualiti.lihat']);
        StewardshipData::query()->where('komponen_data', 'Wakaf')->update(['id_data_steward' => $wakafSteward->id]);
        StewardshipData::query()->where('komponen_data', 'Baitulmal')->update(['id_data_steward' => $baitulmalSteward->id]);
        $wakaf = $this->makeLot(['kategori_guna_tanah' => null]);
        $baitulmal = $this->makeLot(['no_lot' => 'Lot 2', 'bahagian_pemilik' => 'baitulmal', 'kategori_guna_tanah' => null]);

        foreach ([$wakaf, $baitulmal] as $lot) {
            $this->actingAs($this->officer())->postJson("/api/v1/pro/lot/{$lot->id}/semak-kualiti")->assertOk();
        }

        $this->assertSame([$wakafSteward->id], TugasanKualitiData::query()->where('id_rekod', $wakaf->id)->pluck('id_data_steward')->unique()->values()->all());
        $this->assertSame([$baitulmalSteward->id], TugasanKualitiData::query()->where('id_rekod', $baitulmal->id)->pluck('id_data_steward')->unique()->values()->all());
        $this->assertSame([$wakafSteward->id], $this->notifier->of('pro.kualiti_data_gagal')[0]['recipients']);

        // Re-running does not duplicate open tasks.
        $this->actingAs($this->officer())->postJson("/api/v1/pro/lot/{$wakaf->id}/semak-kualiti")->assertJsonPath('data.tugasan_baharu', 0);

        // The lot's own steward wins over the register.
        $own = $this->officer([]);
        $lot = $this->makeLot(['no_lot' => 'Lot 3', 'kategori_guna_tanah' => null, 'id_data_steward' => $own->id]);
        $this->actingAs($this->officer())->postJson("/api/v1/pro/lot/{$lot->id}/semak-kualiti")->assertOk();
        $this->assertSame($own->id, TugasanKualitiData::query()->where('id_rekod', $lot->id)->value('id_data_steward'));

        $mine = $this->actingAs($wakafSteward)->getJson('/api/v1/pro/kualiti/tugasan?saya=1&status=terbuka')->assertOk()->json('data');
        $this->assertNotEmpty($mine);
        $this->assertSame('/admin/pro/lot/'.$wakaf->id, $mine[0]['pautan']);
        $this->actingAs($wakafSteward)->postJson("/api/v1/pro/kualiti/tugasan/{$mine[0]['id']}/selesai", ['tindakan_pembetulan' => 'Kategori disahkan dengan PTD'])->assertOk();
        $task = TugasanKualitiData::query()->findOrFail($mine[0]['id']);
        $this->assertSame(['selesai', 'Kategori disahkan dengan PTD', $wakafSteward->id], [$task->status, $task->tindakan_pembetulan, $task->diselesaikan_oleh]);
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'tugasan_kualiti_data', 'id_rekod' => $task->id]);
    }

    // ---- SRS-PRO-24 / DAT-09 ------------------------------------------------------

    public function test_SRS_PRO_24_quality_report_categories_with_filters_and_export(): void
    {
        $user = $this->officer(['pro.lot.lihat', 'pro.kualiti.lihat']);
        $incomplete = $this->makeLot(['kategori_guna_tanah' => null]);
        $verified = $this->makeLot(['no_lot' => 'Lot 2', 'bahagian_pemilik' => 'baitulmal']);
        $unverified = $this->makeLot(['no_lot' => 'Lot 3', 'kod_mukim' => '100101']);
        $sid = $this->addGeometry($verified, $this->square(101.60, 3.10));
        DataSpatial::query()->whereKey($sid)->update(['status_pengesahan' => 'disahkan_ptg']);
        $this->addGeometry($unverified, $this->square(101.40, 3.00));
        DB::table('cukai')->insert(['id' => (string) Str::uuid(), 'lot_id' => $verified->id, 'jenis' => 'tanah', 'no_bil' => 'B1', 'jumlah' => 120, 'tarikh_akhir' => now()->subMonth()->toDateString(), 'status_bayaran' => 'belum_bayar', 'created_at' => now(), 'updated_at' => now()]);

        $report = $this->actingAs($user)->getJson('/api/v1/pro/kualiti/laporan')->assertOk();
        $summary = collect($report->json('data.ringkasan'))->keyBy('kod');
        foreach (['tidak_lengkap', 'duplikasi', 'dokumen_tamat', 'cukai_tertunggak', 'geometri_belum_disahkan', 'tanpa_geometri', 'rujukan_bercanggah', 'rekod_yatim'] as $code) {
            $this->assertArrayHasKey($code, $summary->all());
        }
        $this->assertSame(1, $summary['tanpa_geometri']['bilangan']);
        $this->assertSame(1, $summary['geometri_belum_disahkan']['bilangan']);
        $this->assertSame(1, $summary['cukai_tertunggak']['bilangan']);
        $this->assertSame($incomplete->id, $report->json('data.butiran.tanpa_geometri.0.lot_id'));

        // Filters: daerah, bahagian, entity.
        $klang = collect($this->actingAs($user)->getJson('/api/v1/pro/kualiti/laporan?kod_daerah=1001')->json('data.ringkasan'))->keyBy('kod');
        $this->assertSame(0, $klang['tanpa_geometri']['bilangan']);
        $this->assertSame(1, $klang['geometri_belum_disahkan']['bilangan']);
        $bm = collect($this->actingAs($user)->getJson('/api/v1/pro/kualiti/laporan?bahagian_pemilik=baitulmal')->json('data.ringkasan'))->keyBy('kod');
        $this->assertSame(1, $bm['cukai_tertunggak']['bilangan']);
        $this->assertSame(0, $bm['tanpa_geometri']['bilangan']);
        $spatialOnly = $this->actingAs($user)->getJson('/api/v1/pro/kualiti/laporan?entiti=data_spatial')->json('data.ringkasan');
        $this->assertEqualsCanonicalizing(['geometri_belum_disahkan', 'tanpa_geometri', 'rekod_yatim'], array_column($spatialOnly, 'kod'));

        // Out-of-scope lots never appear.
        $outsider = $this->officer(['pro.lot.lihat', 'pro.kualiti.lihat'], ['1009']);
        $this->assertSame(0, array_sum(array_column($this->actingAs($outsider)->getJson('/api/v1/pro/kualiti/laporan')->json('data.ringkasan'), 'bilangan')));

        $csv = $this->actingAs($user)->get('/api/v1/pro/kualiti/laporan/eksport?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Lot tanpa geometri', $csv);
    }

    public function test_SRS_DAT_09_scheduled_report_routes_problem_records_to_stewards(): void
    {
        $steward = $this->officer(['pro.kualiti.lihat']);
        StewardshipData::query()->where('komponen_data', 'Wakaf')->update(['id_data_steward' => $steward->id]);
        $lot = $this->makeLot();

        $this->artisan('pro:laporan-kualiti')->assertSuccessful();

        // Incomplete master data → the Wakaf steward (lot is a wakaf lot) …
        $task = TugasanKualitiData::query()->where('id_rekod', $lot->id)->where('kod_isu', 'laporan.tidak_lengkap')->firstOrFail();
        $this->assertSame([$steward->id, 'laporan_berjadual'], [$task->id_data_steward, $task->sumber]);
        // … while a missing geometry is the GIS specialist's (register entry "Data spatial").
        $spatial = TugasanKualitiData::query()->where('id_rekod', $lot->id)->where('kod_isu', 'laporan.tanpa_geometri')->firstOrFail();
        $this->assertSame('Data spatial', StewardshipData::query()->findOrFail($spatial->id_stewardship)->komponen_data);
    }

    // ---- SRS-PRO-20 / DAT-02 ------------------------------------------------------

    public function test_SRS_DAT_02_known_duplicate_pairs_are_all_found_and_never_auto_merged(): void
    {
        // Pair 1: same number written differently across neighbouring mukims + same area + overlapping polygon.
        $a = $this->makeLot(['no_lot' => 'Lot 7001', 'kod_mukim' => '100803', 'keluasan' => 1.25]);
        $b = $this->makeLot(['no_lot' => 'Lot 7001', 'kod_mukim' => '100802', 'keluasan' => 1.25]);
        $this->addGeometry($a, $this->square(101.60, 3.10, 0.001));
        $this->addGeometry($b, $this->square(101.6001, 3.1001, 0.001));
        // Pair 2: LOT vs PT with the same digits in the same mukim and same area.
        $c = $this->makeLot(['no_lot' => 'Lot 8888', 'keluasan' => 500, 'unit_keluasan' => 'm2']);
        $d = $this->makeLot(['no_lot' => 'PT 8888', 'keluasan' => 500, 'unit_keluasan' => 'm2']);
        // Not duplicates: neighbour numbers, different areas, no geometry overlap.
        $this->makeLot(['no_lot' => 'Lot 8889', 'keluasan' => 900, 'unit_keluasan' => 'm2']);
        $this->makeLot(['no_lot' => 'Lot 1', 'kod_mukim' => '100101']);

        $this->actingAs($this->officer(['pro.duplikasi.kemas_kini']))->postJson('/api/v1/pro/duplikasi/kesan')->assertOk();

        $pairs = CadanganDuplikasi::query()->get()->map(fn ($p) => collect([$p->id_rekod_a, $p->id_rekod_b])->sort()->values()->all())->all();
        $this->assertContains(collect([$a->id, $b->id])->sort()->values()->all(), $pairs);
        $this->assertContains(collect([$c->id, $d->id])->sort()->values()->all(), $pairs);
        $this->assertCount(2, $pairs);
        $this->assertSame(0, LotTanah::query()->where('status_lot', 'digabung')->count(), 'detection never merges');

        $suggestion = CadanganDuplikasi::query()->whereIn('id_rekod_a', [$a->id, $b->id])->firstOrFail();
        $this->assertGreaterThanOrEqual(80, (float) $suggestion->skor_padanan);
        $this->assertEquals(1, $suggestion->kriteria_padanan['padanan_no_lot']);
        $this->assertGreaterThan(50, $suggestion->kriteria_padanan['pertindihan_geometri_peratus']);
    }

    public function test_SRS_PRO_20_matching_criteria_are_configurable(): void
    {
        $this->makeLot(['no_lot' => 'Lot 8888', 'keluasan' => 500, 'unit_keluasan' => 'm2']);
        $this->makeLot(['no_lot' => 'PT 8888', 'keluasan' => 500, 'unit_keluasan' => 'm2']);
        $this->params->values['pro.kriteria_duplikasi'] = ['ambang_skor' => 95];

        $this->artisan('pro:kesan-duplikasi')->assertSuccessful();
        $this->assertSame(0, CadanganDuplikasi::query()->count());
        $this->assertEquals(95, $this->actingAs($this->officer(['pro.duplikasi.lihat']))->getJson('/api/v1/pro/duplikasi/kriteria')->json('data.ambang_skor'));
    }

    public function test_SRS_PRO_20_steward_confirms_merges_or_rejects_and_decision_is_audited(): void
    {
        $steward = $this->officer(['pro.lot.lihat', 'pro.duplikasi.lihat', 'pro.duplikasi.kemas_kini']);
        $keep = $this->makeLot(['no_lot' => 'Lot 8888', 'keluasan' => 500, 'unit_keluasan' => 'm2', 'kategori_guna_tanah' => null]);
        $drop = $this->makeLot(['no_lot' => 'PT 8888', 'keluasan' => 500, 'unit_keluasan' => 'm2', 'zoning' => 'Pertanian']);
        $this->addGeometry($drop, $this->square());
        $this->artisan('pro:kesan-duplikasi')->assertSuccessful();

        $queue = $this->actingAs($steward)->getJson('/api/v1/pro/duplikasi')->assertOk();
        $queue->assertJsonCount(1, 'data')->assertJsonPath('data.0.lot_a.no_lot', 'Lot 8888');
        $id = $queue->json('data.0.id');

        $this->actingAs($this->officer(['pro.duplikasi.lihat']))->postJson("/api/v1/pro/duplikasi/{$id}/keputusan", ['keputusan' => 'gabung'])->assertForbidden();
        $this->actingAs($steward)->postJson("/api/v1/pro/duplikasi/{$id}/keputusan", ['keputusan' => 'gabung', 'id_rekod_kekal' => $keep->id, 'catatan' => 'Rekod pendua sistem lama'])
            ->assertOk()->assertJsonPath('data.keputusan', 'gabung');

        $drop->refresh();
        $this->assertSame(['digabung', $keep->id], [$drop->status_lot, $drop->id_lot_gabung]);
        $this->assertSame('Pertanian', $keep->refresh()->zoning, 'gaps filled from the duplicate');
        $this->assertSame($keep->id, DataSpatial::query()->where('semasa', true)->value('lot_id'), 'geometry moved to the master');
        $this->assertAudited('keputusan_duplikasi', ['nama_entiti' => 'cadangan_duplikasi', 'id_rekod' => $id, 'id_pengguna' => $steward->id]);
        $this->assertSame($steward->id, CadanganDuplikasi::query()->findOrFail($id)->id_data_steward);

        $this->actingAs($steward)->postJson("/api/v1/pro/duplikasi/{$id}/keputusan", ['keputusan' => 'tolak'])->assertStatus(422);
        $this->actingAs($steward)->getJson('/api/v1/pro/duplikasi?keputusan=gabung')->assertJsonCount(1, 'data');

        // A rejected suggestion stays recorded and is not suggested again.
        $x = $this->makeLot(['no_lot' => 'Lot 4444', 'keluasan' => 1, 'unit_keluasan' => 'm2']);
        $y = $this->makeLot(['no_lot' => 'PT 4444', 'keluasan' => 1, 'unit_keluasan' => 'm2']);
        $this->artisan('pro:kesan-duplikasi')->assertSuccessful();
        $sid = CadanganDuplikasi::query()->where('keputusan', 'menunggu')->value('id');
        $this->actingAs($steward)->postJson("/api/v1/pro/duplikasi/{$sid}/keputusan", ['keputusan' => 'tolak', 'catatan' => 'Lot berbeza'])->assertOk();
        $this->artisan('pro:kesan-duplikasi')->assertSuccessful();
        $this->assertSame(0, CadanganDuplikasi::query()->where('keputusan', 'menunggu')->count());
        $this->assertSame('aktif', $y->refresh()->status_lot);
        $this->assertSame('aktif', $x->refresh()->status_lot);
    }

    // ---- SRS-PRO-23 / DAT-06 ------------------------------------------------------

    public function test_SRS_PRO_23_retention_archive_after_approval_searchable_and_restorable(): void
    {
        $officer = $this->officer(['pro.lot.lihat', 'pro.arkib.lihat', 'pro.arkib.cipta', 'pro.arkib.kemas_kini']);
        $approver = $this->officer([]);
        $old = $this->makeLot(['no_lot' => 'Lot 1', 'status_lot' => 'tidak_aktif']);
        $recent = $this->makeLot(['no_lot' => 'Lot 2', 'status_lot' => 'tidak_aktif']);
        $this->makeLot(['no_lot' => 'Lot 3']); // active: never a candidate
        DB::table('lot_tanah')->where('id', $old->id)->update(['updated_at' => now()->subYears(8)]);

        // 1. Automatic trigger identifies only the lot past its retention period.
        $this->artisan('pro:arkib-retensi')->assertSuccessful();
        $record = RekodArkib::query()->sole();
        $this->assertSame([$old->id, 'dicadang'], [$record->id_rekod, $record->status_arkib]);

        // 2. Submitted for approval through WFE.
        $this->actingAs($officer)->postJson("/api/v1/pro/arkib/{$record->id}/hantar")->assertOk();
        $this->assertSame(PengurusArkib::FLOW, array_values($this->workflow->started)[0]['flow']);
        $this->assertSame('arkib', $old->refresh()->status_lot === 'arkib' ? 'x' : 'arkib', 'not archived before approval');

        // 3. Approved → archived, hidden from the default list but searchable.
        $this->completeWorkflow(PengurusArkib::FLOW, PengurusArkib::SUBJECT, $record->id, 'lulus', $approver->id);
        $this->assertSame('arkib', $old->refresh()->status_lot);
        $this->assertSame('diarkib', $record->refresh()->status_arkib);
        $this->assertAudited('arkib', ['nama_entiti' => 'lot_tanah', 'id_rekod' => $old->id]);
        $ids = array_column($this->actingAs($officer)->getJson('/api/v1/pro/lot')->json('data'), 'id');
        $this->assertNotContains($old->id, $ids);
        $this->assertSame([$old->id], array_column($this->actingAs($officer)->getJson('/api/v1/pro/lot?arkib=sahaja&q=Lot 1')->json('data'), 'id'));
        $this->actingAs($officer)->getJson("/api/v1/pro/lot/{$old->id}")->assertOk()->assertJsonPath('data.status_lot', 'arkib');

        // 4. Restored by an authorised role, audited.
        $this->actingAs($this->officer(['pro.lot.lihat']))->postJson("/api/v1/pro/lot/{$old->id}/pulih", ['sebab' => 'x'])->assertForbidden();
        $this->actingAs($officer)->postJson("/api/v1/pro/lot/{$old->id}/pulih", ['sebab' => 'Dipulihkan untuk semakan kes'])->assertOk()->assertJsonPath('data.status_lot', 'tidak_aktif');
        $this->assertSame('dipulih', $record->refresh()->status_arkib);
        $this->assertAudited('pulih', ['nama_entiti' => 'lot_tanah', 'id_rekod' => $old->id, 'sebab_perubahan' => 'Dipulihkan untuk semakan kes']);
        $this->assertSame('tidak_aktif', $recent->refresh()->status_lot);
    }

    public function test_SRS_DAT_06_retention_schedule_is_configurable_per_category(): void
    {
        $admin = $this->officer(['pro.arkib.lihat', 'pro.arkib.kemas_kini']);
        $schedules = $this->actingAs($admin)->getJson('/api/v1/pro/arkib/jadual')->assertOk()->json('data');
        $this->assertContains('lot_tidak_aktif', array_column($schedules, 'kategori_rekod'));
        $id = collect($schedules)->firstWhere('kategori_rekod', 'lot_tidak_aktif')['id'];

        $this->actingAs($admin)->putJson("/api/v1/pro/arkib/jadual/{$id}", ['tempoh_retensi_tahun' => 1])->assertOk();
        $lot = $this->makeLot(['status_lot' => 'tidak_aktif']);
        DB::table('lot_tanah')->where('id', $lot->id)->update(['updated_at' => now()->subYears(2)]);
        $this->artisan('pro:arkib-retensi')->assertSuccessful();
        $this->assertSame(1, RekodArkib::query()->where('id_rekod', $lot->id)->count());
    }

    // ---- SRS-PRO-03/04/05, DAT-07 -------------------------------------------------

    public function test_SRS_PRO_05_data_dictionary_is_generated_from_schema_merged_with_business_metadata(): void
    {
        $user = $this->officer(['pro.kamus.lihat']);
        $rows = collect($this->actingAs($user)->getJson('/api/v1/pro/kamus?jadual=lot_tanah')->assertOk()->json('data'))->keyBy('nama_medan');

        $noLot = $rows['no_lot'];
        $this->assertSame(['No. Lot', 'character varying', 50, true, 'dalaman'], [$noLot['nama_perniagaan'], $noLot['jenis_data'], $noLot['saiz'], $noLot['wajib'], $noLot['klasifikasi']]);
        $this->assertSame('Bahagian Pengurusan Hartanah', $noLot['data_owner']);
        $this->assertNotEmpty($noLot['data_steward']);
        $this->assertSame('sulit', $rows['syarat_nyata']['klasifikasi']);
        $this->assertContains('PK', $rows['id']['kunci']);
        $this->assertStringContainsString('geometry', $rows['koordinat_pusat']['jenis_data']);

        $tables = array_column($this->actingAs($user)->getJson('/api/v1/pro/kamus/jadual')->json('data'), 'nama_jadual');
        $this->assertContains('vw_profil_hartanah_360', $tables);

        $editor = $this->officer(['pro.kamus.lihat', 'pro.kamus.kemas_kini']);
        $this->actingAs($user)->putJson('/api/v1/pro/kamus/metadata', ['nama_jadual' => 'lot_tanah', 'nama_medan' => 'zoning', 'nama_perniagaan' => 'Z', 'klasifikasi' => 'dalaman'])->assertForbidden();
        $this->actingAs($editor)->putJson('/api/v1/pro/kamus/metadata', ['nama_jadual' => 'lot_tanah', 'nama_medan' => 'zoning', 'nama_perniagaan' => 'Zon Perancangan', 'definisi' => 'Zon RT', 'klasifikasi' => 'dalaman'])->assertOk();
        $this->actingAs($editor)->putJson('/api/v1/pro/kamus/metadata', ['nama_jadual' => 'lot_tanah', 'nama_medan' => 'tiada_medan', 'nama_perniagaan' => 'x', 'klasifikasi' => 'dalaman'])->assertStatus(422);
        $this->assertSame('Zon Perancangan', collect($this->actingAs($user)->getJson('/api/v1/pro/kamus?q=perancangan')->json('data'))->first()['nama_perniagaan']);

        $csv = $this->actingAs($user)->get('/api/v1/pro/kamus/eksport?format=csv&jadual=lot_tanah')->assertOk()->streamedContent();
        $this->assertStringContainsString('Nama Perniagaan', $csv);
        $this->assertStringContainsString('Zon Perancangan', $csv);
    }

    public function test_SRS_DAT_07_physical_model_lists_indexes_views_and_stored_procedures(): void
    {
        $model = $this->actingAs($this->officer(['pro.kamus.lihat']))->getJson('/api/v1/pro/kamus/model-fizikal')->assertOk()->json('data');

        $views = array_column($model['view'], 'nama');
        foreach (['vw_profil_hartanah_360', 'vw_kualiti_data_rekod_tidak_lengkap', 'vw_lot_spatial_belum_disahkan'] as $view) {
            $this->assertContains($view, $views);
        }
        $this->assertContains('sp_kesan_duplikasi_lot', array_column($model['fungsi'], 'nama'));
        $this->assertContains('lot_tanah_no_lot_mukim_unique', array_column($model['indeks'], 'nama'));
        $this->assertTrue(collect($model['indeks'])->contains(fn ($i) => $i['nama'] === 'data_spatial_geometri_gix' && $i['spatial']));
        $this->assertContains('lot_tanah_keluasan_check', array_column($model['kekangan'], 'nama'));
    }

    public function test_SRS_PRO_03_04_document_generator_writes_erd_physical_model_and_dictionary(): void
    {
        $dir = 'storage/framework/testing/pro-docs-'.uniqid();
        $this->artisan('pro:jana-dokumen-data', ['--keluaran' => $dir, '--tanpa-ddl' => true])->assertSuccessful();

        $erd = (string) file_get_contents(base_path($dir.'/erd.md'));
        $this->assertStringContainsString('erDiagram', $erd);
        $this->assertStringContainsString('lot_tanah ||--o{ data_spatial : "lot_id"', $erd);
        $physical = (string) file_get_contents(base_path($dir.'/model-fizikal.md'));
        $this->assertStringContainsString('### `lot_tanah`', $physical);
        $this->assertStringContainsString('sp_kesan_duplikasi_lot', $physical);
        $this->assertStringContainsString('*(spatial)*', $physical);
        $this->assertStringContainsString('Syarat Nyata', (string) file_get_contents(base_path($dir.'/kamus-data.md')));

        array_map('unlink', glob(base_path($dir.'/*')) ?: []);
        rmdir(base_path($dir));
    }

    public function test_SRS_PRO_04_executable_ddl_is_generated_from_the_live_schema(): void
    {
        $dir = 'storage/framework/testing/pro-ddl-'.uniqid();
        $this->artisan('pro:jana-dokumen-data', ['--keluaran' => $dir])->assertSuccessful();

        if (! is_file(base_path($dir.'/ddl.sql'))) {
            array_map('unlink', glob(base_path($dir.'/*')) ?: []);
            rmdir(base_path($dir));
            $this->markTestSkipped('pg_dump tidak tersedia dalam persekitaran ini.');
        }
        $ddl = (string) file_get_contents(base_path($dir.'/ddl.sql'));
        $this->assertStringContainsString('CREATE TABLE public.lot_tanah', $ddl);
        $this->assertStringContainsString('CREATE VIEW public.vw_profil_hartanah_360', $ddl);
        $this->assertStringContainsString('CREATE FUNCTION public.sp_kesan_duplikasi_lot', $ddl);
        $this->assertStringContainsString('USING gist (geometri)', $ddl);

        array_map('unlink', glob(base_path($dir.'/*')) ?: []);
        rmdir(base_path($dir));
    }

    public function test_SRS_PRO_02_justification_document_matches_the_installed_database(): void
    {
        $doc = (string) file_get_contents(base_path('docs/data/justifikasi-pangkalan-data.md'));
        $server = (string) DB::selectOne("SELECT current_setting('server_version') AS v")->v;
        $postgis = (string) DB::selectOne("SELECT extversion AS v FROM pg_extension WHERE extname = 'postgis'")->v;

        $this->assertStringContainsString('PostgreSQL', $doc);
        $this->assertStringContainsString(explode('.', $server)[0].'.', $doc, 'major version documented');
        $this->assertStringContainsString(implode('.', array_slice(explode('.', $postgis), 0, 2)), $doc);
        foreach (['Kaedah sandaran', 'HA dan DR', 'alternatif', 'Keperluan pelayan'] as $section) {
            $this->assertStringContainsString($section, $doc);
        }
    }

    public function test_SRS_PRO_14_classification_register_per_data_component(): void
    {
        $rows = collect($this->actingAs($this->officer(['pro.kamus.lihat']))->getJson('/api/v1/pro/klasifikasi')->assertOk()->json('data'))->keyBy('entiti');

        $this->assertSame('sulit', $rows['hak_milik']['aras_kerahsiaan']);
        $this->assertSame('rahsia', $rows['penilaian']['aras_kerahsiaan']);
        $this->assertSame('rahsia', $rows['kes_mahkamah']['aras_kerahsiaan']);
        $this->assertSame('dalaman', $rows['lot_tanah']['aras_kerahsiaan']);
        $this->assertStringContainsString('AES-256', $rows['penilaian']['keperluan_encryption']);
    }
}
