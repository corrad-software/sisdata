<?php

namespace Tests\Modules\Tkt;

use App\Modules\Dsh\Contracts\KpiProviders;
use App\Modules\Tkt\Kpi\PematuhanSla;
use App\Modules\Tkt\Kpi\TiketTerbuka;
use App\Modules\Tkt\Models\AktivitiSokongan;
use App\Modules\Tkt\Models\LaporanBulanan;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\TiketService;
use Illuminate\Support\Carbon;

class LaporanTest extends TktTestCase
{
    private const PENGURUS = ['tkt.laporan.lihat', 'tkt.laporan.eksport', 'tkt.aktiviti.cipta'];

    /** Three tickets in Nov 2026: S2 met, S3 missed, S1 still open. */
    private function data(): array
    {
        $tech = $this->ahli();
        $this->isnin('09:00');
        $met = $this->tiket(['keterukan' => 2, 'tajuk' => 'Invois tidak dijana', 'modul' => 'pjk']);
        $missed = $this->tiket(['keterukan' => 3, 'tajuk' => 'Peta tidak dipaparkan', 'modul' => 'gis']);
        $open = $this->tiket(['keterukan' => 1, 'tajuk' => 'Sistem terhenti', 'modul' => 'umum']);
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:20', 'Asia/Kuala_Lumpur'));
        $this->selesai($met, $tech);
        Carbon::setTestNow(Carbon::parse('2026-11-20 09:00', 'Asia/Kuala_Lumpur'));
        app(TiketService::class)->tukarStatus($missed, 'disiasat', $tech);
        $missed->forceFill(['selesai_dilepasi' => true])->save();
        app(TiketService::class)->tukarStatus($missed, 'selesai', $tech, null, ['punca_akar' => 'Pelayan GIS', 'penyelesaian' => 'Mula semula', 'sebab_lepas_sla' => 'Menunggu vendor', 'tindakan_pembetulan' => 'Pantau GeoServer']);

        return [$met, $missed, $open];
    }

    public function test_SRS_TKT_17_dashboard_open_by_severity_and_module_sla_trend_and_drill_down(): void
    {
        [$met, $missed, $open] = $this->data();
        $user = $this->user([...self::PENGURUS, 'tkt.semua.pantau']);
        $d = $this->actingAs($user)->getJson('/api/v1/tkt/laporan/papan-pemuka?dari=2026-11-01&hingga=2026-11-30')->assertOk()->json('data');

        $this->assertSame(1, $d['terbuka']);
        $this->assertSame([1, 0, 0, 0], array_column($d['terbuka_mengikut_keterukan'], 'nilai'));
        $this->assertSame('umum', $d['terbuka_mengikut_modul'][0]['kod']);
        $this->assertEquals([2, 1, 50.0], [$d['sla']['bilangan'], $d['sla']['selesai_dipenuhi'], $d['sla']['peratus_selesai']]);
        $this->assertNotNull($d['mttr_jam']);
        $this->assertCount(12, $d['trend']);
        $this->assertSame(3, collect($d['trend'])->firstWhere('tempoh', '2026-11')['dibuka']);

        // Drill-down to the list and the record.
        $rows = $this->actingAs($user)->getJson('/api/v1/tkt/tiket?status=terbuka&keterukan=1')->assertOk()->json('data');
        $this->assertSame([$open->id], array_column($rows, 'id'));
        $this->actingAs($user)->getJson("/api/v1/tkt/tiket/{$open->id}")->assertOk();
        $this->actingAs($this->user(['tkt.tiket.lihat']))->getJson('/api/v1/tkt/laporan/papan-pemuka')->assertForbidden();
    }

