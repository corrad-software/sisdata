<?php

namespace App\Modules\Kes\Risiko;

use App\Modules\Kes\Services\Rujukan;
use App\Modules\Ptn\Contracts\RiskSignal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-PTN risk category "pertikaian" from MOD-KES data (replaces PTN's
 * built-in signal): one active case on the lot 70, two or more 100; +15
 * (capped) when an active case disputes the land itself (ownership /
 * possession / trespass / caveat) or a hearing is due within 30 days. Lots
 * without a case keep PTN's own input — an open boundary-dispute finding
 * (40). Figures only; no Rahsia detail in `butiran`.
 */
class IsyaratPerundangan implements RiskSignal
{
    public const JENIS_TANAH = ['sengketa_pemilikan', 'tuntutan_milikan', 'pencerobohan', 'kaveat'];

    public function __construct(private Rujukan $ref) {}

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
        if (Schema::hasTable('isu_pemantauan')) {
            foreach (DB::table('isu_pemantauan')->whereIn('lot_id', $lotIds)->where('kategori_isu', 'sempadan')->whereIn('status_isu', ['terbuka', 'dalam_tindakan'])->distinct()->pluck('lot_id') as $id) {
                $out[(string) $id] = ['skor' => 40.0, 'butiran' => 'Pertikaian sempadan (penemuan lawatan)'];
            }
        }
        $jenis = "'".implode("','", self::JENIS_TANAH)."'";
        $rows = DB::table('kes_mahkamah_lot as kl')->join('kes_mahkamah as k', 'k.id', '=', 'kl.kes_mahkamah_id')
            ->whereIn('kl.lot_id', $lotIds)->whereNull('k.deleted_at')->where('k.status_arkib', false)->whereNotIn('k.status', $this->ref->statusTerminal())
            ->groupBy('kl.lot_id')
            ->selectRaw("kl.lot_id, COUNT(*) AS bil, BOOL_OR(k.jenis IN ({$jenis})) AS tanah, BOOL_OR(k.tarikh_prosiding_berikutnya IS NOT NULL AND k.tarikh_prosiding_berikutnya <= CURRENT_DATE + 30) AS hampir")
            ->get();
        foreach ($rows as $r) {
            $skor = (int) $r->bil >= 2 ? 100.0 : 70.0;
            if ($r->tanah || $r->hampir) {
                $skor = min(100.0, $skor + 15.0);
            }
            $out[(string) $r->lot_id] = ['skor' => $skor, 'butiran' => (int) $r->bil.' kes mahkamah aktif'.($r->hampir ? ', prosiding dalam 30 hari' : '')];
        }

        return $out;
    }
}
