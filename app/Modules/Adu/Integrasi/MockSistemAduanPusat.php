<?php

namespace App\Modules\Adu\Integrasi;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adu\Contracts\CentralComplaintSystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Local stand-in for the Sistem Aduan berpusat MAIS. fetchNew() returns
 * whatever was queued with queue() (tests, `adu:import-pusat --contoh=N`),
 * pushStatus() records the call (Log + cache list `pushed()`) and can be made
 * to fail with parameter adu.mock_pusat_gagal to exercise the retry outbox.
 */
class MockSistemAduanPusat implements CentralComplaintSystem
{
    private const KEY_QUEUE = 'adu:mock_pusat:baris';

    private const KEY_PUSHED = 'adu:mock_pusat:dihantar';

    public function __construct(private Parameters $parameters) {}

    /** @param array<string, mixed> $complaint */
    public static function queue(array $complaint): void
    {
        $list = Cache::get(self::KEY_QUEUE, []);
        $list[] = $complaint;
        Cache::forever(self::KEY_QUEUE, $list);
    }

    /** @return list<array{id: string, payload: array<string, mixed>}> */
    public static function pushed(): array
    {
        return Cache::get(self::KEY_PUSHED, []);
    }

    public function fetchNew(?string $since = null): array
    {
        $list = Cache::pull(self::KEY_QUEUE, []);

        return array_values(array_filter($list, fn ($c) => $since === null || (string) ($c['tarikh'] ?? '') >= $since));
    }

    public function pushStatus(string $externalId, array $payload): void
    {
        if ((bool) $this->parameters->get('adu.mock_pusat_gagal', false)) {
            throw new RuntimeException('Sistem Aduan berpusat (mock) tidak dapat dihubungi.');
        }
        $list = Cache::get(self::KEY_PUSHED, []);
        $list[] = ['id' => $externalId, 'payload' => $payload];
        Cache::forever(self::KEY_PUSHED, $list);
        Log::info('adu.mock_pusat.status', ['id_luaran' => $externalId, 'status' => $payload['status']]);
    }
}
