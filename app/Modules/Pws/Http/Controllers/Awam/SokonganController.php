<?php

namespace App\Modules\Pws\Http\Controllers\Awam;

use App\Modules\Adu\Contracts\CaptchaVerifier;
use App\Modules\Pws\Http\Middleware\AuthenticateKlien;
use App\Modules\Pws\Services\Idempotensi;
use App\Modules\Tkt\Contracts\TicketIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Support requests from the public (scope "sokongan") → MOD-TKT through
 * Tkt\Contracts\TicketIntake (channel "portal"). Until MOD-TKT is enabled the
 * fallback throws and the portal is told the service is unavailable (503) —
 * nothing is lost silently. CAPTCHA via MOD-ADU's verifier (SRS-PWS-24).
 */
class SokonganController
{
    public function __invoke(Request $request, TicketIntake $tickets, CaptchaVerifier $captcha, Idempotensi $idempotency): JsonResponse
    {
        $klien = AuthenticateKlien::klien($request);
        $ip = AuthenticateKlien::endUserIp($request);
        $key = 'pws:ip:sokongan:'.sha1($ip);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, __('pws::pws.ralat.had_kadar'));
        }

        return $idempotency->run($request, $klien, 'sokongan', function () use ($request, $tickets, $captcha, $ip, $key, $klien) {
            $data = $request->validate([
                'tajuk' => ['required', 'string', 'min:5', 'max:200'],
                'keterangan' => ['required', 'string', 'min:20', 'max:5000'],
                'kategori' => ['nullable', Rule::in(['akaun', 'permohonan', 'bayaran', 'teknikal', 'lain'])],
                'emel' => ['required', 'email', 'max:150'],
                'halaman' => ['nullable', 'string', 'max:300'],
                'captcha' => ['required', 'string', 'max:2000'],
            ], [], ['tajuk' => 'tajuk', 'keterangan' => 'keterangan', 'emel' => 'e-mel']);
            RateLimiter::hit($key, 3600);
            if (! $captcha->verify($data['captcha'], $ip)) {
                throw ValidationException::withMessages(['captcha' => [__('pws::pws.ralat.captcha')]]);
            }

            try {
                $id = $tickets->open([
                    'tajuk' => $data['tajuk'], 'keterangan' => $data['keterangan'], 'kategori' => $data['kategori'] ?? 'lain',
                    'keutamaan' => 'biasa', 'emel_pelapor' => $data['emel'],
                    'konteks' => ['sumber' => 'Portal Hartanah Wakaf Selangor', 'halaman' => $data['halaman'] ?? null, 'klien' => $klien->id_klien],
                ], 'portal', null, 'pws:'.$request->header('Idempotency-Key'));
            } catch (RuntimeException $e) {
                report($e);
                abort(503, __('pws::pws.ralat.tiket_tiada'));
            }

            return response()->json(['data' => ['no_tiket' => $id], 'message' => __('pws::pws.mesej.tiket_diterima')], 201);
        });
    }
}