    public function test_SRS_TKT_19_monthly_report_exports_pdf_and_excel_with_matching_sla_figures(): void
    {
        $this->data();
        $user = $this->user(self::PENGURUS);
        $this->actingAs($user)->postJson('/api/v1/tkt/aktiviti', ['jenis' => 'tampalan_keselamatan', 'tarikh' => '2026-11-10', 'tajuk' => 'Tampalan PHP'])->assertCreated();
        $r = $this->actingAs($user)->getJson('/api/v1/tkt/laporan/bulanan?bulan=2026-11')->assertOk()->json('data');

        $this->assertEquals([3, 2, 50.0], [$r['ringkasan']['dibuka'], $r['ringkasan']['diselesaikan'], $r['ringkasan']['peratus_selesai']]);
        $titles = array_column($r['seksyen'], 'tajuk');
        foreach (['kategori dan severity', 'Pencapaian SLA', 'melepasi SLA', 'Masa henti', 'Tampalan keselamatan', 'Penyelenggaraan pencegahan', 'masalah berulang', 'Tindakan pembetulan', 'pemindahan teknologi', 'eskalasi'] as $needle) {
            $this->assertNotEmpty(array_filter($titles, fn ($t) => str_contains($t, $needle)), "section {$needle} (§18.6)");
        }
        $breached = collect($r['seksyen'])->first(fn ($s) => str_contains($s['tajuk'], 'melepasi SLA'));
        $this->assertSame('Menunggu vendor', $breached['baris'][0][5]);

        $pdf = $this->actingAs($user)->get('/api/v1/tkt/laporan/bulanan/eksport?bulan=2026-11&format=pdf')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $xlsx = $this->actingAs($user)->get('/api/v1/tkt/laporan/bulanan/eksport?bulan=2026-11&format=xlsx')->assertOk();
        $this->assertStringStartsWith('PK', (string) $xlsx->getContent());
        $this->assertAudited('eksport', ['modul' => 'tkt', 'id_pengguna' => $user->id]);
        $this->actingAs($this->user(['tkt.laporan.lihat']))->get('/api/v1/tkt/laporan/bulanan/eksport?bulan=2026-11')->assertForbidden();
    }

    public function test_SRS_TKT_19_report_is_generated_automatically_and_filed_in_dms(): void
    {
        $this->data();
        Carbon::setTestNow(Carbon::parse('2026-12-01 07:00', 'Asia/Kuala_Lumpur'));
        $this->artisan('tkt:laporan-bulanan')->assertSuccessful();
        $row = LaporanBulanan::query()->where('bulan', '2026-11')->firstOrFail();
        $this->assertSame(50.0, (float) $row->ringkasan['peratus_selesai']);
        $this->assertNotNull($row->id_dokumen_pdf);
        $this->assertNotNull($row->id_dokumen_xlsx);
        $this->assertSame(['tkt_laporan_bulanan'], array_values(array_unique(array_column($this->documents->docs, 'owner_type'))));
        $this->assertCount(1, $this->notifier->of('tkt.laporan_bulanan'));
        $this->assertSame(AktivitiSokongan::query()->count(), 0);
    }

    public function test_SRS_TKT_17_kpis_registered_with_the_executive_dashboard(): void
    {
        [, , $open] = $this->data();
        app(KpiProviders::class);
        $user = $this->user(['tkt.laporan.lihat', 'tkt.semua.pantau']);
        $kpi = app(TiketTerbuka::class)->compute($user);
        $this->assertSame(1, $kpi['nilai']);
        $this->assertSame('keterukan:1', $kpi['pecahan'][0]['kod']);
        $rows = app(TiketTerbuka::class)->records($user, [], 'keterukan:1');
        $this->assertSame('/admin/tkt/tiket/'.$open->id, $rows['baris'][0]['pautan']);
        $this->assertSame(0, app(TiketTerbuka::class)->records($this->user(['tkt.laporan.lihat']), [], null)['jumlah'], 'drill-down respects ticket access');
        $this->assertSame(50.0, app(PematuhanSla::class)->compute($user, ['tahun' => 2026])['nilai']);
        $this->assertSame(3, Tiket::query()->count());
    }
}
