<?php

namespace Tests\Modules\Pws;

use App\Modules\Pjk\Contracts\PaymentGateway;
use App\Modules\Pjk\Integration\MockPaymentGateway;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\LejarTransaksi;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Models\TransaksiGateway;
use App\Modules\Pws\Models\SesiBayaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/** SRS-PWS-10..16: online payments by the public through the gateway, reusing MOD-PJK. */
class BayaranPortalTest extends PwsTestCase
{
    private function contract(): Kontrak
    {
        return $this->active($this->user(), null, ['tarikh_mula' => '2026-10-01', 'tarikh_tamat' => '2027-09-30']); // owes 4500.00
    }

    /** @param array<string, string> $headers */
    private function webhook(string $body, array $headers): TestResponse
    {
        return $this->call('POST', '/api/v1/pjk/gateway/panggilan-balik', [], [], [], $this->transformHeadersToServerVars($headers + ['Content-Type' => 'application/json', 'Accept' => 'application/json']), $body);
    }

    private function gateway(): MockPaymentGateway
    {
        $g = app(PaymentGateway::class);
        $this->assertInstanceOf(MockPaymentGateway::class, $g);

        return $g;
    }

    public function test_SRS_PWS_10_amount_is_generated_by_sisdata_not_entered_by_the_payer(): void
    {
        $k = $this->contract();
        $token = $this->token();
        $res = $this->awam('POST', 'bayaran', ['no_kontrak' => $k->no_kontrak, 'saluran' => 'fpx', 'amaun' => '1.00', 'caj_ids' => [1]], $token, $this->kunci())->assertCreated();
        $this->assertSame('4500.00', $res->json('data.jumlah'));
        $this->assertSame('menunggu', $res->json('data.status'));
        $this->assertSame('4500.00', TransaksiGateway::query()->sole()->amaun);
        $this->assertSame('portal', TransaksiGateway::query()->sole()->sumber);

        $this->awam('POST', 'bayaran', ['no_kontrak' => 'PJK-TIADA', 'saluran' => 'fpx'], $token, $this->kunci('2'))->assertUnprocessable()->assertJsonValidationErrors('no_kontrak');
    }

    public function test_SRS_PWS_11_fpx_and_card_channels_are_offered_from_the_gateway_adapter(): void
    {
        $token = $this->token();
        $this->assertSame(['fpx', 'kad'], array_column($this->awam('GET', 'bayaran/saluran', [], $token)->assertOk()->json('data'), 'kod'));
        $this->awam('POST', 'bayaran', ['no_kontrak' => $this->contract()->no_kontrak, 'saluran' => 'tunai'], $token, $this->kunci())->assertUnprocessable()->assertJsonValidationErrors('saluran');
    }

    /** Kriteria penerimaan 3. */
    public function test_SRS_PWS_12_success_only_after_server_side_confirmation_never_on_redirect(): void
    {
        $k = $this->contract();
        $token = $this->token();
        foreach (['fpx' => 'a', 'kad' => 'b'] as $channel => $key) {
            $tx = $this->awam('POST', 'bayaran', ['no_kontrak' => $k->no_kontrak, 'saluran' => $channel], $token, $this->kunci($key))->assertCreated()->json('data');
            $this->assertSame('ujian:'.$tx['no_rujukan'], $tx['url_bayaran']);

            // The browser comes back from the gateway: nothing confirmed yet.
            $this->awam('GET', 'bayaran/'.$tx['no_rujukan'], ['status' => 'berjaya'], $token)->assertOk()->assertJsonPath('data.status', 'menunggu');

            // Gateway signs its server-to-server callback to MOD-PJK.
            $cb = $this->gateway()->simulate($tx['no_rujukan'], 'berjaya');
            $this->assertNotNull($cb);
            $this->webhook($cb['body'], $cb['headers'])->assertOk()->assertJsonPath('keputusan', 'berjaya');
            $this->awam('GET', 'bayaran/'.$tx['no_rujukan'], [], $token)->assertOk()->assertJsonPath('data.status', 'berjaya')->assertJsonPath('data.url_bayaran', null);
            if ($channel === 'fpx') {
                break; // contract fully paid; the second channel is proven below on a new contract
            }
        }
        $s = SesiBayaran::query()->sole();
        $this->assertSame('pertanyaan_status', $s->disahkan_melalui);
        // SRS-PWS-15: official record in SISDATA — ledger credited once, receipt issued.
        $this->assertSame(1, LejarTransaksi::query()->where('kontrak_id', $k->id)->where('jenis', 'bayaran')->count());
        $this->assertSame(1, Resit::query()->count());

        $k2 = $this->contract();
        $tx = $this->awam('POST', 'bayaran', ['no_kontrak' => $k2->no_kontrak, 'saluran' => 'kad'], $token, $this->kunci('c'))->json('data');
        $this->webhook(str_replace('4500.00', '1.00', (string) $this->gateway()->simulate($tx['no_rujukan'], 'berjaya')['body']), [MockPaymentGateway::HEADER => 't='.time().',v1='.str_repeat('0', 64)])->assertStatus(401);
        $this->assertSame(0, LejarTransaksi::query()->where('kontrak_id', $k2->id)->where('jenis', 'bayaran')->count(), 'Panggilan balik tidak sah tidak mengkreditkan lejar.');
    }

