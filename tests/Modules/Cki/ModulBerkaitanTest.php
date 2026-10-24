<?php

namespace Tests\Modules\Cki;

use App\Modules\Cki\Integration\BilSearchProvider;
use App\Modules\Cki\Integration\Kpi\BayarTepatMasa;
use App\Modules\Cki\Integration\Kpi\CukaiTertunggak;
use App\Modules\Cki\Integration\Kpi\JumlahCukaiTahunan;
use App\Modules\Cki\Integration\Kpi\Penalti;
use App\Modules\Cki\Integration\StatusCukaiLayer;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Profile\CukaiSection;
use App\Modules\Cki\Support\Aliran;
use App\Modules\Wfe\Models\Definisi;
use Database\Seeders\CkiAliranSeeder;
use Illuminate\Support\Facades\DB;

class ModulBerkaitanTest extends CkiTestCase
{
    public function test_SRS_CKI_21_kpis_for_the_executive_dashboard_respect_district_scope(): void
    {
        $state = $this->user();
        $sepang = $this->user(self::PEGAWAI, ['1009']);
        $this->bil(null, ['amaun_pokok' => '100.00', 'status_bayaran' => 'dibayar', 'tarikh_akhir' => '2026-05-31', 'tarikh_bayar' => '2026-05-30']);
        $this->bil(null, ['amaun_pokok' => '300.00', 'amaun_denda' => '30.00', 'tarikh_akhir' => '2026-05-31']);
        $this->bil($this->obligasi('tanah', null, null, '100901'), ['amaun_pokok' => '50.00', 'tarikh_akhir' => '2026-12-31']);

        $this->assertSame(480.0, app(JumlahCukaiTahunan::class)->compute($state)['nilai']);
        $this->assertSame(330.0, app(CukaiTertunggak::class)->compute($state)['nilai']);
        $this->assertSame(50.0, app(BayarTepatMasa::class)->compute($state)['nilai']);     // 1 of 2 due bills paid on time
        $this->assertSame(30.0, app(Penalti::class)->compute($state)['nilai']);
        $this->assertSame(50.0, app(JumlahCukaiTahunan::class)->compute($sepang)['nilai']);
        $this->assertSame(0.0, app(CukaiTertunggak::class)->compute($sepang)['nilai']);
        $this->assertSame('cki.laporan.lihat', app(Penalti::class)->permission());
    }

    public function test_SRS_CKI_21_profile_section_shows_tax_position_timeline_and_kos_cukai_series(): void
    {
        $user = $this->user();
        $lot = $this->lot();
        $o = $this->obligasi('tanah', $lot);
        $this->bil($o, ['tahun_taksiran' => 2025, 'no_bil' => 'P1', 'amaun_pokok' => '120.00', 'tarikh_akhir' => '2025-05-31', 'status_bayaran' => 'dibayar', 'tarikh_bayar' => '2025-05-02']);
        $this->bil($o, ['tahun_taksiran' => 2026, 'no_bil' => 'P2', 'amaun_pokok' => '130.00', 'tarikh_akhir' => '2026-05-31']);
        $s = app(CukaiSection::class);
        $this->assertSame(['cukai', 'kewangan'], [$s->key(), $s->tab()]);
        $block = $s->forLot($lot, $user);
        $this->assertStringContainsString('2026', $block['items'][0]['label']);
        $this->assertSame('danger', $block['flags'][0]['level']);
        $this->assertSame([['tarikh' => '2025-05-02', 'jenis' => 'kos_cukai', 'amaun' => 120.0]], array_map(fn ($e) => array_intersect_key($e, ['tarikh' => 1, 'jenis' => 1, 'amaun' => 1]), $s->financialSeries($lot, $user)));
        $this->assertContains('cki.bayar', array_column($s->timeline($lot, $user), 'type'));
        $this->assertNull($s->forLot($lot, $this->user(['pro.lot.lihat'])));
        $this->assertNull($s->forLot($lot, $this->user(self::PEGAWAI, ['1009'])));

        // Through MOD-PRO's 360° profile API.
        $viewer = $this->user([...self::PEGAWAI, 'pro.lot.lihat']);
        $tabs = collect($this->actingAs($viewer)->getJson('/api/v1/pro/lot/'.$lot.'/profil')->assertOk()->json('data.tab'))->keyBy('kod');
        $section = collect($tabs['kewangan']['seksyen'])->firstWhere('key', 'cukai');
        $this->assertSame('ada', $section['status']);
    }

