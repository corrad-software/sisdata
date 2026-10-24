<?php

namespace Tests\Modules\Dsh;

use App\Modules\Dsh\Contracts\KpiProviders;
use Tests\Modules\Dsh\Support\FakeKpi;

class ArasDashboardTest extends DshTestCase
{
    public function test_SRS_DSH_01_three_levels_are_chosen_automatically_from_the_users_role(): void
    {
        $all = ['dsh.dashboard.lihat', 'dsh.pengurusan.lihat', 'dsh.eksekutif.lihat', 'pro.lot.lihat', 'wfe.tugasan.lihat'];
        $exec = $this->user($all, role: 'pentadbir_sistem');
        $mgmt = $this->user($all, role: 'pelulus');
        $ops = $this->user($all, role: 'pegawai_operasi');

        $this->actingAs($exec)->getJson('/api/v1/dsh/dashboard')->assertOk()->assertJsonPath('data.aras', 'eksekutif');
        $this->actingAs($mgmt)->getJson('/api/v1/dsh/dashboard')->assertOk()->assertJsonPath('data.aras', 'pengurusan');
        $ops = $this->actingAs($ops)->getJson('/api/v1/dsh/dashboard')->assertOk()->assertJsonPath('data.aras', 'operasi');

        // Operasi shows the daily-work widgets, not the state portfolio.
        $kods = array_column($ops->json('data.widget'), 'kod');
        $this->assertContains('sistem:tugasan', $kods);
        $this->assertContains('kpi:wfe.tugasan_saya', $kods);
        $this->assertNotContains('kpi:pro.bilangan_lot', $kods);
    }

    public function test_SRS_DSH_01_without_a_role_mapping_the_highest_permitted_level_is_shown(): void
    {
        $this->actingAs($this->user(['dsh.dashboard.lihat', 'dsh.pengurusan.lihat']))->getJson('/api/v1/dsh/dashboard')
            ->assertOk()->assertJsonPath('data.aras', 'pengurusan')->assertJsonPath('data.aras_tersedia', ['pengurusan', 'operasi']);

        $this->params->values['dsh.aras_peranan'] = json_encode(['pegawai_operasi' => 'eksekutif']);
        $this->actingAs($this->user(self::EKSEKUTIF, role: 'pegawai_operasi'))->getJson('/api/v1/dsh/dashboard')->assertJsonPath('data.aras', 'eksekutif');
    }

    public function test_SRS_DSH_01_district_and_division_users_get_their_variant_never_the_state_view(): void
    {
        $district = $this->user(self::EKSEKUTIF, ['1008']);
        $this->actingAs($district)->getJson('/api/v1/dsh/dashboard')->assertOk()
            ->assertJsonPath('data.aras', 'pengurusan')
            ->assertJsonPath('data.varian', 'daerah')
            ->assertJsonPath('data.skop.daerah.0.nama', 'Petaling');
        $this->actingAs($district)->getJson('/api/v1/dsh/dashboard?aras=eksekutif')->assertForbidden();

        $division = $this->user(self::EKSEKUTIF, bahagian: ['wakaf']);
        $this->actingAs($division)->getJson('/api/v1/dsh/dashboard')->assertJsonPath('data.varian', 'bahagian');
    }

    public function test_SRS_DSH_01_user_without_permission_is_refused(): void
    {
        $this->actingAs($this->user(['pro.lot.lihat']))->getJson('/api/v1/dsh/dashboard')->assertForbidden();
        $this->actingAs($this->user(['dsh.dashboard.lihat']))->getJson('/api/v1/dsh/dashboard?aras=pengurusan')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/dsh/dashboard')->assertUnauthorized();
    }

