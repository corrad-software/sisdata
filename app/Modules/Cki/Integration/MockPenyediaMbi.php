<?php

namespace App\Modules\Cki\Integration;

use App\Modules\Cki\Contracts\PenyediaCukaiBerpusat;
use App\Modules\Cki\Contracts\PenyediaTidakTersedia;
use App\Modules\Cki\Support\Wang;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Local stand-in for the central provider (MBI). Bills are derived
 * deterministically from the account number and year (synthetic, realistic
 * Selangor-ish amounts and due dates); payments made through it are
 * remembered in the cache. Demo / test hooks: setDown() simulates an outage
 * (globally or per agency), override() changes what the API returns for a
 * bill (to create sync conflicts).
 */
class MockPenyediaMbi implements PenyediaCukaiBerpusat
{
    private const PREFIX = 'cki:mockmbi:';

    public function code(): string
    {
        return 'mock_mbi';
    }

    public function ping(string $kodAgensi): void
    {
        $this->assertUp($kodAgensi);
    }

    public function semakBil(array $akaun): array
    {
        $this->assertUp($akaun['kod_agensi']);
        $tahun = (int) $akaun['tahun'];
        $seed = (int) hexdec(substr(hash('crc32b', $akaun['jenis_cukai'].'|'.$akaun['no_akaun']), 0, 7));
        [$min, $span, $due] = match ($akaun['jenis_cukai']) {
            'taksiran_pbt' => [180, 4200, '11-30'],
            'petak' => [40, 900, '05-31'],
            'premium', 'fi' => [500, 9000, '12-31'],
            default => [60, 2400, '05-31'],
        };
        $base = $min + ($seed % $span);
        // Small yearly revision (cukai semakan semula): +0–3 % per year from 2024.
        $pokok = Wang::norm(bcmul((string) $base, bcadd('1', bcmul('0.015', (string) max(0, $tahun - 2024), 4), 4), 2));
        $tarikhAkhir = CarbonImmutable::parse($tahun.'-'.$due);
        $noBil = strtoupper(substr($akaun['jenis_cukai'], 0, 2)).'-'.$tahun.'-'.substr(str_pad((string) $seed, 8, '0', STR_PAD_LEFT), -8);
        $today = CarbonImmutable::today();

        $paid = Cache::get(self::PREFIX.'bayar:'.$noBil);
        $status = $paid ? 'dibayar' : 'belum_bayar';
        $denda = '0.00';
        if (! $paid && $tarikhAkhir->lt($today)) {
            $denda = Wang::max(Wang::percent($pokok, '10'), '10.00');
        }
        $bill = [
            'no_bil' => $noBil,
            'tahun_taksiran' => $tahun,
            'amaun_pokok' => $pokok,
            'amaun_denda' => $denda,
            'tarikh_bil' => CarbonImmutable::parse($tahun.'-01-02')->format('Y-m-d'),
            'tarikh_akhir' => $tarikhAkhir->format('Y-m-d'),
            'status_bayaran' => $status,
            'tarikh_bayar' => $paid['tarikh'] ?? null,
            'no_resit' => $paid['no_resit'] ?? null,
            'rujukan_transaksi' => 'MBI-Q-'.substr(hash('crc32b', $noBil.$today->format('Ymd')), 0, 8),
        ];

        $override = Cache::get(self::PREFIX.'ganti:'.$akaun['no_akaun'].':'.$tahun);
        if (is_array($override)) {
            $bill = array_merge($bill, $override);
        }

        return [$bill];
    }

    public function bayar(array $bayaran): array
    {
        $this->assertUp($bayaran['kod_agensi']);
        $existing = Cache::get(self::PREFIX.'bayar:'.$bayaran['no_bil']);
        if ($existing) {
            return $existing; // idempotent on the bill
        }
        $receipt = [
            'no_resit' => 'MBI-R-'.strtoupper(substr(hash('sha1', $bayaran['rujukan'].$bayaran['no_bil']), 0, 10)),
            'amaun' => Wang::norm($bayaran['amaun']),
            'tarikh' => CarbonImmutable::today()->format('Y-m-d'),
            'rujukan_transaksi' => 'MBI-P-'.strtoupper(substr(hash('crc32b', $bayaran['rujukan']), 0, 8)),
        ];
        Cache::put(self::PREFIX.'bayar:'.$bayaran['no_bil'], $receipt, now()->addYear());

        return $receipt;
    }

    /** Simulate an outage (null agency = whole provider). */
    public function setDown(bool $down, ?string $kodAgensi = null): void
    {
        $key = self::PREFIX.'down:'.($kodAgensi ?? '*');
        $down ? Cache::put($key, true, now()->addDay()) : Cache::forget($key);
    }

    public function isDown(?string $kodAgensi = null): bool
    {
        return (bool) Cache::get(self::PREFIX.'down:*') || ($kodAgensi !== null && (bool) Cache::get(self::PREFIX.'down:'.$kodAgensi));
    }

    /** @param array<string, string|int|null> $fields */
    public function override(string $noAkaun, int $tahun, array $fields): void
    {
        Cache::put(self::PREFIX.'ganti:'.$noAkaun.':'.$tahun, $fields, now()->addDay());
    }

    private function assertUp(string $kodAgensi): void
    {
        if ($this->isDown($kodAgensi)) {
            throw new PenyediaTidakTersedia('Penyedia integrasi berpusat tidak dapat dihubungi (simulasi).', 503, 'MBI_TIDAK_TERSEDIA');
        }
    }
}
