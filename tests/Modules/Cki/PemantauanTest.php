<?php

namespace Tests\Modules\Cki;

use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Lejar;
use App\Modules\Cki\Models\Resit;
use App\Modules\Cki\Models\Transaksi;
use App\Modules\Cki\Reminders\CukaiAkanMatang;
use App\Modules\Cki\Services\Harian;
use App\Modules\Cki\Services\LiputanService;
use App\Modules\Cki\Services\RekonsiliasiService;
use App\Modules\Ntf\Contracts\Reminders;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

class PemantauanTest extends CkiTestCase
{
    public function test_SRS_CKI_17_reminders_fire_exactly_30_14_and_7_days_before_due_date(): void
    {
        $officer = $this->user(self::PEGAWAI, ['1008']);
        $other = $this->user(self::PEGAWAI, ['1009']);
        $today = CarbonImmutable::parse('2026-10-15');
        $b30 = $this->bil(null, ['tarikh_akhir' => '2026-11-14']);
        $b14 = $this->bil(null, ['tarikh_akhir' => '2026-10-29']);
        $b7 = $this->bil(null, ['tarikh_akhir' => '2026-10-22']);
        $this->bil(null, ['tarikh_akhir' => '2026-11-13']);                     // 29 days: no reminder
        $paid = $this->bil(null, ['tarikh_akhir' => '2026-11-14', 'status_bayaran' => 'dibayar']);

        $source = app(CukaiAkanMatang::class);
        $this->assertSame([30, 14, 7], $source->defaultDays());
        $due = fn (int $d) => collect($source->due($d, $today));
        $this->assertSame(['cukai:'.$b30->id], $due(30)->pluck('key')->all());
        $this->assertSame(['cukai:'.$b14->id], $due(14)->pluck('key')->all());
        $this->assertSame(['cukai:'.$b7->id], $due(7)->pluck('key')->all());
        $this->assertNotContains('cukai:'.$paid->id, $due(30)->pluck('key')->all());
        $r = $due(7)->first();
        $this->assertSame(7, $r->data['baki_hari']);
        $this->assertSame('22/10/2026', $r->data['tarikh_akhir']);
        $ids = collect($r->recipients)->pluck('id');
        $this->assertTrue($ids->contains($officer->id));
        $this->assertFalse($ids->contains($other->id));               // other district's officer not told
        $this->assertContains(CukaiAkanMatang::class, app(Reminders::class)->sources()); // run daily by ntf:peringatan
    }

    public function test_SRS_CKI_18_overdue_penalty_and_out_of_coverage_alerts(): void
    {
        $officer = $this->user(self::PEGAWAI, ['1008']);
        $bill = $this->bil(null, ['amaun_pokok' => '200.00', 'tarikh_akhir' => '2026-10-31']);
        Carbon::setTestNow('2026-11-01 08:00:00');
        $r = app(Harian::class)->jalankan();
        $this->assertSame(1, $r['tertunggak']);
        $bill->refresh();
        $this->assertSame('tertunggak', $bill->status_bayaran);
        $this->assertSame('20.00', $bill->amaun_denda);          // 10 % default, minimum RM10
        $this->assertSame('20.00', Lejar::query()->where('id_bil', $bill->id)->where('jenis', 'denda')->value('debit'));
        $this->assertSame('cki.denda:'.$bill->id, $this->notifier->ofType('cki.denda')[0]['dedupe']);
        $this->assertContains($officer->id, $this->notifier->ofType('cki.tertunggak')[0]['to']);
        // Idempotent: second run adds nothing.
        $this->assertSame(['tertunggak' => 0, 'denda' => 0], array_intersect_key(app(Harian::class)->jalankan(), ['tertunggak' => 1, 'denda' => 1]));

        app(LiputanService::class)->kemaskini($this->agensi('MBPJ'), ['perkhidmatan_semakan' => false]);
        $this->obligasi('taksiran_pbt');
        $r = app(Harian::class)->jalankan();
        $this->assertSame(1, $r['luar_liputan']);
        $this->assertSame(1, $this->notifier->ofType('cki.luar_liputan')[0]['data']['bil_lot']);
    }

