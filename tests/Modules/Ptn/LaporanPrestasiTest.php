<?php

namespace Tests\Modules\Ptn;

use App\Modules\Ptn\Kpi\LawatanIkutJadual;
use App\Modules\Ptn\Kpi\LotBelumDiperiksa;
use App\Modules\Ptn\Kpi\LotRisikoMerah;
use App\Modules\Ptn\Kpi\PenemuanTerbuka;
use App\Modules\Ptn\Profile\PemantauanSection;
use App\Modules\Ptn\Services\LawatanSearchProvider;
use Illuminate\Support\Facades\DB;

/** SRS-PTN-20/22/23 + KPIs, profile section, search. */
class LaporanPrestasiTest extends PtnTestCase
{
    public function test_SRS_PTN_22_issue_report_by_location_category_status_officer_and_duration(): void
    {
        $me = $this->user([...self::LAPANGAN, 'ptn.tindakan.kemas_kini'], ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $this->lawatanLengkap($me, $this->jadual($this->lot(), $me), ['item' => $this->item(['FIZ-02' => 'isu', 'SEM-01' => 'isu'])]);
        $isu = DB::table('isu_pemantauan')->where('kategori_isu', 'pembuangan_sampah')->first();
        $this->actingAs($sup)->postJson('/api/v1/ptn/isu/'.$isu->id.'/tindakan', ['langkah_tindakan' => 'Bersih', 'id_pegawai_bertanggungjawab' => $me->id, 'tarikh_sasaran' => now()->addWeek()->toDateString()])->assertCreated();

        $d = $this->actingAs($sup)->getJson('/api/v1/ptn/laporan/isu')->assertOk()->json('data');
        $this->assertSame([['kod' => '1008', 'jumlah' => 2, 'terbuka' => 2, 'purata_hari_selesai' => null, 'label' => 'Petaling']], $d['mengikut_daerah']);
        $this->assertEqualsCanonicalizing(['pembuangan_sampah', 'sempadan'], array_column($d['mengikut_kategori'], 'kod'));
        $this->assertSame($me->name, $d['mengikut_pegawai'][0]['kod']);
        $this->assertCount(2, $d['senarai']);
        $this->assertSame('Damansara', $d['mengikut_mukim'][0]['label']);

        $csv = $this->actingAs($sup)->get('/api/v1/ptn/laporan/isu/eksport?format=csv')->assertOk();
        $this->assertStringContainsString('Mengikut daerah', (string) $csv->getContent());
        $this->assertAudited('eksport', ['modul' => 'ptn']);
        $this->actingAs($this->user(['ptn.laporan.lihat']))->get('/api/v1/ptn/laporan/isu/eksport')->assertForbidden();
        $this->actingAs($sup)->getJson('/api/v1/ptn/laporan/tidakwujud')->assertNotFound();
        foreach (['prestasi', 'tindakan', 'risiko', 'pencerobohan', 'penyelenggaraan'] as $r) {
            $this->actingAs($sup)->getJson('/api/v1/ptn/laporan/'.$r)->assertOk();
            $this->actingAs($sup)->get('/api/v1/ptn/laporan/'.$r.'/eksport?format=pdf')->assertOk();
        }
    }

    public function test_SRS_PTN_23_performance_report_on_time_overdue_interval_and_findings(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $lot = $this->lot();
        $onTime = $this->jadual($lot, $me, now()->toDateString());
        $this->lawatanLengkap($me, $onTime);
        $this->jadual($this->lot(), $me, now()->subDays(10)->toDateString());
        $this->jadual($this->lot(), $me, now()->addMonth()->toDateString());
        DB::table('rekod_lawatan')->where('lot_id', $lot)->update(['tarikh_lawatan' => now()->toDateString()]);

        $d = $this->actingAs($sup)->getJson('/api/v1/ptn/laporan/prestasi?tahun='.now()->year)->assertOk()->json('data');
        $this->assertEquals([3, 2, 1, 50.0, 1], [$d['bil_jadual'], $d['bil_perlu_setakat_ini'], $d['bil_ikut_jadual'], $d['peratus_ikut_jadual'], $d['bil_tertunggak']]);
        $this->assertSame(10, $d['tertunggak'][0]['hari_lewat']);

        $k = app(LawatanIkutJadual::class)->compute($sup);
        $this->assertSame(50.0, $k['nilai']);
        $this->assertSame(2, app(LotBelumDiperiksa::class)->compute($sup)['nilai']);
        $this->assertSame(0, app(PenemuanTerbuka::class)->compute($sup)['nilai']);
        $this->assertSame('lot', app(LotRisikoMerah::class)->compute($sup)['unit']);
    }

    public function test_SRS_PTN_20_current_visit_compared_side_by_side_with_previous_visit(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $lot = $this->lot();
        $first = $this->lawatanLengkap($me, $this->jadual($lot, $me), ['tarikh_lawatan' => now()->subMonths(3)->toDateString()]);
        $second = $this->lawatanLengkap($me, $this->jadual($lot, $me), ['item' => $this->item(['CER-02' => 'kritikal', 'FIZ-01' => 'isu'])]);
        $d = $this->actingAs($sup)->getJson('/api/v1/ptn/lawatan/'.$second->id.'/perbandingan')->assertOk()->json('data');
        $this->assertSame($first->id, $d['sebelum']['id']);
        $rows = collect($d['item'])->keyBy('kod_item');
        $this->assertSame(['baik', 'kritikal', 'merosot'], [$rows['CER-02']['sebelum'], $rows['CER-02']['semasa'], $rows['CER-02']['perubahan']]);
        $this->assertSame('sama', $rows['SEM-01']['perubahan']);
        $this->assertCount(1, $d['gambar_sebelum']);
        $this->assertNotNull($d['gambar_semasa'][0]['imej_kecil']);
    }

    public function test_SRS_PTN_16_lot_card_profile_section_timeline_and_global_search(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $lot = $this->lot();
        $v = $this->lawatanLengkap($me, $this->jadual($lot, $me), ['item' => $this->item(['FIZ-02' => 'isu'])]);
        $s = app(PemantauanSection::class);
        $p = $s->forLot($lot, $sup);
        $this->assertSame(1, $p['items'][0]['isu_terbuka']);
        $this->assertNotEmpty($p['flags']);
        $this->assertStringContainsString('Risiko', $p['flags'][0]['label']);
        $this->assertNotEmpty($s->timeline($lot, $sup));
        $this->assertNull($s->forLot($lot, $this->user(['ptn.isu.lihat'])));
        $hits = app(LawatanSearchProvider::class)->search($v->no_rujukan, $sup);
        $this->assertSame('/admin/ptn/lawatan/'.$v->id, $hits[0]['link']);
        $this->assertSame([], app(LawatanSearchProvider::class)->search($v->no_rujukan, $this->user(self::PENYELIA, ['1009'])));
    }
}
