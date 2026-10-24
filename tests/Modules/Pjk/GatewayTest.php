<?php

namespace Tests\Modules\Pjk;

use App\Modules\Pjk\Contracts\PaymentGateway;
use App\Modules\Pjk\Integration\MockPaymentGateway;
use App\Modules\Pjk\Mail\ResitMail;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\LejarTransaksi;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Models\TransaksiGateway;
use App\Modules\Pjk\Services\GatewayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

class GatewayTest extends PjkTestCase
{
    private function gateway(): MockPaymentGateway
    {
        $g = app(PaymentGateway::class);
        $this->assertInstanceOf(MockPaymentGateway::class, $g);

        return $g;
    }

    /** @param array<string, string> $headers */
    private function webhook(string $body, array $headers): TestResponse
    {
        return $this->call('POST', '/api/v1/pjk/gateway/panggilan-balik', [], [], [], $this->transformHeadersToServerVars($headers + ['Content-Type' => 'application/json', 'Accept' => 'application/json']), $body);
    }

    private function contract(): Kontrak
    {
        return $this->active($this->user(), null, ['tarikh_mula' => '2026-10-01', 'tarikh_tamat' => '2027-09-30']);
    }

    public function test_SRS_PJK_14_acceptance_2_fpx_and_card_payments_credit_ledger_and_email_serial_receipts(): void
    {
        $k = $this->contract(); // owes 3000 deposit + 1500 October = 4500
        $officer = $this->user();
        foreach (['fpx' => '1500.00', 'kad' => '3000.00'] as $channel => $amount) {
            $tx = $this->actingAs($officer)->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => $channel, 'amaun' => $amount, 'emel_pembayar' => 'penyewa@contoh.invalid'])
                ->assertCreated()->assertJsonPath('data.status', 'menunggu')->json('data');
            $this->assertStringStartsWith('/admin/pjk/gateway-ujian/', $tx['url_bayaran']);
            $cb = $this->gateway()->simulate($tx['no_rujukan'], 'berjaya');
            $this->assertNotNull($cb);
            $this->webhook($cb['body'], $cb['headers'])->assertOk()->assertJsonPath('keputusan', 'berjaya');
        }

        $credits = LejarTransaksi::query()->where('kontrak_id', $k->id)->where('jenis', 'bayaran')->orderBy('id')->get();
        $this->assertSame(['fpx', 'kad'], $credits->pluck('kaedah_bayaran')->all());
        $this->assertSame('0.00', $credits->last()->baki_berjalan);
        $this->assertSame(['PJK/R/2026/000001', 'PJK/R/2026/000002'], Resit::query()->orderBy('id')->pluck('no_resit')->all());
        Mail::assertSent(ResitMail::class, 2);
        $this->assertCount(2, $this->dms->ofCategory('resit'));
    }

    public function test_SRS_PJK_15_unique_reference_linked_to_contract_and_charges_with_statuses(): void
    {
        $k = $this->contract();
        $oct = $k->caj()->where('tempoh', '2026-10')->firstOrFail();
        $officer = $this->user();
        $a = $this->actingAs($officer)->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'fpx', 'caj_ids' => [$oct->id]])->assertCreated();
        $this->assertSame('1500.00', $a->json('data.amaun.nilai'));
        $b = $this->actingAs($officer)->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'kad'])->assertCreated();
        $this->assertSame('4500.00', $b->json('data.amaun.nilai'));
        $this->assertNotSame($a->json('data.no_rujukan'), $b->json('data.no_rujukan'));

        $tx = TransaksiGateway::query()->where('no_rujukan', $a->json('data.no_rujukan'))->sole();
        $this->assertSame([$oct->id], $tx->caj_ids);
        $cb = $this->gateway()->simulate($tx->no_rujukan, 'gagal');
        $this->webhook($cb['body'], $cb['headers'])->assertOk();
        $this->assertSame('gagal', $tx->refresh()->status);
        $cb = $this->gateway()->simulate($b->json('data.no_rujukan'), 'dibatalkan');
        $this->webhook($cb['body'], $cb['headers'])->assertOk();
        $this->assertSame('dibatalkan', TransaksiGateway::query()->where('no_rujukan', $b->json('data.no_rujukan'))->value('status'));
        $this->assertSame(0, LejarTransaksi::query()->where('kontrak_id', $k->id)->where('jenis', 'bayaran')->count());

        // A successful payment for specific charges pays exactly those charges.
        $c = $this->actingAs($officer)->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'fpx', 'caj_ids' => [$oct->id]])->json('data');
        $cb = $this->gateway()->simulate($c['no_rujukan'], 'berjaya');
        $this->webhook($cb['body'], $cb['headers'])->assertOk();
        $this->assertSame('dibayar', $oct->refresh()->status_caj);
        $this->assertSame('belum_bayar', $k->caj()->where('kod_caj', 'deposit')->value('status_caj'));
    }

    public function test_SRS_PJK_15_idempotency_key_returns_the_same_transaction(): void
    {
        $k = $this->contract();
        $officer = $this->user();
        $first = $this->actingAs($officer)->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'fpx'], ['Idempotency-Key' => 'abc-123'])->assertCreated()->json('data.no_rujukan');
        $again = $this->actingAs($officer)->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'fpx'], ['Idempotency-Key' => 'abc-123'])->assertOk()->json('data.no_rujukan');
        $this->assertSame($first, $again);
        $this->assertSame(1, TransaksiGateway::query()->count());
    }

    public function test_SRS_PJK_16_callback_signature_is_verified_and_events_are_idempotent(): void
    {
        $k = $this->contract();
        $tx = $this->actingAs($this->user())->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'fpx'])->json('data');
        $cb = $this->gateway()->simulate($tx['no_rujukan'], 'berjaya');

        // Tampered body / bad signature / stale timestamp → rejected, nothing credited.
        $this->webhook(str_replace('4500.00', '1.00', $cb['body']), $cb['headers'])->assertStatus(401);
        $this->webhook($cb['body'], [MockPaymentGateway::HEADER => 't='.time().',v1='.str_repeat('a', 64)])->assertStatus(401);
        $this->webhook($cb['body'], [])->assertStatus(401);
        $stale = $this->gateway()->callback(['no_rujukan' => $tx['no_rujukan'], 'status' => 'berjaya', 'amaun' => '4500.00', 'rujukan_gateway' => 'X'], time() - 3600);
        $this->webhook($stale['body'], $stale['headers'])->assertStatus(401);
        $this->assertSame(0, LejarTransaksi::query()->where('jenis', 'bayaran')->count());
        $this->assertSame(4, DB::table('panggilan_balik_gateway')->where('tandatangan_sah', false)->count());

        $this->webhook($cb['body'], $cb['headers'])->assertOk()->assertJsonPath('keputusan', 'berjaya');
        $this->webhook($cb['body'], $cb['headers'])->assertOk()->assertJsonPath('keputusan', 'pendua'); // same event
        $other = $this->gateway()->callback(['no_rujukan' => $tx['no_rujukan'], 'status' => 'berjaya', 'amaun' => '4500.00', 'rujukan_gateway' => 'X']);
        $this->webhook($other['body'], $other['headers'])->assertOk()->assertJsonPath('keputusan', 'pendua'); // new event, final tx
        $this->assertSame(1, LejarTransaksi::query()->where('jenis', 'bayaran')->count());
        $this->assertSame(1, Resit::query()->count());
    }

    public function test_SRS_PJK_16_acceptance_3_missing_callback_resolved_by_status_query_without_duplicate_credit(): void
    {
        $k = $this->contract();
        $tx = $this->actingAs($this->user())->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'kad'])->json('data');
        $this->assertNull($this->gateway()->simulate($tx['no_rujukan'], 'tiada_panggilan_balik'));
        $svc = app(GatewayService::class);

        $this->assertSame(0, $svc->pollPending()['disemak']); // too early (< 15 minutes)
        Carbon::setTestNow(now()->addMinutes(20));
        $r = $svc->pollPending();
        $this->assertSame(['disemak' => 1, 'diselesaikan' => 1, 'tamat_masa' => 0], $r);
        $t = TransaksiGateway::query()->sole();
        $this->assertSame(['berjaya', 'semakan_status'], [$t->status, $t->disahkan_melalui]);

        // A late callback and another poll change nothing.
        $late = $this->gateway()->callback(['no_rujukan' => $tx['no_rujukan'], 'status' => 'berjaya', 'amaun' => $t->amaun, 'rujukan_gateway' => $t->rujukan_gateway]);
        $this->webhook($late['body'], $late['headers'])->assertOk()->assertJsonPath('keputusan', 'pendua');
        $svc->pollPending();
        $this->assertSame(1, LejarTransaksi::query()->where('jenis', 'bayaran')->count());
    }

    public function test_SRS_PJK_16_unresolved_transactions_end_as_tamat_masa(): void
    {
        $k = $this->contract();
        $tx = $this->actingAs($this->user())->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'fpx'])->json('data');
        $this->gateway()->simulate($tx['no_rujukan'], 'tamat_masa');
        Carbon::setTestNow(now()->addMinutes(61));
        $this->assertSame(1, app(GatewayService::class)->pollPending()['tamat_masa']);
        $this->assertSame('tamat_masa', TransaksiGateway::query()->value('status'));
        $this->assertSame(0, LejarTransaksi::query()->where('jenis', 'bayaran')->count());
    }

    public function test_SRS_PJK_16_amount_mismatch_is_never_credited(): void
    {
        $k = $this->contract();
        $tx = $this->actingAs($this->user())->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'fpx'])->json('data');
        $cb = $this->gateway()->callback(['no_rujukan' => $tx['no_rujukan'], 'status' => 'berjaya', 'amaun' => '10.00', 'rujukan_gateway' => 'X']);
        $this->webhook($cb['body'], $cb['headers'])->assertOk()->assertJsonPath('keputusan', 'amaun_berbeza');
        $this->assertSame(['menunggu', 'AMAUN_BERBEZA'], [TransaksiGateway::query()->value('status'), TransaksiGateway::query()->value('kod_respons')]);
        $this->assertSame(0, LejarTransaksi::query()->where('jenis', 'bayaran')->count());
    }

    public function test_SRS_PJK_14_gateway_outage_falls_back_to_counter_payment(): void
    {
        $k = $this->contract();
        $this->gateway()->setDown(true);
        $this->actingAs($this->user())->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'fpx'])->assertStatus(422)->assertJsonValidationErrors(['gateway']);
        $this->assertSame('gagal', TransaksiGateway::query()->value('status'));
        $this->gateway()->setDown(false);
        $this->actingAs($this->user())->post("/api/v1/pjk/kontrak/{$k->id}/bayaran", ['saluran' => 'kaunter', 'amaun' => '100.00', 'tarikh_bayaran' => '2026-10-15'], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_SRS_PJK_14_test_gateway_page_simulation_goes_through_the_webhook(): void
    {
        $officer = $this->user();
        $k = $this->contract();
        $tx = $this->actingAs($officer)->postJson("/api/v1/pjk/kontrak/{$k->id}/bayaran-dalam-talian", ['saluran' => 'kad'])->json('data');
        $this->actingAs($officer)->getJson('/api/v1/pjk/gateway-ujian/'.$tx['no_rujukan'])->assertOk()->assertJsonPath('data.no_kontrak', $k->no_kontrak);
        $this->actingAs($officer)->postJson('/api/v1/pjk/gateway-ujian/'.$tx['no_rujukan'].'/simulasi', ['keputusan' => 'berjaya'])->assertOk()->assertJsonPath('data.status', 'berjaya')->assertJsonPath('panggilan_balik', 'berjaya');
        $this->actingAs($this->user(['pjk.kontrak.lihat']))->postJson('/api/v1/pjk/gateway-ujian/'.$tx['no_rujukan'].'/simulasi', ['keputusan' => 'berjaya'])->assertForbidden();
    }

    public function test_SRS_PJK_14_portal_can_start_a_payment_for_a_contract(): void
    {
        $k = $this->contract();
        $portal = $this->user(['pjk.portal.cipta']);
        $this->actingAs($portal)->postJson('/api/v1/pjk/portal/bayaran', ['no_kontrak' => $k->no_kontrak, 'saluran' => 'fpx', 'emel_pembayar' => 'p@contoh.invalid'])
            ->assertCreated()->assertJsonPath('data.amaun', '4500.00');
        $this->assertSame('portal', TransaksiGateway::query()->value('sumber'));
        $this->actingAs($this->user(['pjk.kontrak.lihat']))->postJson('/api/v1/pjk/portal/bayaran', ['no_kontrak' => $k->no_kontrak, 'saluran' => 'fpx'])->assertForbidden();
    }
}