    public function test_SRS_CKI_03_payment_schedule_for_planning_period_grouped_by_district_pbt_and_due_date(): void
    {
        $user = $this->user();
        $this->bil(null, ['tarikh_akhir' => '2026-11-20', 'amaun_pokok' => '100.00']);
        $this->bil(null, ['tarikh_akhir' => '2026-12-20', 'amaun_pokok' => '50.00']);
        $this->bil($this->obligasi('taksiran_pbt', null, null, '100901'), ['tarikh_akhir' => '2026-11-30', 'amaun_pokok' => '70.00']);
        $this->bil(null, ['tarikh_akhir' => '2027-06-30']);                           // beyond 90 days
        $this->bil(null, ['tarikh_akhir' => '2026-11-01', 'status_bayaran' => 'dibayar']); // paid

        $byMonth = $this->actingAs($user)->getJson('/api/v1/cki/jadual')->assertOk()->json('data');
        $this->assertSame(90, $byMonth['hari']);
        $this->assertSame('220.00', $byMonth['jumlah']);
        $this->assertSame(['2026-11', '2026-12'], array_column($byMonth['kumpulan'], 'kod'));
        $byDistrict = $this->actingAs($user)->getJson('/api/v1/cki/jadual?kumpulan=daerah')->json('data.kumpulan');
        $this->assertSame(['1008' => '150.00', '1009' => '70.00'], array_column($byDistrict, 'jumlah', 'kod'));
        $byPbt = $this->actingAs($user)->getJson('/api/v1/cki/jadual?kumpulan=pbt')->json('data.kumpulan');
        $this->assertContains('MPSEPANG', array_column($byPbt, 'kod'));
        $this->assertSame('Pejabat Tanah Daerah Petaling', collect($byPbt)->firstWhere('kod', 'PTD-1008')['nama']);
    }

    public function test_SRS_CKI_19_lot_timeline_across_years_with_receipts_and_penalties_exportable(): void
    {
        $user = $this->user();
        $lot = $this->lot();
        $o = $this->obligasi('tanah', $lot);
        $this->bil($o, ['tahun_taksiran' => 2024, 'no_bil' => 'A', 'tarikh_akhir' => '2024-05-31', 'status_bayaran' => 'dibayar', 'tarikh_bayar' => '2024-05-01', 'no_resit' => 'R24']);
        $this->bil($o, ['tahun_taksiran' => 2025, 'no_bil' => 'B', 'tarikh_akhir' => '2025-05-31', 'amaun_denda' => '12.00']);
        $data = $this->actingAs($user)->getJson('/api/v1/cki/lot/'.$lot.'/garis-masa')->assertOk()->json('data');
        $this->assertSame([2024, 2025], array_column($data['bil'], 'tahun_taksiran'));
        $this->assertSame('R24', $data['bil'][0]['no_resit']);
        $this->assertSame('2024-05-01', $data['bil'][0]['tarikh_bayar']);
        $this->assertSame('12.00', $data['bil'][1]['amaun_denda']['nilai']);
        $this->actingAs($user)->get('/api/v1/cki/lot/'.$lot.'/garis-masa/eksport?format=xlsx')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('%PDF', $this->actingAs($user)->get('/api/v1/cki/lot/'.$lot.'/garis-masa/eksport')->assertOk()->getContent());
        $this->assertAudited('eksport', ['modul' => 'cki', 'nama_entiti' => 'lot_tanah', 'id_rekod' => $lot]);
        $this->actingAs($this->user(self::PEGAWAI, ['1009']))->getJson('/api/v1/cki/lot/'.$lot.'/garis-masa')->assertNotFound();
    }

