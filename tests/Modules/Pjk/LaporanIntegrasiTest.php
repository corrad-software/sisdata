<?php

namespace Tests\Modules\Pjk;

use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Pjk\Integration\KontrakSearchProvider;
use App\Modules\Pjk\Integration\Kpi\KadarKutipan;
use App\Modules\Pjk\Integration\Kpi\KadarPenghunian;
use App\Modules\Pjk\Integration\Kpi\KontrakHampirTamat;
use App\Modules\Pjk\Integration\Kpi\Tunggakan;
use App\Modules\Pjk\Integration\PajakanMapLayer;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\PjkServiceProvider;
use App\Modules\Pjk\Profile\PajakanSection;
use App\Modules\Pjk\Services\BayaranService;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pro\Contracts\FinancialSeries;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowFlows;
use App\Modules\Wfe\Services\FlowRegistry;
use Tests\Modules\Pjk\Support\RecordingRegistry;

class LaporanIntegrasiTest extends PjkTestCase
{
    private function pay(string $kontrakId, string $amount, string $date): void
    {
        $b = Bayaran::query()->create(['no_bayaran' => Penomboran::next('bayaran'), 'kontrak_id' => $kontrakId, 'saluran' => 'kaunter', 'amaun' => $amount, 'tarikh_bayaran' => $date, 'status' => 'menunggu_pengesahan']);
        app(BayaranService::class)->settle($b, null, false);
    }

    public function test_SRS_PJK_26_occupancy_dashboard_by_district_and_category_with_vacancy_and_potential_revenue(): void
    {
        $user = $this->user([...self::PENDAFTAR, 'pjk.laporan.lihat']);
        $this->active($user);
        $this->premis(['tarikh_status' => '2026-01-01', 'kadar_tawaran' => '1000.00']);           // vacant 287 days
        $this->premis(['tarikh_status' => '2026-10-01', 'kadar_tawaran' => '500.00']);            // vacant 14 days
        $this->premis(['status_premis' => 'penyelenggaraan', 'kod_mukim' => '100901']);

        $r = $this->actingAs($user)->getJson('/api/v1/pjk/laporan/penghunian')->assertOk()->json('data.ringkasan');
        $this->assertSame([4, 1, 2], [$r['jumlah_premis'], $r['disewa'], $r['kosong']]);
        $this->assertSame('33.3', $r['kadar_penghunian']);
        $this->assertSame(150, $r['purata_hari_kosong']);
        $this->assertCount(1, $r['kosong_melebihi_ambang']);
        $this->assertSame('1500.00', $r['hasil_berpotensi_sebulan']);

        $dash = $this->actingAs($user)->getJson('/api/v1/pjk/ringkasan')->assertOk()->json('data');
        $this->assertSame(['pjk.kadar_kutipan', 'pjk.tunggakan', 'pjk.kadar_penghunian', 'pjk.kontrak_hampir_tamat'], array_column($dash['kpi'], 'kod'));
        $this->actingAs($this->user(self::PENDAFTAR))->getJson('/api/v1/pjk/ringkasan')->assertForbidden();
    }

