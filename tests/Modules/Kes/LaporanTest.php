<?php

namespace Tests\Modules\Kes;

use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\PanelPeguam;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Services\KeputusanService;
use App\Modules\Kes\Services\KesService;
use App\Modules\Kes\Services\KosService;
use App\Modules\Kes\Services\PanelService;
use Illuminate\Support\Facades\DB;

class LaporanTest extends KesTestCase
{
    /** @return array{0: string, 1: string, 2: string} officer ids a/b + case id in Sepang */
    private function portfolio(): array
    {
        $a = $this->user();
        $b = $this->user();
        $k1 = $this->kes([$this->lot('100803', 'BANGUNAN')], null, ['id_pegawai_bertanggungjawab' => $a->id]);
        $k2 = $this->kes([$this->lot('100901', 'PERTANIAN')], null, ['id_pegawai_bertanggungjawab' => $b->id, 'jenis' => 'faraid', 'mahkamah' => 'Mahkamah Tinggi Syariah Shah Alam', 'peringkat_mahkamah' => 'syariah_tinggi']);
        app(PanelService::class)->lantik($k2, PanelPeguam::query()->create(['nama_firma' => 'Tetuan Panel Faraid', 'status' => 'aktif']), [], $a, true);
        $k3 = $this->kes([$this->lot('100803', 'PERTANIAN')], null, ['id_pegawai_bertanggungjawab' => $a->id, 'tarikh_pemfailan' => now()->subYears(2)->toDateString()]);
        app(KesService::class)->tukarStatus($k3, 'selesai', $a);

        return [(string) $a->id, (string) $b->id, $k2->id];
    }

    public function test_SRS_KES_21_reports_filter_and_group_by_every_listed_dimension(): void
    {
        [$a, $b, $k2] = $this->portfolio();
        $ketua = $this->user(KesTestCase::KETUA);
        $list = fn (array $q) => collect($this->actingAs($ketua)->getJson('/api/v1/kes/laporan/senarai?'.http_build_query($q))->assertOk()->json('data'));
        $this->assertCount(3, $list([]));
        $this->assertCount(2, $list(['aktif' => 1]));
        $this->assertCount(1, $list(['status' => 'selesai']));
        $this->assertSame([$k2], $list(['jenis' => 'faraid'])->pluck('id')->all());
        $this->assertSame([$k2], $list(['mahkamah' => 'Syariah'])->pluck('id')->all());
        $this->assertSame([$k2], $list(['kod_daerah' => '1009'])->pluck('id')->all());
        $this->assertSame([$k2], $list(['kod_mukim' => '100901'])->pluck('id')->all());
        $this->assertCount(2, $list(['kategori_tanah' => 'PERTANIAN']));
        $this->assertCount(2, $list(['id_pegawai' => $a]));
        $this->assertSame([$k2], $list(['peguam' => 'Panel Faraid'])->pluck('id')->all());
        $this->assertCount(2, $list(['dari' => now()->subYear()->toDateString(), 'hingga' => now()->toDateString()]));

        $group = fn (string $by) => collect($this->actingAs($ketua)->getJson('/api/v1/kes/laporan/ringkasan?kumpul='.$by)->assertOk()->json('data'))->keyBy('kod');
        $this->assertSame(2, $group('daerah')['1008']['bil']);
        $this->assertSame(1, $group('daerah')['1008']['aktif']);
        $this->assertSame(2, $group('kategori_tanah')['PERTANIAN']['bil']);
        $this->assertSame(2, $group('pegawai')[$a]['bil']);
        $this->assertSame(1, $group('pegawai')[$b]['bil']);
        $this->assertSame('500001.00', $group('jenis')['pencerobohan']['nilai_tuntutan']);
        foreach (['status', 'mahkamah', 'peringkat', 'mukim', 'peguam', 'tahun'] as $by) {
            $this->assertNotEmpty($group($by));
        }
    }

