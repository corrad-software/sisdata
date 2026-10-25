<?php

namespace App\Modules\Pws\Http\Controllers\Awam;

use App\Modules\Pjk\Contracts\PaymentGateway;
use App\Modules\Pws\Http\Middleware\AuthenticateKlien;
use App\Modules\Pws\Models\SesiBayaran;
use App\Modules\Pws\Services\AkaunIntegrasi;
use App\Modules\Pws\Services\BayaranService;
use App\Modules\Pws\Services\Idempotensi;
use App\Modules\Pws\Services\InternalApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Online payments (scope "bayaran"), SRS-PWS-10..16. */
class BayaranController
{
    public function __construct(private BayaranService $service, private Idempotensi $idempotency) {}

    public function saluran(PaymentGateway $gateway): JsonResponse
    {
        return response()->json(['data' => array_map(fn (string $c) => ['kod' => $c, 'nama' => __('pws::pws.saluran.'.$c)], $gateway->channels())]);
    }

    public function store(Request $request): JsonResponse
    {
        $klien = AuthenticateKlien::klien($request);
        $ip = AuthenticateKlien::endUserIp($request);

        return $this->idempotency->run($request, $klien, 'bayaran', function () use ($request, $klien, $ip) {
            $sesi = $this->service->initiate($request->all(), $klien, (string) $request->header('Idempotency-Key'), $ip);

            return response()->json(['data' => BayaranService::present($sesi), 'message' => __('pws::pws.mesej.bayaran_dimulakan')], 201);
        });
    }

    /** Status by server-side query — the portal calls this after the browser returns from the gateway. */
    public function show(Request $request, string $rujukan): JsonResponse
    {
        $klien = AuthenticateKlien::klien($request);
        $sesi = SesiBayaran::query()->where('no_rujukan', $rujukan)->where('klien_id', $klien->id)->first();
        abort_if($sesi === null, 404, __('pws::pws.ralat.tiada'));

        return response()->json(['data' => BayaranService::present($this->service->refresh($sesi))]);
    }

    /**
     * NON-PRODUCTION ONLY: the portal's test-gateway page lets a tester choose
     * the outcome; the mock gateway then sends its SIGNED callback to MOD-PJK
     * exactly as a real gateway would. 404 when disabled or not the mock.
     */
    public function simulasi(Request $request, string $rujukan, PaymentGateway $gateway, InternalApi $api, AkaunIntegrasi $accounts): JsonResponse
    {
        abort_unless((bool) config('pws.gateway_ujian') && $gateway->code() === 'mock', 404);
        $klien = AuthenticateKlien::klien($request);
        $sesi = SesiBayaran::query()->where('no_rujukan', $rujukan)->where('klien_id', $klien->id)->first();
        abort_if($sesi === null, 404, __('pws::pws.ralat.tiada'));
        $data = $request->validate(['keputusan' => ['required', Rule::in(['berjaya', 'gagal', 'dibatalkan', 'tiada_panggilan_balik'])]]);

        $res = $api->call('POST', 'pjk/gateway-ujian/'.rawurlencode($rujukan).'/simulasi', $data, $accounts->gatewayUjian(), [], null, [], $rujukan);
        abort_unless($res['status'] === 200, 503, __('pws::pws.ralat.gateway_tiada'));

        return response()->json(['data' => BayaranService::present($this->service->refresh($sesi->refresh()))]);
    }
}
