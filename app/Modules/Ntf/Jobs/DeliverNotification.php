<?php

namespace App\Modules\Ntf\Jobs;

use App\Modules\Ntf\Services\DeliveryService;
use App\Modules\Ntf\Services\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Persistent-queue delivery of one ntf_penghantaran row (SRS-NTF-21). Retries
 * at least three times with increasing backoff (SRS-NTF-17); both come from
 * ADM parameters `ntf.cubaan_maksimum` and `ntf.backoff_saat`.
 */
class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(public int $deliveryId)
    {
        $this->tries = max(4, app(Settings::class)->int('ntf.cubaan_maksimum'));
    }

    /** @return list<int> seconds before each retry, increasing */
    public function backoff(): array
    {
        return app(Settings::class)->backoff();
    }

    public function handle(DeliveryService $deliveries): void
    {
        $deliveries->attempt($this->deliveryId, $this->attempts(), $this->tries);
    }

    public function failed(?Throwable $exception): void
    {
        app(DeliveryService::class)->finalFailure($this->deliveryId, $exception);
    }
}
