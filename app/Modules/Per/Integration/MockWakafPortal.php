<?php

namespace App\Modules\Per\Integration;

use App\Modules\Per\Contracts\WakafPortal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Local stand-in for Portal Hartanah Wakaf Selangor. Pending submissions are
 * whatever was queued with queue() (tests, `per:portal-wakaf --contoh`);
 * acknowledgements are logged. All data is SYNTHETIC.
 */
class MockWakafPortal implements WakafPortal
{
    private const KEY = 'per:mock_portal_wakaf';

    /** @param array<string, mixed> $submission */
    public static function queue(array $submission): void
    {
        $items = Cache::get(self::KEY, []);
        $items[] = $submission;
        Cache::forever(self::KEY, $items);
    }

    public function pending(): array
    {
        $items = Cache::pull(self::KEY, []);

        return is_array($items) ? array_values($items) : [];
    }

    public function acknowledge(string $portalReference, string $status, ?string $caseNumber = null): void
    {
        Log::info('per.portal_wakaf.ack', ['rujukan_portal' => $portalReference, 'status' => $status, 'no_kes' => $caseNumber]);
    }
}
