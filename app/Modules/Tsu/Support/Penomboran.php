<?php

namespace App\Modules\Tsu\Support;

use Illuminate\Support\Facades\DB;

/** Gap-free running numbers per key (row lock), e.g. TSU/TS/2026/00012. */
final class Penomboran
{
    public const SINGKATAN = [
        'tukar_syarat' => 'TS', 'pecah_sempadan' => 'PS', 'pecah_bahagian' => 'PB', 'penyatuan' => 'PY',
        'pelupusan' => 'PL', 'serah_balik_beri_milik' => 'SB', 'hak_milik_kekal' => 'HK',
    ];

    public static function next(string $prefix, int $pad = 5): string
    {
        $key = $prefix.'/'.now()->format('Y');

        return DB::transaction(function () use ($key, $pad) {
            DB::statement('INSERT INTO tsu_penomboran (kunci, nombor) VALUES (?, 0) ON CONFLICT (kunci) DO NOTHING', [$key]);
            $n = (int) DB::table('tsu_penomboran')->where('kunci', $key)->lockForUpdate()->value('nombor') + 1;
            DB::table('tsu_penomboran')->where('kunci', $key)->update(['nombor' => $n]);

            return $key.'/'.str_pad((string) $n, $pad, '0', STR_PAD_LEFT);
        });
    }

    public static function urusan(string $jenis): string
    {
        return self::next('TSU/'.(self::SINGKATAN[$jenis] ?? 'XX'));
    }
}
