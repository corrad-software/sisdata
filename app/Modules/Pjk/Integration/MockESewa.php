<?php

namespace App\Modules\Pjk\Integration;

use App\Modules\Pjk\Contracts\ESewa;
use App\Modules\Pjk\Contracts\GatewayException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/** Local stand-in for e-Sewa MAIS: remembers pushed contracts and payments (cache). */
class MockESewa implements ESewa
{
    private const PREFIX = 'pjk:mockesewa:';

    public function syncContract(array $contract): void
    {
        $this->assertUp();
        Cache::put(self::PREFIX.'kontrak:'.$contract['no_kontrak'], $contract, now()->addDays(40));
    }

    public function pushPayment(array $payment): void
    {
        $this->assertUp();
        $key = self::PREFIX.'hari:'.$payment['tarikh'];
        $rows = Cache::get($key, []);
        $rows[$payment['no_resit']] = ['rujukan' => $payment['no_resit'], 'amaun' => $payment['amaun'], 'tarikh' => $payment['tarikh'], 'no_kontrak' => $payment['no_kontrak']];
        Cache::put($key, $rows, now()->addDays(40));
    }

    public function payments(CarbonImmutable $date): array
    {
        $this->assertUp();

        return array_values(Cache::get(self::PREFIX.'hari:'.$date->format('Y-m-d'), []));
    }

    /** @return array<string, mixed>|null */
    public function contract(string $noKontrak): ?array
    {
        return Cache::get(self::PREFIX.'kontrak:'.$noKontrak);
    }

    public function setDown(bool $down): void
    {
        $down ? Cache::put(self::PREFIX.'down', true, now()->addHour()) : Cache::forget(self::PREFIX.'down');
    }

    private function assertUp(): void
    {
        if (Cache::get(self::PREFIX.'down')) {
            throw new GatewayException('e-Sewa MAIS tidak dapat dihubungi (simulasi).');
        }
    }
}
