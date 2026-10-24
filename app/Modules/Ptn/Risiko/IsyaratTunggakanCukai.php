<?php

namespace App\Modules\Ptn\Risiko;

use Illuminate\Support\Facades\DB;

/**
 * Tunggakan cukai: unpaid tax bills past their due date — score = overdue
 * amount / ptn.risiko_cukai_rm_penuh × 100 (capped at 100); an overdue bill
 * without an amount counts 50.
 */
class IsyaratTunggakanCukai extends IsyaratAsas
{
    public const DIBAYAR = ['dibayar', 'selesai', 'lunas', 'dikecualikan', 'dibatalkan'];

    public function category(): string
    {
        return 'tunggakan_cukai';
    }

    public function scores(array $lotIds): array
    {
        if ($lotIds === []) {
            return [];
        }
        $full = max(1.0, (float) $this->parameters->get('ptn.risiko_cukai_rm_penuh', 5000));
        $rows = DB::table('cukai')->whereIn('lot_id', $lotIds)->whereNull('deleted_at')
            ->whereNotIn('status_bayaran', self::DIBAYAR)->where('tarikh_akhir', '<', now()->toDateString())
            ->groupBy('lot_id')->selectRaw('lot_id, COUNT(*) AS bil, SUM(COALESCE(jumlah, 0)) AS amaun, BOOL_OR(jumlah IS NULL) AS tanpa_amaun')->get();
        $out = [];
        foreach ($rows as $r) {
            $s = min(100.0, round((float) $r->amaun / $full * 100, 2));
            if ($r->tanpa_amaun) {
                $s = max($s, 50.0);
            }
            $out[(string) $r->lot_id] = ['skor' => $s, 'butiran' => (int) $r->bil.' bil cukai tertunggak'];
        }

        return $out;
    }
}
