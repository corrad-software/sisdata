<?php

namespace App\Modules\Cki\Support;

use Illuminate\Support\Facades\DB;

/** Gapless running numbers per kind and year (payment request numbers). Call inside the inserting transaction. */
final class Penomboran
{
    public const FORMAT = [
        'permohonan' => 'CKI/PB/%d/%05d',
        'resit_manual' => 'CKI/RM/%d/%05d',
    ];

    public static function next(string $jenis, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $n = (int) DB::selectOne(
            'INSERT INTO cki_penomboran (jenis, tahun, nombor_akhir) VALUES (?, ?, 1)
             ON CONFLICT (jenis, tahun) DO UPDATE SET nombor_akhir = cki_penomboran.nombor_akhir + 1
             RETURNING nombor_akhir',
            [$jenis, $year],
        )->nombor_akhir;

        return sprintf(self::FORMAT[$jenis] ?? 'CKI/'.strtoupper($jenis).'/%d/%05d', $year, $n);
    }
}
