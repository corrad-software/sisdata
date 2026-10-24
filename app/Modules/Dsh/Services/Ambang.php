<?php

namespace App\Modules\Dsh\Services;

use App\Modules\Dsh\Models\AmbangIndikator;
use Illuminate\Support\Facades\Cache;

/**
 * Traffic-light / heatmap thresholds per indicator, editable by an admin
 * without code (SRS-DSH-10). Without an admin row, a KPI that declares a
 * `sasaran` gets a default: green at/above target, amber within 15 % of it.
 * Some MOD-DSH indicators ship a sensible default (lower-is-better counts).
 */
class Ambang
{
    private const CACHE_KEY = 'dsh:ambang:v1';

    /** @var array<string, array{arah: string, hijau: int|float, kuning: int|float}> */
    public const LALAI = [
        'wfe.tugasan_lewat_sla' => ['arah' => 'rendah', 'hijau' => 0, 'kuning' => 5],
        'hkm.belum_disahkan' => ['arah' => 'rendah', 'hijau' => 0, 'kuning' => 20],
        'per.melebihi_tempoh' => ['arah' => 'rendah', 'hijau' => 0, 'kuning' => 3],
    ];

    /** @var array<string, array{arah: string, hijau: float, kuning: float, sumber: string}>|null */
    private ?array $rows = null;

    /** @return array{arah: string, hijau: float, kuning: float, sumber: string}|null */
    public function for(string $key, ?float $sasaran = null): ?array
    {
        $rows = $this->rows();
        if (isset($rows[$key])) {
            return $rows[$key];
        }
        if (isset(self::LALAI[$key])) {
            return self::LALAI[$key] + ['sumber' => 'lalai'];
        }
        if ($sasaran !== null && $sasaran > 0) {
            return ['arah' => 'tinggi', 'hijau' => $sasaran, 'kuning' => round($sasaran * 0.85, 2), 'sumber' => 'sasaran'];
        }

        return null;
    }

    /** hijau | kuning | merah, or null when the indicator has no threshold / no value. */
    public function status(string $key, int|float|null $value, ?float $sasaran = null): ?string
    {
        $t = $this->for($key, $sasaran);
        if ($t === null || $value === null) {
            return null;
        }

        return self::classify((float) $value, $t);
    }

    /** @param array{arah: string, hijau: float, kuning: float} $t */
    public static function classify(float $value, array $t): string
    {
        if ($t['arah'] === 'rendah') {
            return $value <= $t['hijau'] ? 'hijau' : ($value <= $t['kuning'] ? 'kuning' : 'merah');
        }

        return $value >= $t['hijau'] ? 'hijau' : ($value >= $t['kuning'] ? 'kuning' : 'merah');
    }

    public function flush(): void
    {
        $this->rows = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, array{arah: string, hijau: float, kuning: float, sumber: string}> */
    private function rows(): array
    {
        return $this->rows ??= Cache::remember(self::CACHE_KEY, 300, fn () => AmbangIndikator::query()->get()
            ->mapWithKeys(fn (AmbangIndikator $a) => [$a->kod_indikator => [
                'arah' => $a->arah,
                'hijau' => (float) $a->nilai_hijau,
                'kuning' => (float) $a->nilai_kuning,
                'sumber' => 'pentadbir',
            ]])->all());
    }
}
