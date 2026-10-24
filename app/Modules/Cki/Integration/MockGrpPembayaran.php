<?php

namespace App\Modules\Cki\Integration;

use App\Modules\Cki\Contracts\GrpPembayaran;
use App\Modules\Cki\Contracts\PenyediaTidakTersedia;
use App\Modules\Cki\Support\Wang;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Local stand-in for GRP MAIS outgoing payments. A submitted request is
 * "dibayar" immediately (voucher + EFT reference generated) unless
 * holdPending() is set. tamper() changes / drops what GRP holds, for
 * reconciliation tests and demos.
 */
class MockGrpPembayaran implements GrpPembayaran
{
    private const PREFIX = 'cki:mockgrp:';

    public function hantarPermohonan(array $permohonan): string
    {
        $this->assertUp();
        $ref = 'GRP-PV-'.strtoupper(substr(hash('crc32b', $permohonan['no_permohonan']), 0, 8));
        $today = CarbonImmutable::today()->format('Y-m-d');
        $pending = (bool) Cache::get(self::PREFIX.'tahan');
        $rows = Cache::get(self::PREFIX.'semua', []);
        $rows[$ref] = [
            'rujukan_grp' => $ref,
            'no_permohonan' => $permohonan['no_permohonan'],
            'amaun' => Wang::norm($permohonan['amaun']),
            'tarikh' => $today,
            'status' => $pending ? 'menunggu' : 'dibayar',
            'no_baucar' => 'PV'.substr(hash('crc32b', $ref), 0, 6),
            'no_rujukan_bayaran' => $permohonan['kaedah'] === 'bank_draf' ? 'BD'.substr(hash('crc32b', $ref.'bd'), 0, 8) : 'EFT'.substr(hash('crc32b', $ref.'eft'), 0, 9),
        ];
        Cache::put(self::PREFIX.'semua', $rows, now()->addYear());

        return $ref;
    }

    public function status(string $rujukanGrp): ?array
    {
        $this->assertUp();
        $r = Cache::get(self::PREFIX.'semua', [])[$rujukanGrp] ?? null;

        return $r === null ? null : [
            'status' => $r['status'],
            'no_baucar' => $r['no_baucar'],
            'no_rujukan_bayaran' => $r['status'] === 'dibayar' ? $r['no_rujukan_bayaran'] : null,
            'tarikh_bayar' => $r['status'] === 'dibayar' ? $r['tarikh'] : null,
            'amaun' => $r['amaun'],
        ];
    }

    public function bayaran(CarbonImmutable $dari, CarbonImmutable $hingga): array
    {
        $this->assertUp();
        $out = [];
        foreach (Cache::get(self::PREFIX.'semua', []) as $r) {
            if ($r['status'] === 'dibayar' && $r['tarikh'] >= $dari->format('Y-m-d') && $r['tarikh'] <= $hingga->format('Y-m-d')) {
                $out[] = ['rujukan_grp' => $r['rujukan_grp'], 'no_permohonan' => $r['no_permohonan'], 'amaun' => $r['amaun'], 'tarikh' => $r['tarikh']];
            }
        }

        return $out;
    }

    /** Record a payment GRP made outside the request flow (demo / reconciliation). */
    public function tamper(string $rujukanGrp, ?string $amaun): void
    {
        $rows = Cache::get(self::PREFIX.'semua', []);
        if ($amaun === null) {
            unset($rows[$rujukanGrp]);
        } elseif (isset($rows[$rujukanGrp])) {
            $rows[$rujukanGrp]['amaun'] = Wang::norm($amaun);
        }
        Cache::put(self::PREFIX.'semua', $rows, now()->addYear());
    }

    public function holdPending(bool $hold): void
    {
        $hold ? Cache::put(self::PREFIX.'tahan', true, now()->addDay()) : Cache::forget(self::PREFIX.'tahan');
    }

    public function setDown(bool $down): void
    {
        $down ? Cache::put(self::PREFIX.'down', true, now()->addDay()) : Cache::forget(self::PREFIX.'down');
    }

    private function assertUp(): void
    {
        if (Cache::get(self::PREFIX.'down')) {
            throw new PenyediaTidakTersedia('GRP MAIS tidak dapat dihubungi (simulasi).', 503, 'GRP_TIDAK_TERSEDIA');
        }
    }
}
