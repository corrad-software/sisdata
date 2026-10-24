<?php

namespace App\Modules\Rbc\Listeners;

use App\Modules\Rbc\Events\AccessDenied;
use App\Modules\Rbc\Models\AmaranKeselamatan;
use App\Modules\Rbc\Services\LoginMonitor;
use App\Modules\Rbc\Services\SecurityPolicy;
use Illuminate\Support\Facades\Cache;

/**
 * SRS-RBC-22: N refused requests by one user within 10 minutes raise a
 * "capaian tanpa kebenaran" alert (once per window).
 */
class DetectRepeatedDenials
{
    public function __construct(private LoginMonitor $monitor, private SecurityPolicy $policy) {}

    public function handle(AccessDenied $event): void
    {
        if (! $event->user) {
            return;
        }

        $key = 'rbc:tolak:'.$event->user->id;
        Cache::add($key, 0, now()->addMinutes(10));
        $count = (int) Cache::increment($key);

        if ($count === $this->policy->int('amaran_tolak_ambang')) {
            $this->monitor->alert(AmaranKeselamatan::CAPAIAN_TANPA_KEBENARAN, $event->user, request()->ip(), [
                'kebenaran' => $event->permission,
                'sumber' => $event->resource,
                'bilangan' => $count,
                'tetingkap_minit' => 10,
            ]);
        }
    }
}
