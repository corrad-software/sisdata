<?php

namespace App\Modules\Pws\Http\Controllers\Awam;

use App\Modules\Pws\Http\Middleware\AuthenticateKlien;
use App\Modules\Pws\Services\Idempotensi;
use App\Modules\Pws\Services\InternalApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public complaints (scope "aduan") — forwarded to MOD-ADU's public complaint
 * API (/api/v1/adu/awam/*) with the END USER's IP, so ADU's per-IP and
 * per-phone limits, CAPTCHA, file whitelist and antivirus apply to the member
 * of the public (SRS-ADU-02..04/22/24, SRS-PWS-24). ADU's reference number is
 * returned unchanged; ADU owns routing, SLA and status.
 */
class AduanController
{
    public function __construct(private InternalApi $api) {}

    public function rujukan(Request $request): JsonResponse
    {
        return $this->relay($this->api->call('GET', 'adu/awam/rujukan', [], null, [], AuthenticateKlien::endUserIp($request)));
    }

    public function store(Request $request, Idempotensi $idempotency): JsonResponse
    {
        $klien = AuthenticateKlien::klien($request);
        $ip = AuthenticateKlien::endUserIp($request);

        return $idempotency->run($request, $klien, 'aduan', function () use ($request, $ip) {
            $raw = $request->allFiles()['fail'] ?? [];
            $files = is_array($raw) ? array_values($raw) : [$raw];

            return $this->relay($this->api->call('POST', 'adu/awam/aduan', $request->except(['fail']), null, [], $ip, $files === [] ? [] : ['fail' => $files]));
        });
    }

    public function semak(Request $request): JsonResponse
    {
        return $this->relay($this->api->call('POST', 'adu/awam/semak', $request->only(['no_rujukan_aduan', 'pengesahan']), null, [], AuthenticateKlien::endUserIp($request)));
    }

    /** @param array{status: int, body: array<string, mixed>} $res */
    private function relay(array $res): JsonResponse
    {
        $body = array_intersect_key($res['body'], array_flip(['data', 'message', 'errors', 'code']));

        return response()->json($body, $res['status'] >= 500 ? 503 : $res['status']);
    }
}
