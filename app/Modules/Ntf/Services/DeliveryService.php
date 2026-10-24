<?php

namespace App\Modules\Ntf\Services;

use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Services\Channels\ChannelManager;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Delivery lifecycle: attempt → hantar/terima, failure → retry with backoff
 * (driven by the queue) → gagal muktamad + admin alert (SRS-NTF-15/16/17).
 * Every attempt outcome is written to the central audit trail (SRS-AUD-17).
 */
class DeliveryService
{
    public const ALERT_TYPE = 'ntf.penghantaran_gagal';

    public function __construct(
        private ChannelManager $channels,
        private AuditLogger $audit,
    ) {}

    /**
     * @throws Throwable to let the queue retry, unless this was the last attempt
     */
    public function attempt(int $deliveryId, int $attempt, int $maxAttempts): void
    {
        $delivery = Penghantaran::query()->with(['mesej.jenis', 'penerima'])->find($deliveryId);

        if (! $delivery || $delivery->selesai() || $delivery->status === Penghantaran::DITAHAN) {
            return;
        }

        if (! $delivery->penerima) {
            $delivery->forceFill(['kod_ralat' => 'PENERIMA_TIADA'])->save();
            $this->finalFailure($deliveryId);

            return;
        }

        $delivery->bilangan_cubaan++;
        $delivery->tarikh_hantar = now();

        try {
            $confirmed = $this->channels->channel($delivery->saluran)->deliver($delivery->mesej, $delivery->penerima);
        } catch (Throwable $e) {
            $delivery->forceFill([
                'status' => Penghantaran::GAGAL,
                'kod_ralat' => $this->errorCode($e),
                'mesej_ralat' => Str::limit($e->getMessage(), 1000),
            ])->save();
            $this->recordAudit($delivery, 'gagal');

            if ($attempt >= $maxAttempts) {
                $this->finalFailure($deliveryId);

                return;
            }

            throw $e;
        }

        $delivery->forceFill([
            'status' => $confirmed ? Penghantaran::DITERIMA : Penghantaran::DIHANTAR,
            'tarikh_terima' => $confirmed ? now() : null,
            'kod_ralat' => null,
            'mesej_ralat' => null,
        ])->save();
        $this->recordAudit($delivery, 'berjaya');
    }

    /** Last retry failed: mark final and tell the system administrators (SRS-NTF-17). */
    public function finalFailure(int $deliveryId, ?Throwable $e = null): void
    {
        $delivery = Penghantaran::query()->with(['mesej.jenis', 'penerima'])->find($deliveryId);

        if (! $delivery || $delivery->status === Penghantaran::GAGAL_MUKTAMAD || in_array($delivery->status, Penghantaran::BERJAYA, true)) {
            return;
        }

        $delivery->forceFill([
            'status' => Penghantaran::GAGAL_MUKTAMAD,
            'tarikh_gagal' => now(),
            'kod_ralat' => $delivery->kod_ralat ?? ($e ? $this->errorCode($e) : 'TIDAK_DIKETAHUI'),
        ])->save();
        $this->recordAudit($delivery, 'gagal');

        // Never alert about a failed alert (no loops). One alert per channel +
        // error code per quiet period of the alert type (rate control).
        if ($delivery->mesej->jenis->kod === self::ALERT_TYPE) {
            return;
        }

        try {
            app(Notifier::class)->send(self::ALERT_TYPE, [], [
                'jenis_notifikasi' => $delivery->mesej->jenis->nama,
                'saluran' => $delivery->saluran,
                'penerima' => $delivery->penerima->name ?? '—',
                'kod_ralat' => $delivery->kod_ralat,
                'bilangan_cubaan' => $delivery->bilangan_cubaan,
                'id_penghantaran' => $delivery->id,
            ], '/admin/notifikasi/pemantauan', 'gagal:'.$delivery->saluran.':'.$delivery->kod_ralat);
        } catch (Throwable $alertError) {
            report($alertError);
        }
    }

    public function errorCode(Throwable $e): string
    {
        if ($e instanceof TransportExceptionInterface) {
            return 'SMTP_TIDAK_TERSEDIA';
        }

        if (preg_match('/^([A-Z][A-Z_]{2,59}):/', $e->getMessage(), $m)) {
            return $m[1];
        }

        return Str::limit('RALAT_'.strtoupper(Str::snake(class_basename($e))), 60, '');
    }

    private function recordAudit(Penghantaran $delivery, string $status): void
    {
        $this->audit->record(
            'hantar_notifikasi',
            'ntf',
            'ntf_penghantaran',
            $delivery->id,
            $status,
            $delivery->kod_ralat,
            meta: [
                'jenis' => $delivery->mesej->jenis->kod,
                'saluran' => $delivery->saluran,
                'penerima_id' => $delivery->penerima_id,
                'cubaan' => $delivery->bilangan_cubaan,
                'status_hantar' => $delivery->status,
                'tarikh_hantar' => $delivery->tarikh_hantar?->toIso8601String(),
                'tarikh_terima' => $delivery->tarikh_terima?->toIso8601String(),
            ],
        );
    }
}
