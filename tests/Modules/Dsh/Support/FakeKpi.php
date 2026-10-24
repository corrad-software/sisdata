<?php

namespace Tests\Modules\Dsh\Support;

use App\Models\User;
use App\Modules\Dsh\Contracts\KpiProvider;
use RuntimeException;

/** A KPI registered by "another module" in tests (e.g. MOD-PTN, built in parallel). */
class FakeKpi implements KpiProvider
{
    public static int $calls = 0;

    public static bool $fail = false;

    /** @var array<string, mixed> */
    public static array $lastFilters = [];

    public function key(): string
    {
        return 'ptn.risiko_tinggi';
    }

    public function label(): string
    {
        return 'Lot berisiko tinggi';
    }

    public function domain(): string
    {
        return 'risiko';
    }

    public function permission(): string
    {
        return 'ptn.laporan.lihat';
    }

    public function compute(User $user, array $filters = []): array
    {
        self::$calls++;
        self::$lastFilters = $filters;
        if (self::$fail) {
            throw new RuntimeException('sumber tidak tersedia');
        }

        return [
            'nilai' => 42.0, 'unit' => '%', 'sasaran' => 80.0,
            'trend' => [['tempoh' => '2026-01', 'nilai' => 30], ['tempoh' => '2026-02', 'nilai' => 42]],
            'pecahan' => [['label' => 'Petaling', 'nilai' => 30, 'kod' => 'daerah:1008'], ['label' => 'Sepang', 'nilai' => 12, 'kod' => 'daerah:1009']],
            'pautan' => '/admin/ptn/laporan?jenis=risiko',
        ];
    }
}