    public function test_SRS_CKI_21_gis_status_layer_colours_lots_by_payment_status_and_respects_scope(): void
    {
        $user = $this->user();
        $paidLot = $this->lot([], true);
        $lateLot = $this->lot([], true);
        $veryLate = $this->lot([], true);
        $this->bil($this->obligasi('tanah', $paidLot), ['status_bayaran' => 'dibayar', 'tarikh_akhir' => '2026-05-31']);
        $this->bil($this->obligasi('tanah', $lateLot), ['tarikh_akhir' => '2026-05-31']);
        $this->bil($this->obligasi('tanah', $veryLate), ['tahun_taksiran' => 2024, 'tarikh_akhir' => '2024-05-31']);
        $layer = app(StatusCukaiLayer::class);
        $this->assertStringStartsWith('cki.', $layer->key());
        $this->assertSame(['selesai', 'belum_dibayar', 'tertunggak', 'tertunggak_ambang'], array_column($layer->legend()['entri'], 'nilai'));
        $status = collect($layer->features($user)['features'])->mapWithKeys(fn ($f) => [$f['properties']['id'] => $f['properties']['status_cukai']]);
        $this->assertSame(['selesai', 'tertunggak', 'tertunggak_ambang'], [$status[$paidLot], $status[$lateLot], $status[$veryLate]]);
        $this->assertSame([], $layer->features($this->user(self::PEGAWAI, ['1009']))['features']);
        $this->assertCount(1, $layer->features($user, null, ['status' => 'selesai'])['features']);

        // Tile source SQL runs and carries the same columns.
        $src = $layer->tileSource($user);
        $rows = DB::select('SELECT * FROM ('.$src['sql'].') s', $src['bindings']);
        $this->assertCount(3, $rows);
        $this->assertNotSame($layer->scopeSignature($user), $layer->scopeSignature($this->user(self::PEGAWAI, ['1009'])));

        // Registered with MOD-GIS (fills the "Status Cukai" slot).
        $gisUser = $this->user([...self::PEGAWAI, 'gis.peta.lihat']);
        $res = $this->actingAs($gisUser)->getJson('/api/v1/gis/lapisan')->assertOk();
        $this->assertContains('cki.status_cukai', collect($res->json('lapisan'))->pluck('kod')->all());
        $this->assertStringContainsString('cki.status_cukai', json_encode($res->json('wajib')));
        $ciri = $this->actingAs($gisUser)->getJson('/api/v1/gis/lapisan/cki.status_cukai/ciri')->assertOk();
        $this->assertCount(3, $ciri->json('features'));
    }

    public function test_SRS_CKI_02_global_search_finds_bills_by_bill_account_or_lot_number_within_scope(): void
    {
        $user = $this->user();
        $lot = $this->lot(['no_lot' => 'PT 4242']);
        $bill = $this->bil($this->obligasi('tanah', $lot, 'CT-SEARCH-9'), ['no_bil' => 'TA-2026-XYZ']);
        $p = app(BilSearchProvider::class);
        foreach (['XYZ', 'SEARCH-9', '4242'] as $q) {
            $this->assertSame($bill->id, $p->search($q, $user)[0]['id'] ?? null, $q);
        }
        $this->assertSame([], $p->search('XYZ', $this->user(self::PEGAWAI, ['1009'])));
        $this->assertSame([], $p->search('XYZ', $this->user(['pro.lot.lihat'])));
    }

    public function test_SRS_CKI_14_flow_is_declared_and_seeded_in_the_workflow_engine(): void
    {
        $this->user();
        $this->seed(CkiAliranSeeder::class);
        $def = Definisi::query()->where('kod_aliran', Aliran::BAYARAN)->sole();
        $this->assertSame(Definisi::AKTIF, $def->status);
        $this->seed(CkiAliranSeeder::class); // idempotent
        $this->assertSame(1, Definisi::query()->where('kod_aliran', Aliran::BAYARAN)->count());
    }

    public function test_SRS_CKI_05_sample_command_generates_flagged_synthetic_bills_for_existing_lots(): void
    {
        foreach (range(1, 12) as $_) {
            $this->lot();
        }
        $this->artisan('cki:sample')->assertSuccessful();
        $bills = Cukai::query()->withoutGlobalScope('access')->get();
        $this->assertTrue($bills->every(fn (Cukai $c) => $c->sintetik));
        $this->assertTrue(Obligasi::query()->withoutGlobalScope('access')->get()->every(fn (Obligasi $o) => $o->sintetik));
        $this->assertEqualsCanonicalizing([2024, 2025, 2026], $bills->pluck('tahun_taksiran')->unique()->values()->all());
        $this->assertTrue($bills->contains('status_bayaran', 'dibayar'));
        $this->assertTrue($bills->contains('status_bayaran', 'tertunggak'));
        $count = $bills->count();
        $this->artisan('cki:sample')->assertSuccessful();          // idempotent
        $this->assertSame($count, Cukai::query()->withoutGlobalScope('access')->count());
    }
}