    public function test_SRS_PWS_12_missing_callback_is_resolved_by_the_status_query(): void
    {
        $k = $this->contract();
        $token = $this->token();
        $tx = $this->awam('POST', 'bayaran', ['no_kontrak' => $k->no_kontrak, 'saluran' => 'fpx'], $token, $this->kunci())->json('data');
        $this->assertNull($this->gateway()->simulate($tx['no_rujukan'], 'tiada_panggilan_balik'));
        $this->awam('GET', 'bayaran/'.$tx['no_rujukan'], [], $token)->assertOk()->assertJsonPath('data.status', 'berjaya');
    }

    public function test_SRS_PWS_16_no_card_or_bank_data_is_stored(): void
    {
        $k = $this->contract();
        $token = $this->token();
        $this->awam('POST', 'bayaran', ['no_kontrak' => $k->no_kontrak, 'saluran' => 'kad', 'no_kad' => '4111111111111111', 'cvv' => '123'], $token, $this->kunci())->assertCreated();
        $dump = json_encode([DB::table('pws_sesi_bayaran')->get(), DB::table('pws_log_integrasi')->get(), DB::table('transaksi_gateway')->get()]);
        $this->assertStringNotContainsString('4111111111111111', (string) $dump);
        $this->assertSame(['id', 'no_rujukan', 'no_kontrak', 'klien_id', 'jumlah', 'mata_wang', 'saluran_bayaran', 'rujukan_gateway', 'url_bayaran', 'status', 'cap_masa_mula', 'cap_masa_pengesahan', 'disahkan_melalui', 'created_at', 'updated_at'],
            array_keys((array) DB::table('pws_sesi_bayaran')->first()));
    }

    public function test_SRS_PWS_15_idempotent_retry_does_not_create_a_second_transaction(): void
    {
        $k = $this->contract();
        $token = $this->token();
        $a = $this->awam('POST', 'bayaran', ['no_kontrak' => $k->no_kontrak, 'saluran' => 'fpx'], $token, $this->kunci('sama'))->json('data.no_rujukan');
        $b = $this->awam('POST', 'bayaran', ['no_kontrak' => $k->no_kontrak, 'saluran' => 'fpx'], $token, $this->kunci('sama'))->json('data.no_rujukan');
        $this->assertSame($a, $b);
        $this->assertSame(1, TransaksiGateway::query()->count());
    }

    public function test_SRS_PWS_12_test_gateway_simulation_goes_through_the_signed_webhook_and_is_client_bound(): void
    {
        $k = $this->contract();
        $token = $this->token();
        $tx = $this->awam('POST', 'bayaran', ['no_kontrak' => $k->no_kontrak, 'saluran' => 'fpx'], $token, $this->kunci())->json('data');
        $other = $this->token();
        $this->awam('GET', 'bayaran/'.$tx['no_rujukan'], [], $other)->assertNotFound();
        $this->awam('POST', 'bayaran/'.$tx['no_rujukan'].'/simulasi', ['keputusan' => 'berjaya'], $other)->assertNotFound();

        $this->awam('POST', 'bayaran/'.$tx['no_rujukan'].'/simulasi', ['keputusan' => 'berjaya'], $token)->assertOk()->assertJsonPath('data.status', 'berjaya');
        $this->assertSame('panggilan_balik', TransaksiGateway::query()->sole()->disahkan_melalui);

        config(['pws.gateway_ujian' => false]);
        $this->awam('POST', 'bayaran/'.$tx['no_rujukan'].'/simulasi', ['keputusan' => 'gagal'], $token)->assertNotFound();
    }
}
