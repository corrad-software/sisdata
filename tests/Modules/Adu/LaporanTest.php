<?php

namespace Tests\Modules\Adu;

use App\Modules\Adu\Gis\AduanMapLayer;
use App\Modules\Adu\Kpi\AduanDalamSla;
use App\Modules\Adu\Kpi\AduanTerbuka;
use App\Modules\Adu\Kpi\PurataMasaSelesai;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\JejakSla;
use App\Modules\Adu\Services\SlaAduan;

class LaporanTest extends AduTestCase
{
    /** 3 open (1 overdue) + 2 closed in Petaling, 1 open in Sepang. */
    private function data(): array
    {
        $lotP = $this->lot('100803');
        $lotS = $this->lot('100901');
        $p = [self::PUSAT_PETALING[0], self::PUSAT_PETALING[1]];
        $open1 = $this->aduan(['lat' => $p[0], 'lng' => $p[1]]);
        $open2 = $this->aduan(['lot_id' => $lotP, 'kategori' => 'PEMBUANGAN_SAMPAH', 'keterangan' => 'Sampah'], 'emel');
        $open3 = $this->aduan(['lot_id' => $lotP, 'kategori' => 'PEMBUANGAN_SAMPAH', 'keterangan' => 'Sampah lagi'], 'telefon');
        $closed1 = $this->aduan(['lot_id' => $lotP]);
        $closed2 = $this->aduan(['lot_id' => $lotP, 'kategori' => 'KEROSAKAN_ASET', 'keterangan' => 'Pagar rosak']);
        $sepang = $this->aduan(['lot_id' => $lotS], 'portal');

        foreach ([[$closed1, 4], [$closed2, 10]] as [$a, $days]) {
            Aduan::query()->whereKey($a->id)->update(['status' => 'ditutup', 'tarikh_terima' => now()->subDays($days), 'tarikh_tutup' => now()]);
            app(SlaAduan::class)->selesai($a);
        }
        // open3 overdue: re-create its SLA cycle in the past.
        app(SlaAduan::class)->mula($open3, now()->subDays(90));

        return compact('open1', 'open2', 'open3', 'closed1', 'closed2', 'sepang', 'lotP');
    }

    public function test_SRS_ADU_26_dashboard_counts_match_the_test_data(): void
    {
        $d = $this->data();
        $user = $this->user(['adu.laporan.lihat']);
        $sum = $this->actingAs($user)->getJson('/api/v1/adu/laporan/ringkasan')->assertOk()->json('data');

        $this->assertSame(6, $sum['jumlah']);
        $this->assertSame(4, $sum['aktif']);
        $this->assertSame(1, $sum['tertangguh']);
        $this->assertSame(2, $sum['diselesaikan']);
        $this->assertEqualsWithDelta(7.0, $sum['purata_hari_selesai'], 0.1);
        $this->assertEquals(100, $sum['peratus_sla_dipenuhi']);
        $this->assertCount(12, $sum['trend']);

        // Filters: district, category, channel, period.
        $this->assertSame(1, $this->actingAs($user)->getJson('/api/v1/adu/laporan/ringkasan?kod_daerah=1009')->json('data.jumlah'));
        $this->assertSame(2, $this->actingAs($user)->getJson('/api/v1/adu/laporan/ringkasan?kategori=PEMBUANGAN_SAMPAH')->json('data.jumlah'));
        $this->assertSame(1, $this->actingAs($user)->getJson('/api/v1/adu/laporan/ringkasan?saluran=portal')->json('data.jumlah'));
        $this->assertSame(4, $this->actingAs($user)->getJson('/api/v1/adu/laporan/ringkasan?dari='.now()->subDay()->toDateString())->json('data.jumlah'));

        // A district officer only counts their district.
        $petaling = $this->user(['adu.laporan.lihat'], ['1008']);
        $this->assertSame(5, $this->actingAs($petaling)->getJson('/api/v1/adu/laporan/ringkasan')->json('data.jumlah'));
    }