    public function test_SRS_CKI_20_reconciliation_lists_the_three_deviation_cases(): void
    {
        $finance = $this->user(['cki.rekonsiliasi.lihat', 'cki.rekonsiliasi.cipta', 'cki.bil.lihat']);
        $officer = $this->user();
        // (1) bill paid without receipt
        $noReceipt = $this->bil(null, ['tahun_taksiran' => 2025, 'tarikh_akhir' => '2026-10-31', 'status_bayaran' => 'dibayar', 'tarikh_bayar' => '2026-10-10']);
        // (2) receipt without bill
        $this->actingAs($officer)->postJson('/api/v1/cki/resit', ['no_resit' => 'ORPHAN-1', 'no_bil_rujukan' => 'TIADA', 'amaun' => '45.00', 'tarikh_resit' => '2026-10-11', 'kod_daerah' => '1008'])->assertCreated();
        // (3) amount difference between system and GRP
        $bill = $this->bil(null, ['amaun_pokok' => '900.00']);
        $t = Transaksi::query()->withoutGlobalScope('access')->findOrFail($this->actingAs($officer)->postJson('/api/v1/cki/bil/'.$bill->id.'/bayaran', ['kaedah_bayaran' => 'eft'])->json('data.id'));
        $this->complete($t->id);
        $this->actingAs($officer)->postJson('/api/v1/cki/transaksi/'.$t->id.'/laksana')->assertOk();
        $this->actingAs($officer)->post('/api/v1/cki/transaksi/'.$t->id.'/resit', ['no_resit' => 'R-OK', 'fail' => UploadedFile::fake()->create('r.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($officer)->postJson('/api/v1/cki/transaksi/'.$t->id.'/selesai')->assertOk();
        $this->grp->tamper((string) $t->refresh()->rujukan_grp, '890.00');
        // a matching, clean payment
        $ok = $this->bil(null, ['amaun_pokok' => '10.00', 'status_bayaran' => 'dibayar', 'tarikh_bayar' => '2026-10-12']);
        Resit::query()->create(['id_bil' => $ok->id, 'no_resit' => 'R-CLEAN', 'amaun' => '10.00', 'tarikh_resit' => '2026-10-12', 'sumber' => 'manual']);

        $this->actingAs($finance)->postJson('/api/v1/cki/rekonsiliasi', ['dari' => '2026-10-01', 'hingga' => '2026-10-15'])->assertCreated();
        $rows = collect($this->actingAs($finance)->getJson('/api/v1/cki/rekonsiliasi')->assertOk()->json('data.sisihan'));
        $this->assertEqualsCanonicalizing(['bil_tanpa_resit', 'resit_tanpa_bil', 'beza_amaun_grp'], $rows->pluck('jenis')->all());
        $this->assertSame($noReceipt->id, $rows->firstWhere('jenis', 'bil_tanpa_resit')['id_bil']);
        $grp = $rows->firstWhere('jenis', 'beza_amaun_grp');
        $this->assertSame(['900.00', '890.00'], [$grp['amaun_sistem'], $grp['amaun_grp']]);
        $this->assertNotEmpty($this->notifier->ofType('cki.sisihan_rekonsiliasi'));
        $this->assertDatabaseHas('penyelarasan_grp_cukai', ['id_transaksi' => $t->id, 'id_larian' => null, 'status_padanan' => 'sisihan']);
    }

    public function test_SRS_CKI_20_payment_missing_from_grp_is_a_deviation(): void
    {
        $officer = $this->user();
        $bill = $this->bil(null, ['amaun_pokok' => '300.00']);
        $t = Transaksi::query()->withoutGlobalScope('access')->findOrFail($this->actingAs($officer)->postJson('/api/v1/cki/bil/'.$bill->id.'/bayaran', ['kaedah_bayaran' => 'eft'])->json('data.id'));
        $this->complete($t->id);
        $this->actingAs($officer)->postJson('/api/v1/cki/transaksi/'.$t->id.'/laksana')->assertOk();
        $this->grp->tamper((string) $t->refresh()->rujukan_grp, null);
        $run = app(RekonsiliasiService::class)->jalankan(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));
        $this->assertSame(1, $run->bil_sisihan);
        $this->assertDatabaseHas('penyelarasan_grp_cukai', ['id_larian' => $run->id, 'jenis' => 'tiada_dalam_grp', 'id_transaksi' => $t->id]);
        $this->grp->setDown(true);
        $this->artisan('cki:rekonsiliasi')->assertFailed();
    }

    public function test_SRS_CKI_21_dashboard_totals_paid_arrears_penalties_and_late_risk_filterable_and_scoped(): void
    {
        $state = $this->user();
        $district = $this->user(self::PEGAWAI, ['1009']);
        $this->bil(null, ['amaun_pokok' => '100.00', 'status_bayaran' => 'dibayar', 'tarikh_akhir' => '2026-05-31', 'tarikh_bayar' => '2026-05-01']);
        $this->bil(null, ['amaun_pokok' => '200.00', 'amaun_denda' => '20.00', 'tarikh_akhir' => '2026-05-31']);
        $this->bil(null, ['amaun_pokok' => '300.00', 'tarikh_akhir' => '2026-10-30']);         // due in 15 days → late risk
        $this->bil($this->obligasi('taksiran_pbt', null, null, '100901'), ['amaun_pokok' => '400.00', 'tarikh_akhir' => '2026-12-31']);

        $d = $this->actingAs($state)->getJson('/api/v1/cki/dashboard')->assertOk()->json('data');
        $this->assertSame(['bil' => 4, 'jumlah_bil' => '1020.00', 'telah_dibayar' => '100.00', 'tertunggak' => '220.00', 'bil_tertunggak' => 1, 'denda_terkumpul' => '20.00', 'risiko_lewat' => '300.00', 'bil_risiko_lewat' => 1], $d['jumlah']);
        $this->assertSame(['1008', '1009'], array_column($d['mengikut_daerah'], 'kod'));
        $this->assertSame(['cki.jumlah_cukai_tahunan', 'cki.tertunggak', 'cki.peratus_bayar_tepat_masa', 'cki.penalti'], array_column($d['kpi'], 'kod'));
        $this->assertSame('400.00', $this->actingAs($state)->getJson('/api/v1/cki/dashboard?jenis_cukai=taksiran_pbt')->json('data.jumlah.jumlah_bil'));
        $this->assertSame('400.00', $this->actingAs($state)->getJson('/api/v1/cki/dashboard?kod_pbt=MPSEPANG')->json('data.jumlah.jumlah_bil'));
        $this->assertSame('620.00', $this->actingAs($state)->getJson('/api/v1/cki/dashboard?kod_mukim=100803')->json('data.jumlah.jumlah_bil'));
        // A Sepang officer only sees Sepang.
        $this->assertSame('400.00', $this->actingAs($district)->getJson('/api/v1/cki/dashboard')->json('data.jumlah.jumlah_bil'));
    }

    public function test_SRS_CKI_22_portfolio_position_by_district_pbt_type_and_arrears_age_with_annual_budget_estimate(): void
    {
        $user = $this->user();
        $this->bil(null, ['amaun_pokok' => '100.00', 'tarikh_akhir' => '2026-10-01']);                       // 14 days overdue
        $this->bil(null, ['tahun_taksiran' => 2025, 'amaun_pokok' => '50.00', 'tarikh_akhir' => '2025-05-31']); // > 1 year
        $this->bil($this->obligasi('taksiran_pbt'), ['amaun_pokok' => '1000.00', 'tarikh_akhir' => '2026-11-30']);
        $d = $this->actingAs($user)->getJson('/api/v1/cki/dashboard')->json('data');
        $age = collect($d['umur_tunggakan'])->keyBy('kod');
        $this->assertSame('100.00', $age['1_30']['amaun']);
        $this->assertSame('50.00', $age['lebih_365']['amaun']);
        $this->assertEqualsCanonicalizing(['PTD-1008', 'MBPJ'], array_column($d['mengikut_pbt'], 'kod'));
        $this->assertEqualsCanonicalizing(['tanah', 'taksiran_pbt'], array_column($d['mengikut_jenis'], 'kod'));
        // 2026 principal 1,100.00 + 3 % = 1,133.00 for 2027.
        $this->assertSame('1133.00', $d['anggaran']['jumlah_anggaran']);
        $this->assertSame(2027, $d['anggaran']['tahun_anggaran']);
    }

    public function test_SRS_CKI_23_reports_with_pdf_and_excel_export(): void
    {
        $user = $this->user();
        $this->bil(null, ['amaun_pokok' => '100.00', 'status_bayaran' => 'dibayar', 'tarikh_akhir' => '2026-05-31', 'tarikh_bayar' => '2026-06-15', 'amaun_denda' => '10.00']);
        $this->bil(null, ['amaun_pokok' => '200.00', 'tarikh_akhir' => '2026-05-31', 'amaun_denda' => '20.00']);
        $this->bil(null, ['amaun_pokok' => '300.00', 'tarikh_akhir' => '2026-12-31']);
        $rows = fn (string $j) => $this->actingAs($user)->getJson('/api/v1/cki/laporan/'.$j)->assertOk()->json('data.baris');
        $this->assertCount(1, $rows('belum_bayar'));
        $this->assertCount(1, $rows('telah_bayar'));
        $this->assertSame('Tidak', $rows('telah_bayar')[0][9]);           // paid after due date
        $this->assertCount(1, $rows('tertunggak'));
        $this->assertSame('91–365 hari', $rows('tertunggak')[0][11]);
        $this->assertCount(2, $rows('penalti'));
        $this->assertSame('Petaling', $rows('bayaran_daerah')[0][0]);
        $this->assertSame(2026, $rows('trend_tahunan')[0][0]);
        foreach (['anggaran_obligasi', 'rekonsiliasi', 'luar_liputan', 'prestasi_integrasi'] as $j) {
            $this->actingAs($user)->getJson('/api/v1/cki/laporan/'.$j)->assertOk()->assertJsonStructure(['data' => ['tajuk', 'kepala', 'baris']]);
        }
        $this->actingAs($user)->getJson('/api/v1/cki/laporan/tiada')->assertNotFound();
        $this->assertStringStartsWith('%PDF', $this->actingAs($user)->get('/api/v1/cki/laporan/tertunggak/eksport?format=pdf')->assertOk()->getContent());
        $this->actingAs($user)->get('/api/v1/cki/laporan/bayaran_daerah/eksport?format=xlsx')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertAudited('eksport', ['modul' => 'cki', 'nama_entiti' => 'laporan', 'id_rekod' => 'bayaran_daerah']);
        $this->actingAs($this->user(['cki.laporan.lihat']))->get('/api/v1/cki/laporan/tertunggak/eksport')->assertForbidden();
    }

    public function test_SRS_CKI_02_excel_import_validates_row_by_row_and_reports_accepted_and_rejected(): void
    {
        $user = $this->user();
        $this->obligasi('tanah', null, 'CT-IMP-1');
        $csv = "jenis_cukai,no_akaun_cukai,tahun_taksiran,no_bil,amaun_pokok,amaun_denda,tarikh_bil,tarikh_akhir,status_bayaran\n"
            ."tanah,CT-IMP-1,2026,IMP-1,150.50,0,2026-01-02,31/05/2026,belum_bayar\n"
            ."tanah,TIADA,2026,IMP-2,10,0,,2026-05-31,\n"
            ."tanah,CT-IMP-1,2026,IMP-3,abc,0,,2026-05-31,\n";
        $file = fn () => UploadedFile::fake()->createWithContent('bil.csv', $csv);
        $dry = $this->actingAs($user)->post('/api/v1/cki/import', ['fail' => $file(), 'dry_run' => '1'], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame(['jumlah_baris' => 3, 'diterima' => 1, 'ditolak' => 2], array_intersect_key($dry, ['jumlah_baris' => 1, 'diterima' => 1, 'ditolak' => 1]));
        $this->assertSame([3, 4], array_column($dry['ralat'], 'baris'));
        $this->assertSame(0, Cukai::query()->withoutGlobalScope('access')->count());

        $real = $this->actingAs($user)->post('/api/v1/cki/import', ['fail' => $file()], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertSame(1, $real['diterima']);
        $bill = Cukai::query()->withoutGlobalScope('access')->sole();
        $this->assertSame(['import', '150.50', '2026-05-31'], [$bill->sumber_rekod, $bill->amaun_pokok, $bill->tarikh_akhir->format('Y-m-d')]);
        $this->actingAs($user)->get('/api/v1/cki/import/templat')->assertOk();
    }
}
