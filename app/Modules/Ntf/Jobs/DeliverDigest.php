<?php

namespace App\Modules\Ntf\Jobs;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Mail\RingkasanMail;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Services\DeliveryService;
use App\Modules\Ntf\Services\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/** One digest e-mail bundling a user's held Biasa/Maklumat e-mail deliveries (SRS-NTF-20). */
class DeliverDigest implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @param list<int> $deliveryIds */
    public function __construct(public int $userId, public array $deliveryIds)
    {
        $this->tries = max(4, app(Settings::class)->int('ntf.cubaan_maksimum'));
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return app(Settings::class)->backoff();
    }

    public function handle(AuditLogger $audit, DeliveryService $deliveries): void
    {
        $user = User::query()->find($this->userId);
        $rows = Penghantaran::query()->with('mesej.jenis')
            ->whereIn('id', $this->deliveryIds)
            ->where('status', Penghantaran::DITAHAN)
            ->get();

        if ($rows->isEmpty() || ! $user) {
            return;
        }

        $now = now();
        try {
            Mail::to($user->email, $user->name)->send(new RingkasanMail($rows->pluck('mesej')->sortByDesc('created_at')->values(), $user));
        } catch (Throwable $e) {
            foreach ($rows as $row) {
                $row->forceFill([
                    'bilangan_cubaan' => $row->bilangan_cubaan + 1,
                    'tarikh_hantar' => $now,
                    'kod_ralat' => $deliveries->errorCode($e),
                    'mesej_ralat' => Str::limit($e->getMessage(), 1000),
                ])->save();
            }

            throw $e;
        }

        foreach ($rows as $row) {
            $row->forceFill([
                'status' => Penghantaran::DITERIMA,
                'bilangan_cubaan' => $row->bilangan_cubaan + 1,
                'tarikh_hantar' => $now,
                'tarikh_terima' => $now,
                'kod_ralat' => null,
                'mesej_ralat' => null,
            ])->save();

            $audit->record('hantar_notifikasi', 'ntf', 'ntf_penghantaran', $row->id, 'berjaya', meta: [
                'jenis' => $row->mesej->jenis->kod,
                'saluran' => 'emel',
                'penerima_id' => $row->penerima_id,
                'ringkasan' => true,
                'status_hantar' => Penghantaran::DITERIMA,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $deliveries = app(DeliveryService::class);

        foreach ($this->deliveryIds as $id) {
            $deliveries->finalFailure($id, $exception);
        }
    }
}
