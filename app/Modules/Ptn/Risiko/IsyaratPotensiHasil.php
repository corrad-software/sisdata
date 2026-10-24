<?php

namespace App\Modules\Ptn\Risiko;

use Illuminate\Support\Facades\DB;

/**
 * Potensi hasil tidak dimanfaatkan: an active lot with no active lease and
 * found idle at the last visit (keadaan terbiar, or an open "tanah terbiar"
 * finding) 100; no active lease only 50; leased 0.
 */
class IsyaratPotensiHasil extends IsyaratAsas
{
    public function category(): string
    {
        return 'potensi_hasil';
    }

    public function scores(array $lotIds): array
    {
        if ($lotIds === []) {
            return [];
        }
        $leased = DB::table('kontrak_pajakan')->whereIn('lot_id', $lotIds)->whereNull('deleted_at')->whereIn('status', IsyaratTamatKontrak::AKTIF)
            ->distinct()->pluck('lot_id')->map(fn ($v) => (string) $v)->flip()->all();
        $active = DB::table('lot_tanah')->whereIn('id', $lotIds)->whereNull('deleted_at')->where('status_lot', 'aktif')->pluck('id')->map(fn ($v) => (string) $v)->all();
        $idle = DB::table('isu_pemantauan')->whereIn('lot_id', $lotIds)->where('kategori_isu', 'tanah_terbiar')->whereIn('status_isu', ['terbuka', 'dalam_tindakan'])
            ->distinct()->pluck('lot_id')->map(fn ($v) => (string) $v)->flip()->all();
        $marks = $this->marks($lotIds);
        foreach (DB::select("SELECT DISTINCT ON (lot_id) lot_id, keadaan_tapak FROM rekod_lawatan WHERE lot_id IN ({$marks}) AND status_lawatan IN ('dihantar', 'disahkan') ORDER BY lot_id, tarikh_lawatan DESC", $lotIds) as $r) {
            if ($r->keadaan_tapak === 'terbiar') {
                $idle[(string) $r->lot_id] = true;
            }
        }
        $out = [];
        foreach ($active as $id) {
            if (isset($leased[$id])) {
                continue;
            }
            $out[$id] = isset($idle[$id])
                ? ['skor' => 100.0, 'butiran' => 'Tiada pajakan aktif dan tanah didapati terbiar']
                : ['skor' => 50.0, 'butiran' => 'Tiada pajakan / sewaan aktif'];
        }

        return $out;
    }
}
