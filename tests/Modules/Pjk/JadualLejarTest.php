<?php

namespace Tests\Modules\Pjk;

use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\LejarTransaksi;
use App\Modules\Pjk\Services\Harian;
use App\Modules\Pjk\Services\JadualCajService;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class JadualLejarTest extends PjkTestCase
{
    public function test_SRS_PJK_12_schedule_generated_from_terms_with_cycle_and_prorated_last_period(): void
    {
        $user = $this->user();
        $monthly = $this->active($user, null, ['tarikh_mula' => '2026-01-01', 'tarikh_tamat' => '2026-12-31']);
        $rows = JadualCaj::query()->where('kontrak_id', $monthly->id)->where('kod_caj', 'sewa')->orderBy('tarikh_caj')->get();
        $this->assertCount(12, $rows);
        $this->assertSame('2026-01', $rows[0]->tempoh);
        $this->assertSame('2026-01-15', $rows[0]->tarikh_akhir->format('Y-m-d'));
        $this->assertTrue($rows->every(fn ($c) => $c->amaun === '1500.00'));

        $quarterly = $this->active($user, null, ['tarikh_mula' => '2026-01-01', 'tarikh_tamat' => '2026-08-15', 'kitaran_caj' => 'suku_tahunan', 'kadar' => '1000.00']);
        $q = JadualCaj::query()->where('kontrak_id', $quarterly->id)->where('kod_caj', 'sewa')->orderBy('tarikh_caj')->get();
        $this->assertSame(['2026-Q1', '2026-Q2', '2026-Q3'], $q->pluck('tempoh')->all());
        $this->assertSame('3000.00', $q[0]->amaun);
        // Jul 1 – Aug 15 = 46 of 92 days → 3000 × 46/92 = 1500.00
        $this->assertSame('1500.00', $q[2]->amaun);
    }

    public function test_SRS_PJK_12_due_charges_posted_daily_and_arrears_aged_in_four_buckets(): void
    {
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-05-01', 'tarikh_tamat' => '2027-04-30']);
        // Activation issued May..Oct (6 months) + deposit.
        $this->assertSame(6, JadualCaj::query()->where('kontrak_id', $k->id)->where('kod_caj', 'sewa')->where('dikeluarkan', true)->count());

        Carbon::setTestNow('2026-11-01 08:00:00');
        $r = app(Harian::class)->run();
        $this->assertSame(1, $r['caj_dikeluarkan']);

        $ageing = app(JadualCajService::class)->ageing($k->id);
        // due dates: May15(170d), Jun15(139), Jul15(109), Aug15(78), Sep15(47), Oct15(17); Nov15 not yet due; deposit due Oct 29 (3d)
        $this->assertSame('4500.00', $ageing['kumpulan']['lebih_90']);
        $this->assertSame('1500.00', $ageing['kumpulan']['61_90']);
        $this->assertSame('1500.00', $ageing['kumpulan']['31_60']);
        $this->assertSame('4500.00', $ageing['kumpulan']['1_30']); // Oct rent + 3000 deposit
        $this->assertSame('12000.00', $ageing['jumlah']);

        $res = $this->actingAs($this->user())->getJson("/api/v1/pjk/kontrak/{$k->id}/caj")->assertOk();
        $may = collect($res->json('data'))->firstWhere('tempoh', '2026-05');
        $this->assertSame(170, $may['umur_tunggakan']);
        $this->assertSame('lebih_90', $may['kumpulan_umur']);
    }

    public function test_SRS_PJK_12_late_penalty_charged_once_per_month(): void
    {
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-05-01', 'tarikh_tamat' => '2027-04-30']);
        $svc = app(JadualCajService::class);
        // overdue > 30 days at 2026-10-15: May, Jun, Jul, Aug (Aug15 → 61d), Sep 15 (30d, not > 30) → 4 × 1500 = 6000 → 1% = 60.00
        $this->assertSame(1, $svc->penalties());
        $this->assertSame(0, $svc->penalties());
        $p = JadualCaj::query()->where('kontrak_id', $k->id)->where('kod_caj', 'penalti')->sole();
        $this->assertSame('60.00', $p->amaun);
        $this->assertSame('2026-10', $p->tempoh);
    }

    public function test_SRS_PJK_11_ledger_running_balance_and_period_view(): void
    {
        $user = $this->user();
        $k = $this->active($user, null, ['tarikh_mula' => '2026-08-01', 'tarikh_tamat' => '2027-07-31']);
        $rows = LejarTransaksi::query()->where('kontrak_id', $k->id)->orderBy('id')->get();
        $running = 0;
        foreach ($rows as $r) {
            $running += Wang::sen($r->debit) - Wang::sen($r->kredit);
            $this->assertSame(Wang::dec($running), $r->baki_berjalan);
        }
        $this->assertSame('7500.00', $rows->last()->baki_berjalan); // deposit 3000 + Aug, Sep, Oct rent

        $res = $this->actingAs($user)->getJson("/api/v1/pjk/kontrak/{$k->id}/lejar?dari=2026-09-01&hingga=2026-09-30")->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('1500.00', $res->json('ringkasan.jumlah_debit.nilai'));
    }

    public function test_SRS_PJK_11_tenant_statement_exports_pdf_and_excel_and_is_audited(): void
    {
        $user = $this->user();
        $k = $this->active($user);
        $pdf = $this->actingAs($user)->get("/api/v1/pjk/kontrak/{$k->id}/penyata?format=pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $xlsx = $this->actingAs($user)->get("/api/v1/pjk/kontrak/{$k->id}/penyata?format=xlsx")->assertOk();
        $this->assertStringStartsWith('PK', (string) $xlsx->getContent());
        $this->assertAudited('eksport', ['modul' => 'pjk', 'id_rekod' => $k->id]);

        $this->actingAs($this->user(['pjk.kontrak.lihat', 'pjk.lejar.lihat']))->get("/api/v1/pjk/kontrak/{$k->id}/penyata")->assertForbidden();
    }

    public function test_SRS_PJK_11_ledger_is_append_only_at_database_level(): void
    {
        $k = $this->active($this->user());
        $row = LejarTransaksi::query()->where('kontrak_id', $k->id)->firstOrFail();

        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('lejar_transaksi')->where('id', $row->id)->update(['debit' => '1.00']));
    }

    public function test_SRS_PJK_11_ledger_rows_cannot_be_deleted(): void
    {
        $k = $this->active($this->user());

        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('lejar_transaksi')->where('kontrak_id', $k->id)->delete());
    }
}
