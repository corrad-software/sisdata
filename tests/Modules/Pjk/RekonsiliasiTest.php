<?php

namespace Tests\Modules\Pjk;

use App\Modules\Pjk\Contracts\GrpMais;
use App\Modules\Pjk\Contracts\PaymentGateway;
use App\Modules\Pjk\Integration\MockGrpMais;
use App\Modules\Pjk\Integration\MockPaymentGateway;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\RekonsiliasiLarian;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Models\SisihanRekonsiliasi;
use App\Modules\Pjk\Services\GatewayService;
use App\Modules\Pjk\Services\RekonsiliasiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

class RekonsiliasiTest extends PjkTestCase
{
    /** @return list<array{no_rujukan: string, amaun: array{nilai: string}}> */
    private function paidOnline(Kontrak $k, int $n): array
    {
        $out = [];
        $g = app(PaymentGateway::class);
        $this->assertInstanceOf(MockPaymentGateway::class, $g);
        for ($i = 0; $i < $n; $i++) {
            $tx = $this->actingAs($this->user())->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'fpx', 'amaun' => '100.00'])->json('data');
            $cb = $g->simulate($tx['no_rujukan'], 'berjaya');
            app(GatewayService::class)->callback($cb['body'], $cb['headers']);
            $out[] = $tx;
        }

        return $out;
    }

    public function test_SRS_PJK_18_acceptance_4_uploaded_settlement_file_yields_the_three_discrepancy_kinds(): void
    {
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-10-01', 'tarikh_tamat' => '2027-09-30']);
        [$a, $b, $c] = $this->paidOnline($k, 3);
        // Settled but never credited (unknown to the ledger):
        $csv = "rujukan,amaun,status,tarikh\n{$a['no_rujukan']},100.00,berjaya,2026-10-15\n{$b['no_rujukan']},90.00,berjaya,2026-10-15\nPJKGW-LUAR-999,55.00,berjaya,2026-10-15\n";
        // $c credited in the ledger but missing from the file.
        $finance = $this->user(self::KEWANGAN);
        $res = $this->actingAs($finance)->post('/api/v1/pjk/rekonsiliasi/fail', [
            'sumber' => 'gateway', 'tarikh' => '2026-10-15', 'fail' => UploadedFile::fake()->createWithContent('penyelesaian.csv', $csv),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(1, $res->json('data.bil_padan'));
        $this->assertSame(3, $res->json('data.bil_sisihan'));

        $diffs = SisihanRekonsiliasi::query()->get()->keyBy('jenis');
        $this->assertSame('PJKGW-LUAR-999', $diffs['tiada_lejar']->rujukan);
        $this->assertSame($c['no_rujukan'], $diffs['tiada_penyelesaian']->rujukan);
        $this->assertSame([$b['no_rujukan'], '100.00', '90.00'], [$diffs['beza_amaun']->rujukan, $diffs['beza_amaun']->amaun_sistem, $diffs['beza_amaun']->amaun_luar]);
        $this->assertCount(1, $this->notifier->ofType('pjk.sisihan_rekonsiliasi'));

        $list = $this->actingAs($finance)->getJson('/api/v1/pjk/rekonsiliasi')->assertOk();
        $this->assertCount(3, $list->json('sisihan'));
        $id = $diffs['beza_amaun']->id;
        $this->actingAs($finance)->putJson("/api/v1/pjk/sisihan/{$id}", ['status_tindakan' => 'selesai', 'catatan' => 'Disemak dengan bank — caj bank RM10'])->assertOk();
        $this->assertCount(2, $this->actingAs($finance)->getJson('/api/v1/pjk/rekonsiliasi')->json('sisihan'));
        $this->actingAs($this->user())->getJson('/api/v1/pjk/rekonsiliasi')->assertForbidden();
    }

    public function test_SRS_PJK_18_daily_run_against_the_gateway_settlement_statement(): void
    {
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-10-01', 'tarikh_tamat' => '2027-09-30']);
        $this->paidOnline($k, 2);
        $this->artisan('pjk:rekonsiliasi', ['--sumber' => 'gateway', '--tarikh' => '2026-10-15'])->assertSuccessful();
        $run = RekonsiliasiLarian::query()->sole();
        $this->assertSame([2, 0, 'selesai'], [$run->bil_padan, $run->bil_sisihan, $run->status]);

        app(PaymentGateway::class)->setDown(true);
        $failed = app(RekonsiliasiService::class)->run('gateway', CarbonImmutable::parse('2026-10-15'));
        $this->assertSame('gagal', $failed->status);
    }

    public function test_SRS_PJK_21_grp_and_esewa_receipts_reconciled_and_differences_reported(): void
    {
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-10-01', 'tarikh_tamat' => '2027-09-30']);
        $this->paidOnline($k, 2);
        $receipts = Resit::query()->orderBy('id')->pluck('no_resit')->all();
        $grp = app(GrpMais::class);
        $this->assertInstanceOf(MockGrpMais::class, $grp);
        $grp->tamper('2026-10-15', $receipts[0], null);          // GRP lost a receipt
        $grp->tamper('2026-10-15', 'PJK/R/LUAR', '20.00');        // GRP has an unknown one

        $svc = app(RekonsiliasiService::class);
        $run = $svc->run('grp', CarbonImmutable::parse('2026-10-15'));
        $this->assertSame([1, 2], [$run->bil_padan, $run->bil_sisihan]);
        $this->assertEqualsCanonicalizing(['tiada_penyelesaian', 'tiada_lejar'], SisihanRekonsiliasi::query()->where('larian_id', $run->id)->pluck('jenis')->all());

        $esewa = $svc->run('esewa', CarbonImmutable::parse('2026-10-15'));
        $this->assertSame([2, 0], [$esewa->bil_padan, $esewa->bil_sisihan]);
        $this->assertCount(1, $this->notifier->ofType('pjk.sisihan_rekonsiliasi'));
    }
}
