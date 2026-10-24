<?php

namespace App\Modules\Cki\Services;

use App\Modules\Adm\Contracts\Integrations;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Cki\Contracts\PenyediaTidakTersedia;
use App\Modules\Cki\Models\LogIntegrasiCukai;
use Closure;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * SRS-CKI-10 / SRS-INT-06/07: every call to the central provider or GRP goes
 * through here — retried with backoff on unavailability, recorded once in
 * `log_integrasi_cukai` (time, status, response time, payload summary, error
 * code, transaction reference, retry count) and in MOD-ADM's integration log
 * (codes cki_mbi / cki_grp) so the ICT health screen and its repeated-failure
 * alert see it too.
 */
class Panggilan
{
    public function __construct(private Parameters $parameters) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $call
     * @param  array<string, mixed>  $ringkasan  identifiers only (masked) — never credentials or full amounts
     * @return T
     *
     * @throws PenyediaTidakTersedia after the last retry
     */
    public function call(string $jenis, ?string $kodAgensi, Closure $call, array $ringkasan = [], ?string $obligasiId = null, string $kodIntegrasi = 'cki_mbi'): mixed
    {
        $attempts = max(1, (int) $this->parameters->get('cki.cuba_semula', 3));
        $backoff = max(0, (int) $this->parameters->get('cki.backoff_ms', 200));
        $started = hrtime(true);
        $error = null;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $result = $call();
                $ref = is_array($result) ? ($result['rujukan_transaksi'] ?? ($result[0]['rujukan_transaksi'] ?? null)) : (is_string($result) ? $result : null);
                $this->log($jenis, $kodAgensi, $obligasiId, true, $started, null, null, $ringkasan + (is_array($result) && array_is_list($result) ? ['bil_rekod' => count($result)] : []), is_string($ref) ? $ref : null, $i, $kodIntegrasi);

                return $result;
            } catch (PenyediaTidakTersedia $e) {
                $error = $e;
                if ($i < $attempts - 1 && $backoff > 0) {
                    Sleep::usleep($backoff * 1000 * (2 ** $i));
                }
            } catch (Throwable $e) {
                $this->log($jenis, $kodAgensi, $obligasiId, false, $started, null, 'RALAT_DALAMAN', $ringkasan, null, $i, $kodIntegrasi, $e->getMessage());
                throw $e;
            }
        }

        $this->log($jenis, $kodAgensi, $obligasiId, false, $started, $error->httpStatus, $error->kodRalat ?? 'TIDAK_TERSEDIA', $ringkasan, null, $attempts - 1, $kodIntegrasi, $error->getMessage());

        throw $error;
    }

    /** @param array<string, mixed> $ringkasan */
    private function log(string $jenis, ?string $agensi, ?string $obligasiId, bool $ok, int $started, ?int $http, ?string $kod, array $ringkasan, ?string $ref, int $retries, string $kodIntegrasi, ?string $message = null): void
    {
        $ms = (int) ((hrtime(true) - $started) / 1_000_000);
        LogIntegrasiCukai::query()->create([
            'jenis_panggilan' => $jenis, 'kod_agensi' => $agensi, 'id_obligasi' => $obligasiId, 'masa_panggilan' => now(),
            'masa_tindak_balas' => $ms, 'status_http' => $http ?? ($ok ? 200 : null), 'berjaya' => $ok, 'kod_ralat' => $kod,
            'ringkasan_payload' => $ringkasan, 'rujukan_transaksi' => $ref, 'bilangan_cuba_semula' => $retries,
        ]);
        if (app()->bound(Integrations::class)) {
            app(Integrations::class)->log($kodIntegrasi, $ok, $http ?? ($ok ? 200 : null), $ms, $message, $jenis, null, ['kod_agensi' => $agensi]);
        }
    }
}
