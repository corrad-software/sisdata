<?php

namespace App\Modules\Kes\Services;

use Illuminate\Support\Facades\DB;

/**
 * SRS-KES-06: a lot is "dalam tindakan undang-undang" while at least one
 * linked case is in a non-terminal stage; the flag is lifted when every
 * linked case is finished. Kept in kes_status_lot — MOD-PRO's lifecycle
 * status (status_lot) is never overwritten, so the "status asal" is always
 * intact. Exposed through LegalCases::underLegalAction, the 360° profile
 * flag, the GIS layer and the PTN risk signal.
 */
class StatusLot
{
    public function __construct(private Rujukan $ref) {}

    /** @param list<string> $lotIds */
    public function segar(array $lotIds): void
    {
        $lotIds = array_values(array_unique($lotIds));
        if ($lotIds === []) {
            return;
        }
        $terminal = $this->ref->statusTerminal();
        $aktif = DB::table('kes_mahkamah_lot as kl')->join('kes_mahkamah as k', 'k.id', '=', 'kl.kes_mahkamah_id')
            ->whereIn('kl.lot_id', $lotIds)->whereNull('k.deleted_at')->whereNotIn('k.status', $terminal)
            ->groupBy('kl.lot_id')->selectRaw('kl.lot_id, COUNT(*) AS n')->pluck('n', 'lot_id');
        $semasa = DB::table('kes_status_lot')->whereIn('lot_id', $lotIds)->get()->keyBy('lot_id');
        $now = now();
        foreach ($lotIds as $lotId) {
            $n = (int) ($aktif[$lotId] ?? 0);
            $row = $semasa[$lotId] ?? null;
            $dalam = $n > 0;
            if ($row === null) {
                if (! $dalam) {
                    continue;
                }
                DB::table('kes_status_lot')->insert(['lot_id' => $lotId, 'dalam_tindakan' => true, 'bil_kes_aktif' => $n, 'tarikh_mula' => $now, 'tarikh_tamat' => null, 'created_at' => $now, 'updated_at' => $now]);

                continue;
            }
            $update = ['bil_kes_aktif' => $n, 'dalam_tindakan' => $dalam, 'updated_at' => $now];
            if ($dalam && ! $row->dalam_tindakan) {
                $update += ['tarikh_mula' => $now, 'tarikh_tamat' => null];
            } elseif (! $dalam && $row->dalam_tindakan) {
                $update['tarikh_tamat'] = $now;
            }
            DB::table('kes_status_lot')->where('lot_id', $lotId)->update($update);
        }
    }

    /**
     * @param  list<string>  $lotIds
     * @return array<string, bool>
     */
    public function untuk(array $lotIds): array
    {
        $out = array_fill_keys($lotIds, false);
        if ($lotIds === []) {
            return $out;
        }
        foreach (DB::table('kes_status_lot')->whereIn('lot_id', $lotIds)->where('dalam_tindakan', true)->pluck('lot_id') as $id) {
            $out[(string) $id] = true;
        }

        return $out;
    }
}