    public function test_SRS_ADU_25_reports_by_category_status_district_period_officer_and_export(): void
    {
        $this->data();
        $user = $this->user(['adu.laporan.lihat', 'adu.aduan.eksport']);
        $sla = $this->actingAs($user)->getJson('/api/v1/adu/laporan/prestasi_sla?ikut=kategori')->assertOk()->json('data');
        $this->assertContains('Pencerobohan tanah', array_column($sla, 'label'));
        $this->actingAs($user)->getJson('/api/v1/adu/laporan/prestasi_sla?ikut=pegawai')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/adu/laporan/prestasi_sla?ikut=daerah')->assertOk();
        $this->assertCount(1, $this->actingAs($user)->getJson('/api/v1/adu/laporan/tertangguh')->assertOk()->json('data'));
        $this->actingAs($user)->getJson('/api/v1/adu/laporan/bukan_mais')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/adu/laporan/resolusi')->assertOk();

        foreach (['csv' => 'text/csv', 'xlsx' => 'spreadsheetml', 'pdf' => 'application/pdf'] as $format => $type) {
            $res = $this->actingAs($user)->get("/api/v1/adu/laporan/prestasi_sla/eksport?format={$format}")->assertOk();
            $this->assertStringContainsString($type, (string) $res->headers->get('Content-Type'));
        }
        $this->assertAudited('eksport', ['modul' => 'adu', 'id_pengguna' => $user->id]);
        $this->actingAs($this->user(['adu.laporan.lihat']))->get('/api/v1/adu/laporan/prestasi_sla/eksport')->assertForbidden();
    }

    public function test_SRS_ADU_27_analytics_repeat_lots_hotspots_and_trends(): void
    {
        $d = $this->data();
        $user = $this->user(['adu.laporan.lihat']);
        $repeat = $this->actingAs($user)->getJson('/api/v1/adu/laporan/berulang')->assertOk()->json('data');
        $this->assertSame($d['lotP'], $repeat[0]['lot_id']);
        $this->assertSame(5, $repeat[0]['bilangan']);
        $this->assertNotNull($repeat[0]['no_lot']);

        $hot = $this->actingAs($user)->getJson('/api/v1/adu/laporan/kawasan_panas')->assertOk()->json('data');
        $this->assertSame(['Damansara', 5], [$hot[0]['mukim'], $hot[0]['jumlah']]);
        $this->assertNotNull($hot[0]['lat']);

        $trend = $this->actingAs($user)->getJson('/api/v1/adu/laporan/trend')->assertOk()->json('data');
        $this->assertSame(now()->format('Y-m'), end($trend)['bulan']);
    }

    public function test_SRS_ADU_16_map_layer_shows_scoped_points_coloured_by_category(): void
    {
        $this->data();
        $layer = app(AduanMapLayer::class);
        $this->assertSame(['adu.aduan', 'point', 'risiko'], [$layer->key(), $layer->geometryType(), $layer->group()]);
        $this->assertSame('kategori', $layer->legend()['medan']);

        $all = $layer->features($this->user(['adu.aduan.lihat']));
        $this->assertCount(6, $all['features']);
        $props = $all['features'][0]['properties'];
        foreach (['id', 'label', 'pautan', 'kategori', 'status', 'warna'] as $k) {
            $this->assertArrayHasKey($k, $props);
        }
        $this->actingAs($sepang = $this->user(['adu.aduan.lihat'], ['1009']));
        $this->assertCount(1, $layer->features($sepang)['features'], 'GIS cannot bypass district scope');
        $this->assertCount(0, $layer->features($this->user([]))['features']);
        $this->assertCount(4, $layer->features($this->user(['adu.aduan.lihat']), null, ['status' => 'terbuka'])['features']);
        $bbox = [self::PUSAT_SEPANG[1] - 0.01, self::PUSAT_SEPANG[0] - 0.01, self::PUSAT_SEPANG[1] + 0.01, self::PUSAT_SEPANG[0] + 0.01];
        $this->assertCount(1, $layer->features($this->user(['adu.aduan.lihat']), $bbox)['features']);

        $this->actingAs($this->user(['adu.aduan.lihat']))->getJson('/api/v1/adu/peta')->assertOk()->assertJsonCount(6, 'features');
    }

    public function test_kpis_for_executive_dashboard(): void
    {
        $this->data();
        $user = $this->user(['adu.laporan.lihat']);
        $open = app(AduanTerbuka::class)->compute($user);
        $this->assertSame(4, $open['nilai']);
        $this->assertSame('adu.aduan_terbuka', app(AduanTerbuka::class)->key());
        $this->assertSame(100.0, app(AduanDalamSla::class)->compute($user)['nilai']);
        $this->assertEqualsWithDelta(7.0, app(PurataMasaSelesai::class)->compute($user)['nilai'], 0.1);
        $this->assertSame(1, app(AduanTerbuka::class)->compute($user, ['kod_daerah' => '1009'])['nilai']);
        $this->assertSame(1, app(AduanTerbuka::class)->compute($this->user(['adu.laporan.lihat'], ['1009']))['nilai'], 'KPI respects the viewer\'s district scope');
        $this->assertSame(3, JejakSla::query()->where('semasa', true)->whereNull('masa_selesai_sebenar')->where('masa_sasaran_selesai', '>', now())->count());
    }
}
