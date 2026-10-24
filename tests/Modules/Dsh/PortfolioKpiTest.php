<?php

namespace Tests\Modules\Dsh;

use Illuminate\Support\Facades\DB;

class PortfolioKpiTest extends DshTestCase
{
    private function portfolio(): array
    {
        $a = $this->lot(['kod_mukim' => '100803', 'keluasan' => 2, 'unit_keluasan' => 'hektar', 'kategori_guna_tanah' => 'BANGUNAN', 'bahagian_pemilik' => 'wakaf']);
        $b = $this->lot(['kod_mukim' => '100803', 'keluasan' => 5000, 'unit_keluasan' => 'm2', 'kategori_guna_tanah' => 'PERTANIAN', 'bahagian_pemilik' => 'baitulmal']);
        $c = $this->lot(['kod_mukim' => '100901', 'keluasan' => 1, 'unit_keluasan' => 'hektar', 'kategori_guna_tanah' => 'PERTANIAN', 'bahagian_pemilik' => 'wakaf']);
        $this->lot(['kod_mukim' => '100901', 'status_lot' => 'arkib']);
        $this->lot(['kod_mukim' => '100901', 'klasifikasi' => 'rahsia']);

        return [$a, $b, $c];
    }

    public function test_SRS_DSH_02_portfolio_size_area_and_breakdowns(): void
    {
        $this->portfolio();
        $k = $this->kpis($this->user());

        $this->assertSame(3, $k['pro.bilangan_lot']['nilai'], 'archived and Rahsia lots (no grant) are excluded');
        $this->assertEquals(3.5, $k['pro.keluasan']['nilai']);
        $this->assertSame('ha', $k['pro.keluasan']['unit']);
        $byDistrict = collect($k['pro.bilangan_lot']['pecahan'])->keyBy('kod');
        $this->assertSame(2, $byDistrict['daerah:1008']['nilai']);
        $this->assertSame('Petaling', $byDistrict['daerah:1008']['label']);
        $byDiv = collect($k['pro.lot_bahagian']['pecahan'])->keyBy('kod');
        $this->assertSame(2, $byDiv['bahagian:wakaf']['nilai']);
        $this->assertSame('/admin/dsh/senarai?kpi=pro.bilangan_lot', $k['pro.bilangan_lot']['pautan']);
    }

    public function test_SRS_DSH_02_district_officer_sees_only_their_district(): void
    {
        $this->portfolio();
        $k = $this->kpis($this->user(self::EKSEKUTIF, ['1009']));
        $this->assertSame(1, $k['pro.bilangan_lot']['nilai']);
        $this->assertSame(['daerah:1009'], array_column($k['pro.bilangan_lot']['pecahan'], 'kod'));

        // Asking for another district through the filter returns nothing, not the other district.
        $k = $this->kpis($this->user(self::EKSEKUTIF, ['1009']), ['kod_daerah' => '1008']);
        $this->assertSame(0, $k['pro.bilangan_lot']['nilai']);
    }

    public function test_SRS_DSH_08_combined_filters_narrow_every_widget(): void
    {
        $this->portfolio();
        $user = $this->user();
        $k = $this->kpis($user, ['kod_daerah' => '1008', 'kategori' => 'PERTANIAN']);
        $this->assertSame(1, $k['pro.bilangan_lot']['nilai']);
        $this->assertEquals(0.5, $k['pro.keluasan']['nilai']);
        $k = $this->kpis($user, ['bahagian' => 'wakaf', 'kod_mukim' => '100901']);
        $this->assertSame(1, $k['pro.bilangan_lot']['nilai']);
        $k = $this->kpis($user, ['modul' => 'pro']);
        $this->assertSame(['pro'], array_values(array_unique(array_column($k, 'modul'))));
    }

