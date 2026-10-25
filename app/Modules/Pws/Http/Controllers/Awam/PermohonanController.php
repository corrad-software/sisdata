<?php

namespace App\Modules\Pws\Http\Controllers\Awam;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Pws\Http\Middleware\AuthenticateKlien;
use App\Modules\Pws\Models\Permohonan;
use App\Modules\Pws\Services\Idempotensi;
use App\Modules\Pws\Services\PermohonanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Waqf registration and rental/lease applications (scope "permohonan"),
 * SRS-PWS-07/08/21/24. State-changing calls need an Idempotency-Key and are
 * limited per end-user IP on top of the per-client limit.
 */
class PermohonanController
{
    public function __construct(private PermohonanService $service, private Idempotensi $idempotency, private Parameters $parameters) {}

    public function wakaf(Request $request): JsonResponse
    {
        $klien = AuthenticateKlien::klien($request);
        $this->limitEndUser($request, 'hantar');

        return $this->idempotency->run($request, $klien, 'permohonan.wakaf', function () use ($request, $klien) {
            $row = $this->service->wakaf($request->except(['lampiran']), $this->files($request), $klien);

            return response()->json(['data' => PermohonanService::present($row) + ['rujukan_sisdata' => $row->rujukan_sisdata],
                'message' => __('pws::pws.mesej.permohonan_diterima', ['no' => $row->no_rujukan])], 201);
        });
    }

    public function sewaan(Request $request): JsonResponse
    {
        $klien = AuthenticateKlien::klien($request);
        $ip = AuthenticateKlien::endUserIp($request);
        $this->limitEndUser($request, 'hantar');

        return $this->idempotency->run($request, $klien, 'permohonan.sewaan', function () use ($request, $klien, $ip) {
            $row = $this->service->sewaan($request->except(['lampiran']), $this->files($request), $klien, $ip);

            return response()->json(['data' => PermohonanService::present($row) + ['rujukan_sisdata' => $row->rujukan_sisdata],
                'message' => __('pws::pws.mesej.permohonan_diterima', ['no' => $row->no_rujukan])], 201);
        });
    }

    /** SRS-PWS-09 (status part): reference + e-mail; same 404 for "no such" and "wrong e-mail". */
    public function status(Request $request): JsonResponse
    {
        $this->limitEndUser($request, 'semak');
        $data = $request->validate(['no_rujukan' => ['required', 'string', 'max:30'], 'emel' => ['required', 'email', 'max:150']]);
        $row = $this->service->status($data['no_rujukan'], $data['emel'], AuthenticateKlien::endUserIp($request));
        abort_if($row === null, 404, __('pws::pws.ralat.permohonan_tiada'));

        return response()->json(['data' => PermohonanService::present($row)]);
    }

    /**
     * Two-way status sync for the portal (SRS-PWS-21 iii): references of this
     * client's applications whose status changed since `sejak`.
     */
    public function perubahan(Request $request): JsonResponse
    {
        $klien = AuthenticateKlien::klien($request);
        $data = $request->validate(['sejak' => ['required', 'date']]);
        $rows = Permohonan::query()->where('klien_id', $klien->id)->where('tarikh_status', '>', $data['sejak'])
            ->orderBy('tarikh_status')->limit(200)->get();

        return response()->json(['data' => $rows->map(fn (Permohonan $p) => [
            'no_rujukan' => $p->no_rujukan, 'jenis' => $p->jenis, 'status' => $p->status, 'status_nama' => PermohonanService::label($p->status, $p->status_nama),
            'tarikh_kemas_kini' => $p->tarikh_status?->toIso8601String(),
        ])->all()]);
    }

    /** @return list<UploadedFile> */
    private function files(Request $request): array
    {
        $raw = $request->allFiles()['lampiran'] ?? [];

        return is_array($raw) ? array_values($raw) : [$raw];
    }

    private function limitEndUser(Request $request, string $action): void
    {
        $limit = (int) $this->parameters->get('pws.had_'.$action.'_ip_sejam', $action === 'hantar' ? 10 : 60);
        $key = 'pws:ip:'.$action.':'.sha1(AuthenticateKlien::endUserIp($request));
        if (RateLimiter::tooManyAttempts($key, max(1, $limit))) {
            abort(429, __('pws::pws.ralat.had_kadar'));
        }
        RateLimiter::hit($key, 3600);
    }
}
