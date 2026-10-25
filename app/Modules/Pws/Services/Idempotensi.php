<?php

namespace App\Modules\Pws\Services;

use App\Modules\Pws\Models\KlienApi;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Idempotency for every state-changing public call (SRS-PWS-22 retry
 * safety): the portal sends `Idempotency-Key`; a retry with the same key and
 * body replays the stored response; the same key with a different body is
 * refused (422); a concurrent duplicate gets 409. Only successful (2xx)
 * outcomes are stored, so after a validation error the user can correct the
 * form and resubmit with the same key.
 */
class Idempotensi
{
    private const JAM = 24;

    /** @param Closure(): JsonResponse $handler */
    public function run(Request $request, KlienApi $klien, string $endpoint, Closure $handler): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '' || mb_strlen($key) > 100 || ! preg_match('/^[A-Za-z0-9._:\-]{8,100}$/', $key)) {
            throw new HttpException(400, __('pws::pws.ralat.kunci_idempotensi_wajib'));
        }
        $fingerprint = hash('sha256', $endpoint.'|'.json_encode($this->normalise($request->except(['captcha']))));

        DB::table('pws_idempotensi')->where('tamat_pada', '<', now())->delete();
        $existing = DB::table('pws_idempotensi')->where('klien_id', $klien->id)->where('kunci', $key)->first();
        if ($existing !== null) {
            return $this->replay($existing, $fingerprint);
        }

        try {
            $id = DB::table('pws_idempotensi')->insertGetId([
                'klien_id' => $klien->id, 'kunci' => $key, 'endpoint' => $endpoint, 'cincangan_permintaan' => $fingerprint,
                'tamat_pada' => now()->addHours(self::JAM), 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $row = DB::table('pws_idempotensi')->where('klien_id', $klien->id)->where('kunci', $key)->first();

            return $row !== null ? $this->replay($row, $fingerprint) : throw new HttpException(409, __('pws::pws.ralat.idempotensi_sedang'));
        }

        try {
            $response = $handler();
        } catch (\Throwable $e) {
            DB::table('pws_idempotensi')->where('id', $id)->delete(); // nothing happened — the key may be reused

            throw $e;
        }

        if ($response->getStatusCode() >= 300) { // only a completed action is replayed
            DB::table('pws_idempotensi')->where('id', $id)->delete();
        } else {
            DB::table('pws_idempotensi')->where('id', $id)->update([
                'kod_status' => $response->getStatusCode(), 'respons' => $response->getContent(), 'updated_at' => now(),
            ]);
        }

        return $response;
    }

    private function replay(object $row, string $fingerprint): JsonResponse
    {
        if (! hash_equals((string) $row->cincangan_permintaan, $fingerprint)) {
            throw new HttpException(422, __('pws::pws.ralat.kunci_idempotensi_berbeza'));
        }
        if ($row->kod_status === null) {
            throw new HttpException(409, __('pws::pws.ralat.idempotensi_sedang'));
        }
        $body = json_decode((string) $row->respons, true);

        return response()->json(is_array($body) ? $body : [], (int) $row->kod_status)->header('Idempotent-Replayed', 'true');
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function normalise(array $data): array
    {
        ksort($data);
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->normalise($v);
            } elseif (is_object($v)) {
                $data[$k] = method_exists($v, 'getClientOriginalName') ? 'fail:'.$v->getClientOriginalName().':'.(method_exists($v, 'getSize') ? $v->getSize() : '') : get_class($v);
            }
        }

        return $data;
    }
}