    public function test_SRS_DSH_02_rahsia_summaries_need_the_explicit_grant(): void
    {
        [$a, $b] = $this->portfolio();
        DB::table('penilaian')->insert([
            ['id' => (string) str()->uuid(), 'lot_id' => $a, 'tarikh_penilaian' => '2024-01-01', 'nilai_pasaran' => 100000, 'versi' => 1, 'klasifikasi' => 'rahsia', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) str()->uuid(), 'lot_id' => $a, 'tarikh_penilaian' => '2025-06-01', 'nilai_pasaran' => 250000, 'versi' => 2, 'klasifikasi' => 'rahsia', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) str()->uuid(), 'lot_id' => $b, 'tarikh_penilaian' => '2025-01-01', 'nilai_pasaran' => 50000, 'versi' => 1, 'klasifikasi' => 'rahsia', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $kes = (string) str()->uuid();
        DB::table('kes_mahkamah')->insert(['id' => $kes, 'no_kes' => 'KES/1', 'jenis' => 'sivil', 'status' => 'aktif', 'klasifikasi' => 'rahsia', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('kes_mahkamah_lot')->insert(['kes_mahkamah_id' => $kes, 'lot_id' => $a]);

        $plain = $this->kpis($this->user());
        $this->assertArrayNotHasKey('pro.nilai_portfolio', $plain);
        $this->assertArrayNotHasKey('kes.aktif', $plain);

        $granted = $this->kpis($this->user([...self::EKSEKUTIF, 'dsh.rahsia.lihat']));
        $this->assertEquals(300000, $granted['pro.nilai_portfolio']['nilai'], 'latest valuation per lot');
        $this->assertSame(1, $granted['kes.aktif']['nilai']);

        // Same grant but another district: the case's lot is out of scope.
        $this->assertSame(0, $this->kpis($this->user([...self::EKSEKUTIF, 'dsh.rahsia.lihat'], ['1009']))['kes.aktif']['nilai']);
    }

    public function test_SRS_DSH_02_titles_land_status_and_expiring(): void
    {
        [$a, $b, $c] = $this->portfolio();
        $this->title($a, ['status_semasa' => 'disewa']);
        $this->title($b, ['status_semasa' => 'kosong', 'disahkan' => false]);
        $this->title($c, ['jenis_hak_milik' => 'PM', 'tarikh_tamat_tempoh' => now()->addMonths(3)->toDateString()]);
        $this->title($c, ['status_semasa' => 'diganti']);
        $this->params->values['hkm.senarai_status_tanah'] = [['kod' => 'aktif', 'nama' => 'Aktif'], ['kod' => 'kosong', 'nama' => 'Kosong'], ['kod' => 'disewa', 'nama' => 'Disewa']];

        $k = $this->kpis($this->user());
        $this->assertSame(3, $k['hkm.hak_milik']['nilai'], 'replaced titles are not in force');
        $status = collect($k['hkm.status_tanah']['pecahan'])->keyBy('kod');
        $this->assertSame('Kosong', $status['status:kosong']['label']);
        $this->assertEquals(66.7, $k['hkm.status_tanah']['nilai']);
        $this->assertSame(1, $k['hkm.hampir_tamat']['nilai']);
        $this->assertSame(1, $k['hkm.belum_disahkan']['nilai']);
        $this->assertSame('kuning', $k['hkm.belum_disahkan']['lampu']);

        $this->assertArrayNotHasKey('hkm.hak_milik', $this->kpis($this->user(['dsh.dashboard.lihat', 'pro.lot.lihat'])));
    }

    public function test_SRS_DSH_02_acquisitions_by_channel_and_outstanding_actions(): void
    {
        $this->acquisition(['status_pemerolehan' => 'selesai', 'tarikh_pemerolehan' => now()->toDateString(), 'id_saluran' => 1]);
        $this->acquisition(['status_pemerolehan' => 'selesai', 'tarikh_pemerolehan' => now()->toDateString(), 'id_saluran' => 2, 'kod_daerah' => '1009']);
        $this->acquisition(['tarikh_mula_kes' => now()->subDays(400)->toDateString(), 'tempoh_sasaran_hari' => 365]);
        $user = $this->user();
        $this->task('individu:'.$user->id, '1008', now()->subDay()->toDateTimeString());
        $this->task('peranan:pelulus', '1009');

        $k = $this->kpis($user);
        $this->assertSame(2, $k['per.pemerolehan_tahunan']['nilai']);
        $this->assertSame(2, end($k['per.pemerolehan_tahunan']['trend'])['nilai']);
        $this->assertSame(1, $k['per.dalam_proses']['nilai']);
        $this->assertSame(1, $k['per.melebihi_tempoh']['nilai']);
        $this->assertSame(2, $k['wfe.tugasan_tertunggak']['nilai']);
        $this->assertSame(1, $k['wfe.tugasan_lewat_sla']['nilai']);
        $this->assertSame('kuning', $k['wfe.tugasan_lewat_sla']['lampu'], 'default threshold: 0 green, ≤5 amber');
        $this->assertSame(1, $k['wfe.tugasan_saya']['nilai']);

        $district = $this->kpis($this->user(self::EKSEKUTIF, ['1009']));
        $this->assertSame(1, $district['per.pemerolehan_tahunan']['nilai']);
        $this->assertSame(1, $district['wfe.tugasan_tertunggak']['nilai']);
    }
}
