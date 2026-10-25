<?php

namespace Tests\Modules\Lap;

use App\Modules\Lap\Models\DefinisiLaporan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Standard catalogue, ad hoc builder, drill-down, comparison, report header. */
class LaporanTest extends LapTestCase
{
    public function test_SRS_LAP_02_catalogue_covers_every_mandatory_category_and_links_module_reports(): void
    {
        $data = $this->actingAs($this->user([...self::PEMBACA, 'lap.log.lihat', 'aud.laporan.lihat']))->getJson('/api/v1/lap/katalog')->assertOk()->json('data');
        $categories = array_unique(array_column($data, 'kategori'));
        foreach (['portfolio', 'kewangan', 'operasi', 'risiko', 'pematuhan', 'aset', 'lembaga'] as $c) {
            $this->assertContains($c, $categories, "kategori {$c}");
        }
        $byCode = collect($data)->keyBy('kod');
        $this->assertSame('modul', $byCode['PJK-tunggakan']['jenis']);
        $this->assertSame('/admin/pjk/laporan?jenis=tunggakan', $byCode['PJK-tunggakan']['pautan']);
        $this->assertSame('komposit', $byCode['LEM-01']['jenis']);
        $this->assertNotEmpty($byCode['KEW-01']['definisi_metrik']);
        $this->assertNotEmpty($byCode['KEW-01']['sumber_data']);
        $this->assertNotEmpty($byCode['KEW-01']['pemilik']);

        // A module report is opened in its module — LAP never runs it.
        $this->actingAs($this->user())->postJson('/api/v1/lap/jana', ['sumber' => ['katalog' => 'PJK-tunggakan']])->assertNotFound();
    }

    public function test_SRS_LAP_02_every_standard_report_runs_and_drills(): void
    {
        $lot = $this->lot();
        $this->charge($this->contract($lot), now()->subMonth()->toDateString(), '100.00');
        $this->tax($lot);
        $this->complaint('100803');
        $user = $this->user([...self::PEMBACA, 'lap.log.lihat', 'aud.laporan.lihat', 'lap.analitik.lihat']);
        $codes = collect($this->actingAs($user)->getJson('/api/v1/lap/katalog')->json('data'))->whereIn('jenis', ['lap', 'komposit'])->pluck('kod');
        $this->assertGreaterThanOrEqual(20, $codes->count());
        foreach ($codes as $code) {
            $data = $this->jana($user, ['katalog' => $code]);
            $this->assertNotEmpty($data['bahagian'], $code);
        }
    }

    public function test_SRS_LAP_03_ad_hoc_fields_filters_grouping_sort_and_period(): void
    {
        foreach (['2026-02-10' => 'pencerobohan', '2026-03-01' => 'pencerobohan', '2026-03-05' => 'sempadan', '2025-03-05' => 'sempadan'] as $date => $cat) {
            $this->complaint('100803', ['kategori' => $cat, 'tarikh_terima' => $date.' 10:00:00', 'tarikh_aduan' => $date]);
        }
        $this->complaint('100901', ['kategori' => 'sempadan', 'tarikh_terima' => '2026-03-02 10:00:00', 'status' => 'ditutup']);
        $def = [
            'set' => 'aduan', 'kumpulan' => ['kategori'], 'agregat' => [['fungsi' => 'bilangan'], ['fungsi' => 'bilangan_benar', 'medan' => 'terbuka']],
            'penapis' => [['medan' => 'kod_daerah', 'op' => 'sama', 'nilai' => '1008']], 'tempoh' => ['dari' => '2026-01-01', 'hingga' => '2026-12-31'],
            'susunan' => [['medan' => 'kategori', 'arah' => 'desc']],
        ];
        $res = $this->jana($this->user(), ['adhoc' => $def])['bahagian'][0]['hasil'];
        $this->assertSame('kumpulan', $res['mod']);
        $this->assertSame(['sempadan', 'pencerobohan'], array_column($res['baris'], 'kategori'), 'sorted desc');
        $this->assertSame([1, 2], array_column($res['baris'], 'bilangan'));
        $this->assertSame(3, $res['jumlah_keseluruhan']['bilangan']);

        // Two levels with a named code (district name column) group correctly.
        $two = $this->jana($this->user(), ['adhoc' => ['set' => 'aduan', 'kumpulan' => ['kod_daerah', 'status']]])['bahagian'][0]['hasil'];
        $this->assertSame(5, $two['jumlah_keseluruhan']['bilangan']);
        $this->assertContains('Petaling', array_map(fn ($r) => $r['_label']['kod_daerah'], $two['baris']));

        // Record mode with chosen fields.
        $res = $this->jana($this->user(), ['adhoc' => ['set' => 'aduan', 'medan' => ['no_rujukan_aduan', 'kategori', 'status'], 'penapis' => [['medan' => 'status', 'op' => 'sama', 'nilai' => 'ditutup']]]])['bahagian'][0]['hasil'];
        $this->assertSame('rekod', $res['mod']);
        $this->assertSame(['no_rujukan_aduan', 'kategori', 'status'], array_column($res['lajur'], 'kunci'));
        $this->assertCount(1, $res['baris']);
        $this->assertStringStartsWith('/admin/adu/aduan/', $res['baris'][0]['_pautan']);
    }

