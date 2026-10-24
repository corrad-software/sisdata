<?php

namespace Tests\Modules\Dsh;

use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Dsh\Services\KpiRegistry;
use App\Modules\Dsh\Services\KpiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Tests\Modules\Dsh\Support\FakeKpi;

class KpiRegistryTest extends DshTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeKpi::$calls = 0;
        FakeKpi::$fail = false;
    }

    public function test_SRS_DSH_02_registry_is_a_singleton_holding_every_modules_kpis(): void
    {
        $registry = app(KpiProviders::class);
        $this->assertInstanceOf(KpiRegistry::class, $registry);
        $this->assertSame($registry, app(KpiRegistry::class));

        $keys = array_keys($registry->all());
        // Merged modules register through callAfterResolving in their own boot().
        foreach (['pjk.kadar_kutipan', 'pjk.tunggakan', 'pjk.kadar_penghunian', 'pjk.kontrak_hampir_tamat',
            'cki.jumlah_cukai_tahunan', 'cki.tertunggak', 'cki.peratus_bayar_tepat_masa', 'cki.penalti',
            'adu.aduan_terbuka', 'adu.dalam_sla', 'adu.purata_masa_selesai'] as $key) {
            $this->assertContains($key, $keys);
        }
        // MOD-DSH's own portfolio KPIs for modules without KPIs yet.
        foreach (['pro.bilangan_lot', 'pro.keluasan', 'pro.nilai_portfolio', 'hkm.hak_milik', 'hkm.status_tanah', 'hkm.hampir_tamat',
            'per.pemerolehan_tahunan', 'wfe.tugasan_tertunggak', 'wfe.dalam_sla', 'dms.storan', 'kes.aktif', 'dsh.hasil_kos'] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_SRS_DSH_02_kpi_registered_after_the_registry_was_resolved_is_still_shown(): void
    {
        app(KpiProviders::class)->all();

        // A module booting late (e.g. MOD-PTN) registers lazily.
        $provider = new class($this->app) extends ServiceProvider
        {
            public function boot(): void
            {
                $this->callAfterResolving(KpiProviders::class, fn (KpiProviders $k) => $k->register(FakeKpi::class));
            }
        };
        $provider->boot();

        $user = $this->user([...self::EKSEKUTIF, 'ptn.laporan.lihat']);
        $kpis = $this->kpis($user);
        $this->assertArrayHasKey('ptn.risiko_tinggi', $kpis);
        $this->assertSame(42, $kpis['ptn.risiko_tinggi']['nilai']);
        $this->assertSame('/admin/ptn/laporan?jenis=risiko', $kpis['ptn.risiko_tinggi']['pautan']);
    }

    public function test_SRS_DSH_02_kpi_is_hidden_without_its_permission(): void
    {
        app(KpiProviders::class)->register(FakeKpi::class);
        $kpis = $this->kpis($this->user());
        $this->assertArrayNotHasKey('ptn.risiko_tinggi', $kpis);
        $this->assertArrayNotHasKey('pjk.kadar_kutipan', $kpis);
        $this->assertArrayHasKey('pro.bilangan_lot', $kpis);
    }

    public function test_SRS_DSH_02_a_failing_provider_yields_an_error_entry_not_a_broken_dashboard(): void
    {
        app(KpiProviders::class)->register(FakeKpi::class);
        FakeKpi::$fail = true;
        $user = $this->user([...self::EKSEKUTIF, 'ptn.laporan.lihat']);

        $res = $this->actingAs($user)->getJson('/api/v1/dsh/dashboard?aras=eksekutif')->assertOk();
        $kpi = collect($res->json('data.kpi'))->firstWhere('key', 'ptn.risiko_tinggi');
        $this->assertTrue($kpi['ralat']);
        $this->assertNull($kpi['nilai']);
        $this->assertNotNull(collect($res->json('data.kpi'))->firstWhere('key', 'pro.bilangan_lot'));
    }

    public function test_SRS_DSH_13_results_are_cached_per_scope_signature_with_a_ttl_of_at_most_15_minutes(): void
    {
        Cache::flush();
        app(KpiProviders::class)->register(FakeKpi::class);
        $this->params->values['dsh.cache_saat'] = 3600;
        $this->assertSame(KpiService::MAX_TTL, app(KpiService::class)->ttl());

        $state = $this->user([...self::EKSEKUTIF, 'ptn.laporan.lihat']);
        $district = $this->user([...self::EKSEKUTIF, 'ptn.laporan.lihat'], ['1008']);

        $this->kpis($state, ['kunci' => 'ptn.risiko_tinggi']);
        $this->kpis($state, ['kunci' => 'ptn.risiko_tinggi']);
        $this->assertSame(1, FakeKpi::$calls, 'Same scope + filters is served from cache');

        $this->kpis($district, ['kunci' => 'ptn.risiko_tinggi']);
        $this->assertSame(2, FakeKpi::$calls, 'A different scope never shares a cached result');

        $this->kpis($state, ['kunci' => 'ptn.risiko_tinggi', 'tahun' => 2025]);
        $this->assertSame(3, FakeKpi::$calls, 'Different filters are a different cache entry');

        $this->kpis($state, ['kunci' => 'ptn.risiko_tinggi', 'segar' => 1]);
        $this->assertSame(4, FakeKpi::$calls, 'segar=1 forces recomputation');
    }

    public function test_SRS_DSH_08_filters_reach_every_provider_and_invalid_values_are_rejected(): void
    {
        app(KpiProviders::class)->register(FakeKpi::class);
        $user = $this->user([...self::EKSEKUTIF, 'ptn.laporan.lihat']);
        $this->kpis($user, ['kunci' => 'ptn.risiko_tinggi', 'tahun' => 2025, 'kod_mukim' => '100803', 'kategori' => 'BANGUNAN', 'bahagian' => 'wakaf', 'status' => 'aktif', 'pegawai' => 7]);
        $this->assertSame(['tahun' => 2025, 'kod_mukim' => '100803', 'bahagian' => 'wakaf', 'kategori' => 'BANGUNAN', 'status' => 'aktif', 'pegawai' => 7, 'kod_daerah' => '1008'], FakeKpi::$lastFilters);

        $this->actingAs($user)->getJson('/api/v1/dsh/kpi?kod_daerah=10;drop')->assertStatus(422);
    }
}
