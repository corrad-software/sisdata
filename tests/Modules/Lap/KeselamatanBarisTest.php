<?php

namespace Tests\Modules\Lap;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SRS-LAP-08 (row-level security at query level), SRS-LAP-09 (report access)
 * and SRS-RBC-08 (reporting / BI cannot bypass authorisation).
 */
class KeselamatanBarisTest extends LapTestCase
{
    public function test_SRS_LAP_08_reporting_views_return_nothing_without_scope_settings(): void
    {
        $this->lot(['kod_mukim' => '100803']);
        $this->lot(['kod_mukim' => '100901']);

        $this->assertSame(2, DB::table('lot_tanah')->count());
        $this->assertSame(0, (int) DB::selectOne('SELECT count(*) AS n FROM laporan.v_lot')->n, 'deny by default when the session carries no scope');
    }

    public function test_SRS_LAP_08_direct_query_on_view_enforces_the_session_district_scope(): void
    {
        $this->lot(['kod_mukim' => '100803']);
        $this->lot(['kod_mukim' => '100804']);
        $this->lot(['kod_mukim' => '100901']);

        $n = DB::transaction(function () {
            DB::select("SELECT set_config('sisdata.skop_daerah', '1009', true), set_config('sisdata.skop_mukim', '*', true), set_config('sisdata.skop_bahagian', '*', true), set_config('sisdata.tahap_klasifikasi', 'awam,dalaman', true)");

            return DB::select('SELECT kod_daerah FROM laporan.v_lot');
        });
        $this->assertSame(['1009'], array_values(array_unique(array_map(fn ($r) => $r->kod_daerah, $n))));

        $all = DB::transaction(function () {
            DB::select("SELECT set_config('sisdata.skop_daerah', '*', true), set_config('sisdata.skop_mukim', '*', true), set_config('sisdata.skop_bahagian', '*', true), set_config('sisdata.tahap_klasifikasi', 'awam,dalaman', true)");

            return (int) DB::selectOne('SELECT count(*) AS n FROM laporan.v_lot')->n;
        });
        $this->assertSame(3, $all);
    }

    public function test_SRS_LAP_08_district_user_opening_a_state_report_receives_only_their_rows(): void
    {
        $this->lot(['kod_mukim' => '100803']);
        $this->lot(['kod_mukim' => '100804']);
        $this->lot(['kod_mukim' => '100901']);

        $state = $this->rows($this->jana($this->user(), ['katalog' => 'POR-01']), 'kod_daerah');
        $this->assertSame(['1008', '1009'], array_map('strval', array_keys($state)));
        $this->assertSame(2, array_values($state)[0]['bilangan'], '1008 first (2 lots)');

        $district = $this->rows($this->jana($this->user(districts: ['1009']), ['katalog' => 'POR-01']), 'kod_daerah');
        $this->assertSame(['1009'], array_map('strval', array_keys($district)));

        // A drill or filter into another district yields nothing — the database refuses the rows.
        $data = $this->jana($this->user(districts: ['1009']), ['katalog' => 'POR-01'], ['drill' => ['kod_daerah' => '1008']]);
        $this->assertSame([], $data['bahagian'][0]['hasil']['baris']);
        $this->assertSame(0, $data['bahagian'][0]['hasil']['bilangan_rekod']);
    }

    public function test_SRS_LAP_08_sulit_rows_need_the_dataset_classification_permission(): void
    {
        $this->lot(['kod_mukim' => '100803', 'klasifikasi' => 'dalaman']);
        $this->lot(['kod_mukim' => '100803', 'klasifikasi' => 'sulit']);

        $plain = $this->jana($this->user(), ['katalog' => 'POR-01']);
        $this->assertSame(1, $plain['bahagian'][0]['hasil']['bilangan_rekod']);

        $cleared = $this->jana($this->user([...self::PEMBACA, 'pro.sulit.lihat']), ['katalog' => 'POR-01']);
        $this->assertSame(2, $cleared['bahagian'][0]['hasil']['bilangan_rekod']);
        $this->assertSame('sulit', $cleared['bahagian'][0]['hasil']['meta']['klasifikasi_tertinggi']);
    }

    public function test_SRS_LAP_09_report_needs_the_source_module_permission_and_denials_are_audited(): void
    {
        $user = $this->user(['lap.laporan.lihat', 'pro.lot.lihat']);

        $codes = collect($this->actingAs($user)->getJson('/api/v1/lap/katalog')->assertOk()->json('data'))->pluck('kod')->all();
        $this->assertContains('POR-01', $codes);
        $this->assertNotContains('KEW-01', $codes, 'finance report hidden without pjk.laporan.lihat');

        $this->actingAs($user)->postJson('/api/v1/lap/jana', ['sumber' => ['katalog' => 'KEW-01']])->assertForbidden();
        $this->assertAudited('jana', ['modul' => 'lap', 'status_transaksi' => 'ditolak', 'id_rekod' => 'KEW-01']);

        // Ad hoc on a dataset the user may not read is refused too (BI / builder cannot bypass).
        $this->actingAs($user)->postJson('/api/v1/lap/jana', ['sumber' => ['adhoc' => ['set' => 'cukai', 'kumpulan' => ['kod_daerah']]]])->assertForbidden();
    }