    public function test_SRS_LAP_03_definition_only_accepts_whitelisted_fields_and_operators(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson('/api/v1/lap/jana', ['sumber' => ['adhoc' => ['set' => 'aduan', 'medan' => ['id; DROP TABLE aduan']]]])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/v1/lap/jana', ['sumber' => ['adhoc' => ['set' => 'aduan', 'penapis' => [['medan' => 'kategori', 'op' => 'OR 1=1', 'nilai' => 'x']]]]])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/v1/lap/jana', ['sumber' => ['adhoc' => ['set' => 'aduan', 'kumpulan' => ['tempoh_selesai_hari']]]])->assertStatus(422);
        $this->actingAs($user)->postJson('/api/v1/lap/jana', ['sumber' => ['adhoc' => ['set' => 'tiada']]])->assertStatus(422);
        $this->assertSame(0, DB::table('aduan')->count() - DB::table('aduan')->count());
    }

    public function test_SRS_LAP_03_saved_shared_definition_runs_with_the_readers_scope(): void
    {
        $this->complaint('100803');
        $this->complaint('100901');
        $creator = $this->user();
        $id = $this->actingAs($creator)->postJson('/api/v1/lap/definisi', [
            'nama' => 'Aduan ikut daerah', 'pertanyaan' => ['set' => 'aduan', 'kumpulan' => ['kod_daerah']], 'status_kongsi' => 'semua',
        ])->assertCreated()->json('data.id');

        $mine = $this->rows($this->jana($creator, ['definisi' => $id]), 'kod_daerah');
        $this->assertCount(2, $mine);

        $reader = $this->user(districts: ['1009']);
        $this->assertContains($id, collect($this->actingAs($reader)->getJson('/api/v1/lap/definisi')->json('data'))->pluck('id')->all());
        $theirs = $this->rows($this->jana($reader, ['definisi' => $id]), 'kod_daerah');
        $this->assertSame(['1009'], array_map('strval', array_keys($theirs)), 'recipient permissions, not the creator\'s');

        // Only the creator edits.
        $this->actingAs($reader)->putJson("/api/v1/lap/definisi/{$id}", ['nama' => 'x', 'pertanyaan' => ['set' => 'aduan'], 'status_kongsi' => 'semua'])->assertForbidden();
    }

    public function test_SRS_LAP_03_private_definition_is_invisible_to_others_and_role_sharing_works(): void
    {
        $creator = $this->user(role: 'pegawai_operasi');
        $id = $this->actingAs($creator)->postJson('/api/v1/lap/definisi', ['nama' => 'Peribadi', 'pertanyaan' => ['set' => 'aduan'], 'status_kongsi' => 'peribadi'])->assertCreated()->json('data.id');
        $other = $this->user(role: 'pegawai_operasi');
        $this->actingAs($other)->getJson("/api/v1/lap/definisi/{$id}")->assertNotFound();
        $this->actingAs($other)->postJson('/api/v1/lap/jana', ['sumber' => ['definisi' => $id]])->assertForbidden();

        DefinisiLaporan::query()->whereKey($id)->update(['status_kongsi' => 'peranan', 'kongsi_peranan' => json_encode(['pegawai_operasi'])]);
        $this->actingAs($other)->getJson("/api/v1/lap/definisi/{$id}")->assertOk();
        $this->actingAs($this->user())->getJson("/api/v1/lap/definisi/{$id}")->assertNotFound();
    }

    public function test_SRS_LAP_12_drill_down_from_aggregate_to_group_to_records_to_original_record(): void
    {
        $a = $this->lot(['kod_mukim' => '100803', 'no_lot' => 'Lot A1']);
        $this->lot(['kod_mukim' => '100804', 'no_lot' => 'Lot B1']);
        $this->lot(['kod_mukim' => '100901', 'no_lot' => 'Lot C1']);
        $user = $this->user();

        $l0 = $this->jana($user, ['katalog' => 'POR-01'])['bahagian'][0]['hasil'];
        $this->assertSame(['kod_daerah'], $l0['kumpulan']);
        $this->assertSame('Petaling', $l0['baris'][0]['_label']['kod_daerah']);

        $l1 = $this->jana($user, ['katalog' => 'POR-01'], ['drill' => ['kod_daerah' => '1008']])['bahagian'][0]['hasil'];
        $this->assertSame(['kod_mukim'], $l1['kumpulan']);
        $this->assertCount(2, $l1['baris']);

        $l2 = $this->jana($user, ['katalog' => 'POR-01'], ['drill' => ['kod_daerah' => '1008', 'kod_mukim' => '100803']])['bahagian'][0]['hasil'];
        $this->assertSame(['kategori_guna_tanah'], $l2['kumpulan']);

        $l3 = $this->jana($user, ['katalog' => 'POR-01'], ['drill' => ['kod_daerah' => '1008', 'kod_mukim' => '100803', 'kategori_guna_tanah' => 'BANGUNAN']])['bahagian'][0]['hasil'];
        $this->assertSame('rekod', $l3['mod']);
        $this->assertSame(['Lot A1'], array_column($l3['baris'], 'no_lot'));
        $this->assertSame('/admin/pro/lot/'.$a, $l3['baris'][0]['_pautan'], 'original SISDATA record');
        $this->assertAudited('jana', ['modul' => 'lap', 'nama_entiti' => 'lap_log_penjanaan']);
    }

    public function test_SRS_LAP_02_year_on_year_comparison_with_exact_decimals(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $k = $this->contract($this->lot());
        $this->charge($k, '2026-03-01', '0.10', '0.10');
        $this->charge($k, '2026-03-02', '0.20', '0.20', ['kod_caj' => 'servis']);
        $this->charge($k, '2025-03-01', '0.10', '0.10');
        $this->charge($k, '2026-04-01', '1000.00', '333.33');

        $res = $this->jana($this->user(), ['katalog' => 'KEW-01'])['bahagian'][0]['hasil'];
        $rows = collect($res['baris'])->keyBy('tarikh_caj:bulan');
        $this->assertSame('0.30', $rows['2026-03']['jumlah_amaun']);
        $this->assertSame('0.10', $rows['2026-03']['jumlah_amaun__lepas']);
        $this->assertSame('0.20', $rows['2026-03']['jumlah_amaun__beza']);
        $this->assertSame('200.00', $rows['2026-03']['jumlah_amaun__peratus']);
        $this->assertNull($rows['2026-04']['jumlah_amaun__peratus'], 'no base year value');
        $this->assertSame('1000.30', $res['jumlah_keseluruhan']['jumlah_amaun']);
        $this->assertSame('333.63', $res['jumlah_keseluruhan']['jumlah_amaun_dibayar']);
        Carbon::setTestNow();
    }

    public function test_SRS_LAP_11_every_result_carries_data_timestamp_period_and_filters(): void
    {
        $this->complaint('100803');
        $res = $this->jana($this->user(), ['katalog' => 'OPS-02'], ['parameter' => ['kod_daerah' => '1008']])['bahagian'][0]['hasil'];
        $m = $res['meta'];
        $this->assertNotNull($m['data_dikemas_kini']);
        $this->assertNotNull($m['data_setakat']);
        $this->assertNotNull($m['dijana_pada']);
        $this->assertSame(now()->startOfYear()->toDateString(), $m['tempoh']['dari']);
        $this->assertContains('Daerah = 1008', $m['penapis_teks']);
        $this->assertTrue(collect($m['penapis_teks'])->contains(fn ($t) => str_starts_with($t, 'Tarikh aduan:')));
    }

    public function test_SRS_LAP_07_results_are_cached_per_scope(): void
    {
        $this->params->values['lap.cache_saat'] = 300;
        $this->complaint('100803');
        $this->complaint('100901');
        $state = $this->user();
        $this->assertFalse($this->jana($state, ['katalog' => 'OPS-02'])['bahagian'][0]['hasil']['dari_cache']);
        $this->assertTrue($this->jana($state, ['katalog' => 'OPS-02'])['bahagian'][0]['hasil']['dari_cache']);

        $district = $this->user(districts: ['1009']);
        $r = $this->jana($district, ['katalog' => 'OPS-02'])['bahagian'][0]['hasil'];
        $this->assertFalse($r['dari_cache'], 'a cache entry is never shared across scopes');
        $this->assertSame(1, $r['bilangan_rekod']);
    }

    public function test_SRS_LAP_02_composite_board_report_includes_only_permitted_sections(): void
    {
        $this->lot();
        $full = $this->jana($this->user(), ['katalog' => 'LEM-01']);
        $this->assertTrue($full['komposit']);
        $this->assertGreaterThanOrEqual(8, count($full['bahagian']));

        $narrow = $this->jana($this->user(['lap.laporan.lihat', 'pro.lot.lihat']), ['katalog' => 'LEM-01']);
        $this->assertSame(['POR-01'], array_column($narrow['bahagian'], 'kod'));
    }

    public function test_SRS_LAP_22_system_usage_analytics_feed_the_upgrade_plan(): void
    {
        $user = $this->user([...self::PEMBACA, 'aud.laporan.lihat']);
        $this->jana($user, ['katalog' => 'POR-01']); // audited activity in module "lap"
        $rows = $this->rows($this->jana($user, ['katalog' => 'PEM-04']), 'modul');
        $this->assertArrayHasKey('lap', $rows);
        $this->assertGreaterThanOrEqual(1, $rows['lap']['bilangan']);
        $this->assertArrayHasKey('bilangan__lepas', $rows['lap'], 'year-on-year usage comparison');
    }

    public function test_SRS_LAP_23_data_dictionary_lists_metric_source_owner_and_field_definitions(): void
    {
        DB::table('kamus_data')->updateOrInsert(['nama_jadual' => 'aduan', 'nama_medan' => 'kategori'], ['nama_perniagaan' => 'Kategori aduan', 'definisi' => 'Jenis isu yang diadukan', 'data_owner' => 'Unit Aduan']);
        $data = $this->actingAs($this->user())->getJson('/api/v1/lap/kamus')->assertOk()->json('data');
        $report = collect($data['laporan'])->firstWhere('kod', 'OPS-02');
        $this->assertNotEmpty($report['definisi_metrik']);
        $this->assertStringContainsString('laporan.v_aduan', $report['sumber_data']);
        $field = collect(collect($data['set_data'])->firstWhere('kunci', 'aduan')['medan'])->firstWhere('kunci', 'kategori');
        $this->assertSame('Jenis isu yang diadukan', $field['definisi']);
        $this->assertSame('Unit Aduan', $field['data_owner']);
        $this->assertSame('/admin/pro/kamus', $data['pautan_kamus_pro']);
    }

    public function test_SRS_LAP_02_admin_records_crp2_validation_of_a_standard_report(): void
    {
        $admin = $this->user([...self::PEMBACA, 'lap.katalog.kemas_kini']);
        $this->actingAs($admin)->getJson('/api/v1/lap/katalog')->assertOk();
        $r = $this->actingAs($admin)->putJson('/api/v1/lap/katalog/KEW-02', ['status_pengesahan' => 'disahkan', 'pemilik' => 'Bahagian Kewangan MAIS'])->assertOk()->json('data');
        $this->assertSame('disahkan', $r['status_pengesahan']);
        $this->assertNotNull($r['disahkan_pada']);
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'lap_katalog_laporan']);
        $this->actingAs($this->user())->putJson('/api/v1/lap/katalog/KEW-02', ['status_pengesahan' => 'draf'])->assertForbidden();
    }
}
