<?php

namespace App\Modules\Adm\Services;

use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use App\Modules\Adm\Contracts\Integrations;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Models\Integrasi;
use App\Modules\Adm\Models\LogIntegrasi;
use App\Modules\Adm\Services\Logging\Redactor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * SRS-ADM-04 settings + SRS-ADM-14 integration log + SRS-ADM-17 alert on
 * repeated integration failure.
 */
class IntegrasiService implements Integrations
{
    public function __construct(private Parameters $parameters, private Amaran $amaran) {}

    public function settings(string $code): ?array
    {
        $row = Integrasi::query()->where('kod', $code)->first();

        return $row ? [
            'kod' => $row->kod,
            'nama' => $row->nama,
            'jenis' => $row->jenis,
            'endpoint' => $row->endpoint,
            'id_rahsia' => $row->id_rahsia,
            'tempoh_tamat_masa' => $row->tempoh_tamat_masa,
            'kadar_capaian' => $row->kadar_capaian,
            'jadual' => $row->jadual,
            'status' => $row->status,
        ] : null;
    }

    public function allow(string $code): bool
    {
        $row = Integrasi::query()->where('kod', $code)->first();
        if (! $row || $row->status !== 'aktif') {
            return false;
        }

        if (! $row->kadar_capaian) {
            return true;
        }

        return RateLimiter::attempt('adm:integrasi:'.$code, $row->kadar_capaian, fn () => true, 60) !== false;
    }

    public function log(string $code, bool $success, ?int $httpStatus = null, ?int $durationMs = null, ?string $message = null, ?string $operation = null, ?int $records = null, array $context = [], string $direction = 'keluar'): void
    {
        try {
            LogIntegrasi::query()->create([
                'kod_integrasi' => $code,
                'id_korelasi' => AssignRequestId::current(),
                'tarikh_masa' => now(),
                'arah' => $direction,
                'operasi' => $operation !== null ? Str::limit($operation, 120, '') : null,
                'berjaya' => $success,
                'status_http' => $httpStatus,
                'tempoh_ms' => $durationMs,
                'bilangan_rekod' => $records,
                'mesej' => Redactor::string($message !== null ? Str::limit($message, 2000) : null),
                'konteks' => Redactor::array($context),
            ]);

            $this->track($code, $success);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Admin "test connection": one GET with the configured timeout, logged like any exchange. */
    public function test(Integrasi $integrasi): array
    {
        $started = microtime(true);
        $status = null;
        $ok = false;
        $message = null;

        try {
            $response = Http::timeout(max(1, min($integrasi->tempoh_tamat_masa, 10)))->connectTimeout(3)->get($integrasi->endpoint);
            $status = $response->status();
            $ok = $status < 500;
            $message = $ok ? 'OK' : 'HTTP '.$status;
        } catch (Throwable $e) {
            $message = Str::limit($e->getMessage(), 300);
        }

        $ms = (int) round((microtime(true) - $started) * 1000);
        $this->log($integrasi->kod, $ok, $status, $ms, $message, 'uji_sambungan');

        return ['berjaya' => $ok, 'status_http' => $status, 'tempoh_ms' => $ms, 'mesej' => Redactor::string($message)];
    }

    /**
     * Apply an approved create/update (SRS-ADM-18).
     *
     * @param  array<string, mixed>  $fields
     */
    public function apply(string $code, array $fields, ?string $justification, ?User $by): Integrasi
    {
        $row = Integrasi::query()->firstOrNew(['kod' => $code]);
        $row->fill(array_intersect_key($fields, array_flip(Integrasi::EDITABLE)));
        $row->justifikasi = $justification;
        $row->dikemas_kini_oleh = $by?->id;
        if ($row->status === 'aktif' && $row->isDirty('status')) {
            $row->kegagalan_berturut = 0;
        }
        $row->save();

        return $row;
    }

    private function track(string $code, bool $success): void
    {
        $row = Integrasi::query()->where('kod', $code)->first();
        if (! $row) {
            return;
        }

        if ($success) {
            $row->forceFill(['kegagalan_berturut' => 0, 'terakhir_berjaya_pada' => now()])->saveQuietly();

            return;
        }

        $row->forceFill(['kegagalan_berturut' => $row->kegagalan_berturut + 1, 'terakhir_gagal_pada' => now()])->saveQuietly();

        $threshold = max(1, (int) $this->parameters->get('adm.ambang_integrasi_gagal', 3));
        if ($row->kegagalan_berturut >= $threshold) {
            $this->amaran->ict('adm.integrasi_gagal', [
                'integrasi' => $row->nama,
                'kod' => $row->kod,
                'bilangan' => $row->kegagalan_berturut,
            ], '/admin/pentadbiran/integrasi', 'integrasi_gagal:'.$row->kod.':'.now()->format('YmdH'));
        }
    }
}