    public function test_SRS_PJK_27_reports_by_status_location_category_tenant_with_pdf_and_excel_export(): void
    {
        $user = $this->user([...self::PENDAFTAR, 'pjk.laporan.lihat', 'pjk.laporan.eksport']);
        $k = $this->active($user, null, ['tarikh_mula' => '2026-09-01', 'tarikh_tamat' => '2027-08-31']);
        $this->pay($k->id, '1500.00', '2026-09-05');
        $sepang = $this->active($user, null, ['premis_id' => $this->premis(['kod_mukim' => '100901'])->id]);

        $rep = $this->actingAs($user)->getJson('/api/v1/pjk/laporan/kontrak?kod_daerah=1008')->assertOk()->json('data');
        $this->assertCount(1, $rep['baris']);
        $this->assertSame(['RM 1,500.00', 'RM 0.00'], [$rep['baris'][0][10], $rep['baris'][0][11]]); // hasil; Sep paid, Oct due today, deposit due 29 Oct
        $this->assertSame(2, $this->actingAs($user)->getJson('/api/v1/pjk/laporan/kontrak')->json('data.ringkasan.bil_kontrak'));

        $age = $this->actingAs($user)->getJson('/api/v1/pjk/laporan/tunggakan')->assertOk()->json('data.ringkasan');
        $this->assertArrayHasKey('1009', $age['mengikut_daerah']);
        $this->assertArrayNotHasKey('1008', $age['mengikut_daerah']);
        $kut = $this->actingAs($user)->getJson('/api/v1/pjk/laporan/kutipan?dari=2026-09-01&hingga=2026-10-31')->assertOk()->json('data.ringkasan');
        $this->assertSame('1500.00', $kut['jumlah']);
        foreach (['hampir_tamat', 'deposit', 'agihan', 'rekonsiliasi', 'pindaan'] as $jenis) {
            $this->actingAs($user)->getJson("/api/v1/pjk/laporan/{$jenis}")->assertOk()->assertJsonStructure(['data' => ['tajuk', 'kepala', 'baris', 'ringkasan']]);
        }
        $this->actingAs($user)->getJson('/api/v1/pjk/laporan/tiada')->assertNotFound();

        $pdf = $this->actingAs($user)->get('/api/v1/pjk/laporan/kontrak/eksport?format=pdf')->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $xlsx = $this->actingAs($user)->get('/api/v1/pjk/laporan/tunggakan/eksport?format=xlsx')->assertOk();
        $this->assertStringStartsWith('PK', (string) $xlsx->getContent());
        $this->assertAudited('eksport', ['modul' => 'pjk', 'nama_entiti' => 'laporan', 'id_rekod' => 'tunggakan']);
        $this->actingAs($this->user(['pjk.laporan.lihat']))->get('/api/v1/pjk/laporan/kontrak/eksport')->assertForbidden();

        // Scope: a Sepang officer reports only on Sepang.
        $sp = $this->user(['pjk.laporan.lihat'], ['1009']);
        $rows = $this->actingAs($sp)->getJson('/api/v1/pjk/laporan/kontrak')->json('data.baris');
        $this->assertSame([$sepang->no_kontrak], array_column($rows, 0));
    }

    public function test_SRS_PJK_27_profile_section_and_financial_series_feed_the_360_profile(): void
    {
        $user = $this->user();
        $k = $this->active($user, null, ['tarikh_mula' => '2026-09-01', 'tarikh_tamat' => '2027-08-31']);
        $this->pay($k->id, '1500.00', '2026-09-05');
        $this->pay($k->id, '4500.00', '2026-10-05'); // 1500 rent + 3000 deposit (deposit is not revenue)

        $section = app(PajakanSection::class);
        $this->assertInstanceOf(FinancialSeries::class, $section);
        $this->assertSame(['pajakan', 'pemanfaatan'], [$section->key(), $section->tab()]);
        $data = $section->forLot($k->lot_id, $user);
        $this->assertNotNull($data);
        $this->assertSame($k->no_kontrak, $data['items'][0]['no_kontrak']);
        $series = $section->financialSeries($k->lot_id, $user);
        $this->assertSame([['2026-09-05', 'hasil_sewaan', 1500.0], ['2026-10-05', 'hasil_sewaan', 1500.0]], array_map(fn ($e) => [$e['tarikh'], $e['jenis'], $e['amaun']], $series));
        $this->assertNotEmpty($section->timeline($k->lot_id, $user));

        $this->assertNull($section->forLot($k->lot_id, $this->user(['pjk.kontrak.lihat'], ['1009'])));
        $this->assertSame([], $section->financialSeries($k->lot_id, $this->user(['pro.lot.lihat'])));

        // Through MOD-PRO's real 360° profile.
        $pro = $this->user(['pro.lot.lihat', 'pjk.kontrak.lihat']);
        $profile = $this->actingAs($pro)->getJson("/api/v1/pro/lot/{$k->lot_id}/profil")->assertOk()->json('data');
        $tab = collect($profile['tab'])->firstWhere('kod', 'pemanfaatan');
        $this->assertSame('ada', collect($tab['seksyen'])->firstWhere('key', 'pajakan')['status']);
    }