    public function test_SRS_LAP_09_field_level_permission_hides_rahsia_fields(): void
    {
        $lot = $this->lot();
        DB::table('penilaian')->insert(['id' => (string) str()->uuid(), 'lot_id' => $lot, 'tarikh_penilaian' => '2025-01-01', 'nilai_pasaran' => 999999, 'versi' => 1, 'klasifikasi' => 'rahsia', 'created_at' => now(), 'updated_at' => now()]);
        $def = ['set' => 'lot', 'medan' => ['no_lot', 'nilai_pasaran']];

        $data = $this->jana($this->user(), ['adhoc' => $def]);
        $this->assertSame(['no_lot'], array_column($data['bahagian'][0]['hasil']['lajur'], 'kunci'));
        $this->assertContains('Nilai pasaran terkini (RM)', $data['bahagian'][0]['hasil']['meta']['medan_disembunyikan']);

        $data = $this->jana($this->user([...self::PEMBACA, 'pro.rahsia.lihat']), ['adhoc' => $def]);
        $this->assertSame('999999.00', $data['bahagian'][0]['hasil']['baris'][0]['nilai_pasaran']);
    }

    public function test_SRS_LAP_09_allowed_roles_restrict_a_report(): void
    {
        $this->actingAs($admin = $this->user([...self::PEMBACA, 'lap.katalog.kemas_kini']))->getJson('/api/v1/lap/katalog')->assertOk();
        $this->actingAs($admin)->putJson('/api/v1/lap/katalog/POR-01', ['peranan_dibenarkan' => ['pengurusan_tertinggi']])->assertOk();

        $this->actingAs($this->user())->postJson('/api/v1/lap/jana', ['sumber' => ['katalog' => 'POR-01']])->assertForbidden();
    }

    public function test_SRS_LAP_09_user_without_lap_permission_is_refused(): void
    {
        $this->postJson('/api/v1/lap/jana', ['sumber' => ['katalog' => 'POR-01']])->assertUnauthorized();
        $this->actingAs($this->user(['pro.lot.lihat']))->getJson('/api/v1/lap/katalog')->assertForbidden();
        $this->actingAs($this->user(['pro.lot.lihat']))->postJson('/api/v1/lap/jana', ['sumber' => ['katalog' => 'POR-01']])->assertForbidden();
    }

    public function test_SRS_LAP_08_bi_role_reads_only_aggregate_views(): void
    {
        $role = DB::selectOne("SELECT 1 AS ok FROM pg_roles WHERE rolname = 'sisdata_bi'");
        if (! $role) {
            $this->markTestSkipped('Peranan sisdata_bi belum dicipta (infra/bin/sisdata-bi-setup.sh).');
        }
        $grants = collect(DB::select("SELECT table_name FROM information_schema.role_table_grants WHERE grantee = 'sisdata_bi' AND privilege_type = 'SELECT'"))->pluck('table_name')->all();
        $this->assertContains('bi_portfolio', $grants);
        $this->assertNotContains('v_lot', $grants);
        $this->assertNotContains('lot_tanah', $grants);
        $this->assertFalse((bool) DB::selectOne("SELECT has_table_privilege('sisdata_bi', 'public.lot_tanah', 'SELECT') AS p")->p);
        $this->assertFalse((bool) DB::selectOne("SELECT has_table_privilege('sisdata_bi', 'laporan.v_lot', 'SELECT') AS p")->p);
        $this->assertTrue((bool) DB::selectOne("SELECT has_table_privilege('sisdata_bi', 'laporan.bi_portfolio', 'SELECT') AS p")->p);

        // Connect as the BI role itself (what Metabase does): aggregates readable, raw / row data and writes refused.
        $base = (array) config('database.connections.'.config('database.default'));
        config(['database.connections.lap_bi_ujian' => array_merge($base, ['username' => 'sisdata_bi', 'password' => getenv('SISDATA_BI_PASSWORD') ?: 'sisdata_bi_local_pw'])]);
        try {
            $bi = DB::connection('lap_bi_ujian');
            $this->assertNotNull($bi->selectOne('SELECT count(*) AS n FROM laporan.bi_portfolio'), 'aggregate view readable');
            foreach (['SELECT 1 FROM lot_tanah', 'SELECT 1 FROM laporan.v_lot', "INSERT INTO laporan.bi_portfolio (kod_daerah) VALUES ('x')"] as $sql) {
                try {
                    $bi->select($sql);
                    $this->fail("sisdata_bi must not run: {$sql}");
                } catch (QueryException $e) {
                    $this->assertMatchesRegularExpression('/permission denied|read-only|cannot insert/i', $e->getMessage());
                }
            }
        } finally {
            DB::disconnect('lap_bi_ujian');
        }
    }
}
