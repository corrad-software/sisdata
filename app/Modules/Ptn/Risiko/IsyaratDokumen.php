<?php

namespace App\Modules\Ptn\Risiko;

use Illuminate\Support\Facades\DB;

/**
 * Dokumen tidak lengkap: share of the required DMS categories
 * (ptn.risiko_dokumen_wajib, default geran + pelan) with no active document
 * attached to the lot — missing / required × 100.
 */
class IsyaratDokumen extends IsyaratAsas
{
    public function category(): string
    {
        return 'dokumen';
    }

    /** @return list<string> */
    public function wajib(): array
    {
        $v = $this->parameters->get('ptn.risiko_dokumen_wajib', ['geran', 'pelan']);
        if (is_string($v)) {
            $v = json_decode($v, true) ?? array_map('trim', explode(',', $v));
        }

        return array_values(array_filter(array_map('strval', (array) $v)));
    }

    public function scores(array $lotIds): array
    {
        $required = $this->wajib();
        if ($lotIds === [] || $required === []) {
            return [];
        }
        $have = [];
        $rows = DB::table('dokumen as d')->join('dokumen_kategori as k', 'k.id', '=', 'd.kategori_id')
            ->where('d.jenis_entiti_induk', 'lot_tanah')->whereIn('d.id_rekod_induk', $lotIds)->whereIn('k.kod', $required)
            ->whereNull('d.deleted_at')->where('d.status', 'aktif')->distinct()->get(['d.id_rekod_induk', 'k.kod']);
        foreach ($rows as $r) {
            $have[(string) $r->id_rekod_induk][(string) $r->kod] = true;
        }
        $out = [];
        foreach ($lotIds as $id) {
            $missing = array_values(array_filter($required, fn ($k) => ! isset($have[$id][$k])));
            if ($missing !== []) {
                $out[$id] = ['skor' => round(count($missing) / count($required) * 100, 2), 'butiran' => 'Tiada: '.implode(', ', $missing)];
            }
        }

        return $out;
    }
}