    public function test_SRS_PJK_26_map_layer_and_dashboard_kpis_are_registered_and_scoped(): void
    {
        $layers = new RecordingRegistry;
        $kpis = new RecordingRegistry;
        $this->app->instance(MapLayers::class, $layers);
        $this->app->instance(KpiProviders::class, $kpis);
        (new PjkServiceProvider($this->app))->boot();
        app(MapLayers::class);
        app(KpiProviders::class);
        $this->assertContains(PajakanMapLayer::class, $layers->registered);
        $this->assertSame([KadarKutipan::class, Tunggakan::class, KadarPenghunian::class, KontrakHampirTamat::class], array_values(array_intersect($kpis->registered, [KadarKutipan::class, Tunggakan::class, KadarPenghunian::class, KontrakHampirTamat::class])));

        $user = $this->user();
        $lot = $this->lot([], true);
        $k = $this->active($user, null, ['premis_id' => $this->premis([], $lot)->id]);
        $this->premis([], $this->lot([], true)); // vacant

        $layer = app(PajakanMapLayer::class);
        $this->assertSame(['pjk.pajakan', 'polygon', 'status_pajakan'], [$layer->key(), $layer->geometryType(), $layer->legend()['medan']]);
        $fc = $layer->features($user);
        $this->assertCount(2, $fc['features']);
        $mine = collect($fc['features'])->firstWhere('properties.id', $lot);
        $this->assertSame('tunggakan', $mine['properties']['status_pajakan']); // Jan–Oct unpaid
        $this->assertSame('MultiPolygon', $mine['geometry']['type']);
        $this->assertSame('/admin/pro/lot/'.$lot.'?tab=pemanfaatan', $mine['properties']['pautan']);
        $this->assertCount(1, $layer->features($user, null, ['status' => 'kosong'])['features']);
        $this->assertCount(0, $layer->features($user, [100.0, 2.0, 100.1, 2.1])['features']);
        $this->assertCount(0, $layer->features($this->user(['pjk.kontrak.lihat'], ['1009']))['features']);
        $this->assertCount(0, $layer->features($this->user(['pjk.premis.lihat']))['features']);

        $kpiUser = $this->user(['pjk.laporan.lihat']);
        $t = app(Tunggakan::class)->compute($kpiUser);
        $this->assertSame(['RM', 13500.0], [$t['unit'], $t['nilai']]); // Jan–Sep overdue; Oct due today; deposit due 29 Oct
        $this->assertSame(0.0, app(Tunggakan::class)->compute($this->user(['pjk.laporan.lihat'], ['1009']))['nilai']);
        $this->assertSame(50.0, app(KadarPenghunian::class)->compute($kpiUser)['nilai']);
        $this->assertSame(0.0, app(KadarKutipan::class)->compute($kpiUser)['nilai']);
        $this->assertSame(0, app(KontrakHampirTamat::class)->compute($kpiUser)['nilai']);
        $this->assertSame('/admin/pjk/kontrak/'.$k->id, app(KontrakSearchProvider::class)->search((string) $k->no_kontrak, $user)[0]['link']);
        $this->assertSame([], app(KontrakSearchProvider::class)->search((string) $k->no_kontrak, $this->user(['pjk.kontrak.lihat'], ['1009'])));
    }

    public function test_SRS_PJK_05_permissions_flows_and_notification_types_are_declared(): void
    {
        $perms = app(PermissionRegistry::class)->all();
        $this->assertSame(array_keys(PjkServiceProvider::PERMISSIONS), array_keys($perms['pjk']['permissions']));
        $flows = app(WorkflowFlows::class);
        $this->assertInstanceOf(FlowRegistry::class, $flows);
        foreach (Aliran::FLOWS as $flow => [, $subject]) {
            $this->assertNotNull($flows->get($flow), $flow);
        }
        $this->assertSame('/admin/pjk/kontrak/abc', app(SubjectLinks::class)->linkFor('kontrak_pajakan', 'abc'));
        $this->actingAs($this->user(['pjk.kontrak.lihat']))->getJson('/api/v1/pjk/rujukan')->assertOk()->assertJsonPath('data.gateway.ujian', true)->assertJsonCount(5, 'data.kategori_hartanah');
    }
}
