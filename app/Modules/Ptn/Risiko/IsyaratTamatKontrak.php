<?php

namespace App\Modules\Ptn\Risiko;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tamat tempoh kontrak: active lease/tenancy already past its end date 100,
 * ending within 90 days 60, within 180 days 30 (worst contract counts).
 */
class IsyaratTamatKontrak extends IsyaratAsas
{
    public const AKTIF = ['aktif', 'berkuat_kuasa', 'diperbaharui'];

    public function category(): string
    {
        return 'tamat_kontrak';
    }

    public function scores(array $lotIds): array
    {
        if ($lotIds === []) {
            return [];
        }
        $today = now()->startOfDay();
        $rows = DB::table('kontrak_pajakan')->whereIn('lot_id', $lotIds)->whereNull('deleted_at')->whereIn('status', self::AKTIF)
            ->whereNotNull('tarikh_tamat')->where('tarikh_tamat', '<=', $today->copy()->addDays(180)->toDateString())->get(['lot_id', 'tarikh_tamat']);
        $out = [];
        foreach ($rows as $r) {
            $days = (int) $today->diffInDays(Carbon::parse($r->tarikh_tamat), false);
            [$s, $t] = match (true) {
                $days < 0 => [100.0, 'Kontrak aktif telah tamat tempoh'],
                $days <= 90 => [60.0, "Kontrak tamat dalam {$days} hari"],
                default => [30.0, "Kontrak tamat dalam {$days} hari"],
            };
            $id = (string) $r->lot_id;
            if (! isset($out[$id]) || $out[$id]['skor'] < $s) {
                $out[$id] = ['skor' => $s, 'butiran' => $t];
            }
        }

        return $out;
    }
}
