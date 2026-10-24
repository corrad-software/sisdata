<?php

namespace App\Modules\Pjk\Integration;

use App\Modules\Pjk\Contracts\GatewayException;
use App\Modules\Pjk\Contracts\GrpMais;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/** Local stand-in for GRP MAIS: remembers posted receipts per day (cache). */
class MockGrpMais implements GrpMais
{
    private const PREFIX = 'pjk:mockgrp:';

    public function postReceipt(array $receipt): string
    {
        if (Cache::get(self::PREFIX.'down')) {
            throw new GatewayException('GRP MAIS tidak dapat dihubungi (simulasi).');
        }
        $key = self::PREFIX.'hari:'.$receipt['tarikh'];
        $rows = Cache::get($key, []);
        $rows[$receipt['no_resit']] = ['rujukan' => $receipt['no_resit'], 'amaun' => $receipt['amaun'], 'tarikh' => $receipt['tarikh']];
        Cache::put($key, $rows, now()->addDays(40));

        return 'GRP-JV-'.substr(hash('crc32b', $receipt['no_resit']), 0, 8);
    }

    public function receipts(CarbonImmutable $date): array
    {
        if (Cache::get(self::PREFIX.'down')) {
            throw new GatewayException('GRP MAIS tidak dapat dihubungi (simulasi).');
        }

        return array_values(Cache::get(self::PREFIX.'hari:'.$date->format('Y-m-d'), []));
    }

    /** Test / demo hook: change or drop what GRP holds for a receipt. */
    public function tamper(string $date, string $noResit, ?string $amaun): void
    {
        $key = self::PREFIX.'hari:'.$date;
        $rows = Cache::get($key, []);
        if ($amaun === null) {
            unset($rows[$noResit]);
        } else {
            $rows[$noResit] = ['rujukan' => $noResit, 'amaun' => $amaun, 'tarikh' => $date];
        }
        Cache::put($key, $rows, now()->addDays(40));
    }

    public function setDown(bool $down): void
    {
        $down ? Cache::put(self::PREFIX.'down', true, now()->addHour()) : Cache::forget(self::PREFIX.'down');
    }
}
