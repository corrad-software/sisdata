<?php

namespace App\Modules\Ptn\Risiko;

use App\Modules\Ptn\Support\Lalai;
use Illuminate\Support\Facades\DB;

/**
 * Pencerobohan: worst OPEN encroachment finding (pencerobohan, pendudukan
 * haram, binaan haram) — kritikal 100, tinggi 75, sederhana 50, rendah 25;
 * no open one but one closed within the last 365 days → 10.
 */
class IsyaratPencerobohan extends IsyaratAsas
{
    public const SKOR = ['kritikal' => 100, 'tinggi' => 75, 'sederhana' => 50, 'rendah' => 25];

    public function category(): string
    {
        return 'pencerobohan';
    }

    public function scores(array $lotIds): array
    {
        if ($lotIds === []) {
            return [];
        }
        $cats = Lalai::PENCEROBOHAN;
        $rows = DB::table('isu_pemantauan')->whereIn('lot_id', $lotIds)->whereIn('kategori_isu', $cats)
            ->where(fn ($q) => $q->whereIn('status_isu', ['terbuka', 'dalam_tindakan'])->orWhere('tarikh_ditutup', '>=', now()->subDays(365)->toDateString()))
            ->get(['lot_id', 'tahap_keterukan', 'status_isu']);
        $out = [];
        foreach ($rows as $r) {
            $open = in_array($r->status_isu, ['terbuka', 'dalam_tindakan'], true);
            $s = $open ? (self::SKOR[$r->tahap_keterukan] ?? 50) : 10;
            $id = (string) $r->lot_id;
            if (! isset($out[$id]) || $out[$id]['skor'] < $s) {
                $out[$id] = ['skor' => (float) $s, 'butiran' => $open ? 'Penemuan pencerobohan terbuka ('.$r->tahap_keterukan.')' : 'Pencerobohan ditutup dalam 12 bulan lalu'];
            }
        }

        return $out;
    }
}