    public function test_SRS_KES_22_dashboard_status_type_upcoming_value_and_costs(): void
    {
        $this->portfolio();
        $k = KesMahkamah::query()->withoutGlobalScope('access')->where('jenis', 'faraid')->firstOrFail();
        Prosiding::query()->create(['kes_mahkamah_id' => $k->id, 'tarikh_prosiding' => now()->addDays(6), 'jenis_prosiding' => 'bicara', 'status' => 'dijadualkan']);
        app(KosService::class)->tambah($k, ['kategori_kos' => 'fi_peguam', 'amaun' => '2500.25', 'tarikh' => now()->toDateString()], $this->user());
        $lotId = DB::table('kes_mahkamah_lot')->where('kes_mahkamah_id', $k->id)->value('lot_id');
        DB::table('penilaian')->insert(['id' => (string) str()->uuid(), 'lot_id' => $lotId, 'tarikh_penilaian' => '2025-01-01', 'nilai_pasaran' => 750000, 'versi' => 1, 'klasifikasi' => 'rahsia', 'created_at' => now(), 'updated_at' => now()]);

        $d = $this->actingAs($this->user())->getJson('/api/v1/kes/dashboard')->assertOk()->json('data');
        $this->assertSame(2, $d['bil_aktif']);
        $this->assertLessThanOrEqual(6, $d['prosiding_hampir'][0]['baki_hari']);
        $this->assertSame('2500.25', $d['kos_terkumpul']);
        $this->assertSame(2, $d['bil_lot_terlibat']);
        $this->assertFalse($d['nilai_lot']['boleh_lihat'], 'valuation is Rahsia of MOD-PRO');
        $this->assertNotEmpty($d['mengikut_status']);
        $this->assertNotEmpty($d['mengikut_jenis']);
        $d2 = $this->actingAs($this->user([...KesTestCase::PEGAWAI, 'pro.rahsia.lihat']))->getJson('/api/v1/kes/dashboard')->json('data');
        $this->assertSame('750000.00', $d2['nilai_lot']['nilai']);
        $this->assertDatabaseHas('kes_log_capaian', ['jenis_tindakan' => 'lihat_senarai', 'keterangan' => 'Dashboard kes']);
    }

    public function test_SRS_KES_23_portfolio_legal_exposure(): void
    {
        $this->portfolio();
        $u = $this->user([...KesTestCase::PEGAWAI, 'pro.rahsia.lihat']);
        $k = KesMahkamah::query()->withoutGlobalScope('access')->where('jenis', 'pencerobohan')->where('status', '!=', 'selesai')->firstOrFail();
        app(KosService::class)->tambah($k, ['kategori_kos' => 'kos_mahkamah', 'amaun' => '300', 'tarikh' => now()->subYear()->toDateString()], $u);
        app(KosService::class)->tambah($k, ['kategori_kos' => 'fi_peguam', 'amaun' => '700', 'tarikh' => now()->toDateString()], $u);
        app(KeputusanService::class)->rekod($k, ['jenis_keputusan' => 'menang', 'tarikh_keputusan' => now()->toDateString(), 'ringkasan_perintah' => 'x', 'tindakan' => []], $u);

        $d = $this->actingAs($u)->getJson('/api/v1/kes/laporan/pendedahan')->assertOk()->json('data');
        $this->assertSame(2, $d['bil_kes_aktif']);
        $this->assertTrue($d['nilai_lot']['boleh_lihat']);
        $this->assertSame('500001.00', $d['nilai_tuntutan_aktif'], 'claims of active cases');
        $this->assertCount(2, $d['kos_mengikut_tahun']);
        $this->assertSame('menang', $d['trend_keputusan'][0]['jenis_keputusan']);
        $this->assertSame('Pencerobohan', $d['trend_keputusan'][0]['jenis_kes_nama']);
    }

    public function test_SRS_KES_24_reports_export_to_pdf_and_excel_with_audit(): void
    {
        $this->portfolio();
        $u = $this->user();
        foreach (['pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'] as $format => $type) {
            foreach (['senarai', 'ringkasan', 'kos', 'pendedahan', 'kalendar'] as $jenis) {
                $res = $this->actingAs($u)->get('/api/v1/kes/laporan/'.$jenis.'/eksport?format='.$format.'&kod_daerah=1008&kumpul=jenis')->assertOk();
                $this->assertSame($type, $res->headers->get('Content-Type'));
            }
        }
        $this->assertAudited('eksport', ['modul' => 'kes']);
        $this->assertSame(10, DB::table('kes_log_capaian')->where('jenis_tindakan', 'eksport')->where('id_pengguna', $u->id)->count());

        $this->actingAs($this->user(['kes.kes.lihat', 'kes.rahsia.lihat', 'kes.laporan.lihat']))->get('/api/v1/kes/laporan/senarai/eksport?format=pdf')->assertForbidden();
        $this->actingAs($this->user(['kes.laporan.lihat', 'kes.laporan.eksport']))->get('/api/v1/kes/laporan/senarai/eksport?format=pdf')->assertForbidden();
    }
}