    public function test_SRS_DSH_09_dashboard_offers_all_seven_visual_types(): void
    {
        $lot = $this->lot();
        $this->title($lot);
        $user = $this->user([...self::EKSEKUTIF, 'gis.peta.lihat', 'dsh.widget.kemas_kini']);
        $res = $this->actingAs($user)->getJson('/api/v1/dsh/dashboard?aras=eksekutif')->assertOk();
        $widgets = collect($res->json('data.widget'));
        $kpis = collect($res->json('data.kpi'))->keyBy('key');

        $this->assertSame('peta', $widgets->firstWhere('kod', 'sistem:peta')['jenis_visual']);
        $this->assertSame('lampu', $widgets->firstWhere('kod', 'sistem:lampu')['jenis_visual']);
        // Data to draw bar / pie (pecahan), line (trend), heatmap (matriks), progress (% + sasaran).
        $this->assertNotEmpty($kpis['pro.bilangan_lot']['pecahan']);
        $this->assertCount(5, $kpis['pro.bilangan_lot']['trend']);
        $this->assertNotEmpty($kpis['pro.bilangan_lot']['matriks']['baris']);
        $this->assertSame('%', $kpis['wfe.dalam_sla']['unit']);
        $this->assertEquals(90, $kpis['wfe.dalam_sla']['sasaran']);

        $this->actingAs($user)->getJson('/api/v1/dsh/tetapan')->assertOk()
            ->assertJsonPath('data.visual', ['auto', 'stat', 'bar', 'pie', 'line', 'progress', 'heatmap', 'lampu', 'peta', 'senarai', 'insight']);
    }

    public function test_SRS_DSH_03_map_widget_is_shown_only_with_gis_permission(): void
    {
        $with = $this->actingAs($this->user([...self::EKSEKUTIF, 'gis.peta.lihat']))->getJson('/api/v1/dsh/dashboard?aras=eksekutif');
        $this->assertContains('sistem:peta', array_column($with->json('data.widget'), 'kod'));
        $with->assertJsonPath('data.kebenaran.peta', true);

        $without = $this->actingAs($this->user())->getJson('/api/v1/dsh/dashboard?aras=eksekutif');
        $this->assertNotContains('sistem:peta', array_column($without->json('data.widget'), 'kod'));
    }

    public function test_SRS_DSH_04_map_and_indicators_share_one_filter_set(): void
    {
        $this->lot(['kod_mukim' => '100803']);
        $this->lot(['kod_mukim' => '100901']);
        $res = $this->actingAs($this->user([...self::EKSEKUTIF, 'gis.peta.lihat']))->getJson('/api/v1/dsh/dashboard?aras=eksekutif&kod_daerah=1009')->assertOk();
        // The response echoes the normalised filters the frontend applies to the map layers too.
        $res->assertJsonPath('data.penapis', ['kod_daerah' => '1009']);
        $this->assertSame(1, collect($res->json('data.kpi'))->firstWhere('key', 'pro.bilangan_lot')['nilai']);
    }

    public function test_SRS_DSH_05_tax_dashboard_shows_mod_cki_kpis(): void
    {
        $cki = $this->kpis($this->user([...self::EKSEKUTIF, 'cki.laporan.lihat']), ['modul' => 'cki']);
        $this->assertEqualsCanonicalizing(['cki.jumlah_cukai_tahunan', 'cki.tertunggak', 'cki.peratus_bayar_tepat_masa', 'cki.penalti'], array_keys($cki));
        foreach ($cki as $kpi) {
            $this->assertFalse($kpi['ralat'], $kpi['key'].' computed');
        }
    }

    public function test_SRS_DSH_06_lease_dashboard_shows_mod_pjk_kpis(): void
    {
        $pjk = $this->kpis($this->user([...self::EKSEKUTIF, 'pjk.laporan.lihat']), ['modul' => 'pjk']);
        $this->assertEqualsCanonicalizing(['pjk.kadar_kutipan', 'pjk.tunggakan', 'pjk.kadar_penghunian', 'pjk.kontrak_hampir_tamat'], array_keys($pjk));
        foreach ($pjk as $kpi) {
            $this->assertFalse($kpi['ralat'], $kpi['key'].' computed');
        }
        $this->assertArrayNotHasKey('pjk.kadar_kutipan', $this->kpis($this->user(), ['modul' => 'pjk']));
    }

    public function test_SRS_DSH_07_monitoring_dashboard_shows_kpis_registered_by_mod_ptn(): void
    {
        app(KpiProviders::class)->register(FakeKpi::class);
        $ptn = $this->kpis($this->user([...self::EKSEKUTIF, 'ptn.laporan.lihat']), ['modul' => 'ptn']);
        // The fake plus MOD-PTN's own registered KPIs all appear on the monitoring dashboard.
        $this->assertContains('ptn.risiko_tinggi', array_keys($ptn));
        $this->assertContains('ptn.lawatan_ikut_jadual', array_keys($ptn));
        $this->assertSame('merah', $ptn['ptn.risiko_tinggi']['lampu'], 'risk rating coloured by its target');
    }
}
