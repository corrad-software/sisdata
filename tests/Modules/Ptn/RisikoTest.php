<?php

namespace Tests\Modules\Ptn;

use App\Modules\Gis\Contracts\MapLayers;
use App\Modules\Ptn\Contracts\LotRiskStatus;
use App\Modules\Ptn\Contracts\RiskSignal;
use App\Modules\Ptn\Contracts\RiskSignals;
use App\Modules\Ptn\Gis\PemantauanLayer;
use App\Modules\Ptn\Gis\RisikoLotLayer;
use App\Modules\Ptn\Models\RiskRatingLot;
use App\Modules\Ptn\Profile\RisikoSection;
use App\Modules\Ptn\Services\RisikoService;
use App\Modules\Ptn\Support\KodWarna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** SRS-PTN-11/12/14/15/16/24, acceptance #5 and #7. */
class RisikoTest extends PtnTestCase
{
    /** Lot A: encroachment (tinggi), RM2,500 tax arrears, one active case, missing pelan, lease ending in 60 days. */
    private function lotBerisiko(): string
    {
        $lot = $this->lot();
        DB::table('isu_pemantauan')->insert(['id' => (string) Str::uuid(), 'no_rujukan' => 'ISU-R-1', 'lot_id' => $lot, 'kod_daerah' => '1008', 'kod_mukim' => '100803', 'kategori_isu' => 'pencerobohan', 'tahap_keterukan' => 'tinggi', 'keterangan' => 'x', 'tarikh_dibuka' => now()->toDateString(), 'status_isu' => 'terbuka', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([2000, 500] as $amt) {
            DB::table('cukai')->insert(['id' => (string) Str::uuid(), 'lot_id' => $lot, 'jenis' => 'tanah', 'jumlah' => $amt, 'tarikh_akhir' => now()->subMonth()->toDateString(), 'status_bayaran' => 'tertunggak', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('cukai')->insert(['id' => (string) Str::uuid(), 'lot_id' => $lot, 'jenis' => 'tanah', 'jumlah' => 9000, 'tarikh_akhir' => now()->subMonth()->toDateString(), 'status_bayaran' => 'dibayar', 'created_at' => now(), 'updated_at' => now()]);
        $kes = (string) Str::uuid();
        DB::table('kes_mahkamah')->insert(['id' => $kes, 'no_kes' => 'KES/1', 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('kes_mahkamah_lot')->insert(['kes_mahkamah_id' => $kes, 'lot_id' => $lot]);
        DB::table('dokumen')->insert([
            'id' => (string) Str::uuid(), 'no_rujukan' => 'DOK-1', 'kategori_id' => DB::table('dokumen_kategori')->where('kod', 'geran')->value('id'),
            'jenis_entiti_induk' => 'lot_tanah', 'id_rekod_induk' => $lot, 'tajuk' => 'Geran', 'nama_fail' => 'g.pdf', 'jenis_mime' => 'application/pdf', 'sambungan' => 'pdf',
            'saiz_bait' => 10, 'cincangan_fail' => str_repeat('a', 64), 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('kontrak_pajakan')->insert(['id' => (string) Str::uuid(), 'lot_id' => $lot, 'status' => 'aktif', 'tarikh_mula' => now()->subYear()->toDateString(), 'tarikh_tamat' => now()->addDays(60)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        return $lot;
    }

    public function test_SRS_PTN_12_six_category_scores_match_the_manual_calculation(): void
    {
        $a = $this->lotBerisiko();
        $b = $this->lot();
        app(RisikoService::class)->kira();

        $r = RiskRatingLot::query()->withoutGlobalScope('access')->where('lot_id', $a)->where('semasa', true)->sole();
        $this->assertSame(
            ['75.00', '50.00', '70.00', '50.00', '60.00', '0.00'],
            [$r->skor_pencerobohan, $r->skor_tunggakan_cukai, $r->skor_pertikaian, $r->skor_dokumen, $r->skor_tamat_kontrak, $r->skor_potensi_hasil],
        );
        // (30×75 + 20×50 + 20×70 + 10×50 + 10×60 + 10×0) / 100
        $this->assertSame('57.50', $r->skor_keseluruhan);
        $this->assertSame('kuning', $r->kod_warna);

        $rb = RiskRatingLot::query()->withoutGlobalScope('access')->where('lot_id', $b)->where('semasa', true)->sole();
        // Only: no active lease (50) and no geran/pelan (100) → (10×50 + 10×100) / 100
        $this->assertSame(['50.00', '100.00', '15.00', 'hijau'], [$rb->skor_potensi_hasil, $rb->skor_dokumen, $rb->skor_keseluruhan, $rb->kod_warna]);
        $this->assertSame('Kontrak tamat dalam 60 hari', $r->butiran['butiran']['tamat_kontrak']);
    }

    public function test_SRS_PTN_11_category_weights_are_configurable_and_history_kept(): void
    {
        $admin = $this->admin();
        $a = $this->lotBerisiko();
        $this->actingAs($admin)->postJson('/api/v1/ptn/konfigurasi/ambang', ['kategori_risiko' => 'pencerobohan', 'pemberat' => 60])->assertCreated();
        $this->actingAs($admin)->postJson('/api/v1/ptn/risiko/kira', ['lot_id' => $a])->assertOk();
        $r = RiskRatingLot::query()->withoutGlobalScope('access')->where('lot_id', $a)->where('semasa', true)->sole();
        // (60×75 + 3500) / 130
        $this->assertSame('61.54', $r->skor_keseluruhan);
        $this->assertSame('merah', $r->kod_warna);
        $this->assertSame(2, DB::table('konfigurasi_ambang_warna')->where('kategori_risiko', 'pencerobohan')->count());
        $this->assertAudited('cipta', ['modul' => 'ptn', 'nama_entiti' => 'konfigurasi_ambang_warna']);
        $this->actingAs($this->user(['ptn.konfigurasi.lihat']))->postJson('/api/v1/ptn/konfigurasi/ambang', ['kategori_risiko' => 'pencerobohan', 'pemberat' => 1])->assertForbidden();
    }

    public function test_SRS_PTN_12_owning_module_can_replace_a_builtin_signal(): void
    {
        $lot = $this->lot();
        app(RiskSignals::class)->register(CukaiDariModul::class);
        app(RisikoService::class)->kira([$lot]);
        $this->assertSame('90.00', RiskRatingLot::query()->withoutGlobalScope('access')->where('lot_id', $lot)->value('skor_tunggakan_cukai'));
    }

    public function test_SRS_PTN_15_threshold_change_shows_on_list_card_map_and_dashboard_without_deploy(): void
    {
        $admin = $this->admin();
        $a = $this->lotBerisiko();
        app(RisikoService::class)->kira();
        $colours = function () use ($admin, $a): array {
            app()->forgetScopedInstances();

            return [
                'senarai' => collect($this->actingAs($admin)->getJson('/api/v1/ptn/lot?kod_daerah=1008')->assertOk()->json('data'))->firstWhere('lot_id', $a)['kod_warna'],
                'kad' => $this->actingAs($admin)->getJson('/api/v1/ptn/lot/'.$a)->assertOk()->json('data.risiko.kod_warna'),
                'peta' => collect(app(RisikoLotLayer::class)->features($admin)['features'])->firstWhere('id', $a)['properties']['kod_warna'],
                'dashboard' => $this->actingAs($admin)->getJson('/api/v1/ptn/risiko/dashboard')->assertOk()->json('data.taburan_warna.kuning') === 1 ? 'kuning' : 'merah',
                'profil' => app(RisikoSection::class)->forLot($a, $admin)['items'][0]['kod_warna'],
                'kontrak' => app(LotRiskStatus::class)->forLots([$a])[$a]['kod_warna'],
            ];
        };
        $this->assertSame(array_fill_keys(['senarai', 'kad', 'peta', 'dashboard', 'profil', 'kontrak'], 'kuning'), $colours());

        $this->actingAs($admin)->postJson('/api/v1/ptn/konfigurasi/ambang', ['kategori_risiko' => 'keseluruhan', 'ambang_kuning' => 20, 'ambang_merah' => 50])->assertCreated();
        $this->assertSame(array_fill_keys(['senarai', 'kad', 'peta', 'dashboard', 'profil', 'kontrak'], 'merah'), $colours());
    }

    public function test_SRS_PTN_14_action_and_visit_colours_follow_deadline_thresholds(): void
    {
        $k = app(KodWarna::class);
        $today = now();
        $this->assertSame('hijau', $k->untukTarikh($today->copy()->addDays(10), 'tindakan'));
        $this->assertSame('kuning', $k->untukTarikh($today->copy()->addDays(7), 'tindakan'));
        $this->assertSame('kuning', $k->untukTarikh($today->copy(), 'tindakan'));
        $this->assertSame('merah', $k->untukTarikh($today->copy()->subDay(), 'tindakan'));
        $this->assertSame('hijau', $k->untukTarikh($today->copy()->subDays(30), 'tindakan', true));
        $this->assertSame('kuning', $k->untukTarikh($today->copy()->addDays(14), 'lawatan'));
        $k->tetapkan('tindakan', ['tempoh_kelewatan_kuning' => 3, 'tempoh_kelewatan_merah' => 5], $this->admin());
        $this->assertSame('hijau', $k->untukTarikh($today->copy()->addDays(7), 'tindakan'));
        $this->assertSame('kuning', $k->untukTarikh($today->copy()->subDays(5), 'tindakan'));
        $this->assertSame('merah', $k->untukTarikh($today->copy()->subDays(6), 'tindakan'));
    }

    public function test_SRS_PTN_16_colour_codes_always_carry_legend_and_text_labels(): void
    {
        $admin = $this->admin();
        $warna = $this->actingAs($admin)->getJson('/api/v1/ptn/rujukan')->assertOk()->json('data.warna');
        $this->assertSame(['hijau', 'kuning', 'merah'], array_column($warna, 'kod'));
        foreach ($warna as $w) {
            $this->assertNotEmpty($w['label']);
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $w['hex']);
        }
        foreach ([app(RisikoLotLayer::class)->legend(), app(PemantauanLayer::class)->legend()] as $legend) {
            $this->assertNotEmpty($legend['entri']);
            $this->assertNotEmpty(array_filter(array_column($legend['entri'], 'label')));
        }
        $this->assertNotNull(app(MapLayers::class)->get('ptn.pemantauan'));
    }

    public function test_SRS_PTN_24_risk_dashboard_distribution_high_risk_list_open_issues_overdue_and_trend(): void
    {
        $sup = $this->user(self::PENYELIA, ['1008']);
        $a = $this->lotBerisiko();
        $this->lot();
        $this->lot('100901');
        app(RisikoService::class)->kira();
        $d = $this->actingAs($sup)->getJson('/api/v1/ptn/risiko/dashboard')->assertOk()->json('data');
        $this->assertSame(['hijau' => 1, 'kuning' => 1, 'merah' => 0], $d['taburan_warna'], 'skop daerah Petaling sahaja');
        $this->assertSame($a, $d['lot_risiko_tinggi'][0]['lot_id']);
        $this->assertSame(1, $d['isu_terbuka']);
        $this->assertCount(12, $d['trend']);
        $this->assertSame(1, end($d['trend'])['kuning']);
        $this->actingAs($this->user(['ptn.lawatan.lihat']))->getJson('/api/v1/ptn/risiko/dashboard')->assertForbidden();
    }
}

class CukaiDariModul implements RiskSignal
{
    public function category(): string
    {
        return 'tunggakan_cukai';
    }

    public function scores(array $lotIds): array
    {
        return array_fill_keys($lotIds, ['skor' => 90.0, 'butiran' => 'MOD-CKI']);
    }
}
