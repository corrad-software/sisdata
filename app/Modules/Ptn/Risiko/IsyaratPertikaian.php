<?php

namespace App\Modules\Ptn\Risiko;

use Illuminate\Support\Facades\DB;

/**
 * Pertikaian: active court cases on the lot — one 70, two or more 100; no
 * case but an open boundary-dispute finding → 40.
 */
class IsyaratPertikaian extends IsyaratAsas
{
    public const TAMAT = ['selesai', 'ditutup', 'tamat', 'ditarik_balik', 'dibatalkan'];

    public function category(): string
    {
        return 'pertikaian';
    }

    public function scores(array $lotIds): array
    {
        if ($lotIds === []) {
            return [];
        }
        $out = [];
        foreach (DB::table('isu_pemantauan')->whereIn('lot_id', $lotIds)->where('kategori_isu', 'sempadan')->whereIn('status_isu', ['terbuka', 'dalam_tindakan'])->distinct()->pluck('lot_id') as $id) {
            $out[(string) $id] = ['skor' => 40.0, 'butiran' => 'Pertikaian sempadan (penemuan lawatan)'];
        }
        $rows = DB::table('kes_mahkamah_lot as kl')->join('kes_mahkamah as k', 'k.id', '=', 'kl.kes_mahkamah_id')
            ->whereIn('kl.lot_id', $lotIds)->whereNull('k.deleted_at')->whereNotIn('k.status', self::TAMAT)
            ->groupBy('kl.lot_id')->selectRaw('kl.lot_id, COUNT(*) AS bil')->get();
        foreach ($rows as $r) {
            $out[(string) $r->lot_id] = ['skor' => (int) $r->bil >= 2 ? 100.0 : 70.0, 'butiran' => (int) $r->bil.' kes aktif'];
        }

        return $out;
    }
}
