<?php

namespace App\Modules\Pjk\Support;

use Illuminate\Support\Facades\DB;

/**
 * Gapless, sequential running numbers per kind and year (SRS-PJK-17 receipt
 * serials; contract, letter and application references). Call inside the
 * transaction that inserts the numbered row — a rollback gives it back.
 */
final class Penomboran
{
    public const FORMAT = [
        'kontrak' => 'PJK/K/%d/%05d',
        'resit' => 'PJK/R/%d/%06d',
        'resit_kredit' => 'PJK/RK/%d/%06d',
        'bayaran' => 'PJK/B/%d/%06d',
        'caj' => 'PJK/C/%d/%07d',
        'lejar' => 'PJK/L/%d/%07d',
        'gateway' => 'PJKGW%d%07d',
        'permohonan' => 'PJK/P/%d/%05d',
        'surat' => 'PJK/S/%d/%05d',
        'premis' => 'PRM-%d-%05d',
    ];

    public static function next(string $jenis, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        $n = (int) DB::selectOne(
            'INSERT INTO pjk_penomboran (jenis, tahun, nombor_akhir) VALUES (?, ?, 1)
             ON CONFLICT (jenis, tahun) DO UPDATE SET nombor_akhir = pjk_penomboran.nombor_akhir + 1
             RETURNING nombor_akhir',
            [$jenis, $year],
        )->nombor_akhir;

        return sprintf(self::FORMAT[$jenis] ?? 'PJK/'.strtoupper($jenis).'/%d/%05d', $year, $n);
    }
}
