<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Modules\Pjk\Http\Present;
use App\Modules\Pjk\Models\KiraanAgihanManfaat;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (Awam) feeds for the Portal Hartanah Wakaf Selangor — no personal
 * or Sulit data, rate-limited:
 *  - SRS-PJK-04 catalogue of premises available for offer;
 *  - SRS-PJK-25 confirmed benefit distribution, aggregated (no tenant, no contract).
 */
class AwamController
{
    public function catalogue(Request $request): JsonResponse
    {
        $page = PremisController::filter(PremisAset::query()->withoutGlobalScope('access'), $request)
            ->where('status_premis', 'kosong')->where('papar_portal', true)
            ->orderBy('kod_premis')->paginate(min(50, (int) $request->query('per_page', 20)));

        return response()->json([
            'data' => collect($page->items())->map(fn (PremisAset $p) => Present::premisAwam($p))->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function distribution(Request $request): JsonResponse
    {
        $rows = KiraanAgihanManfaat::query()->where('status_pengesahan', 'disahkan')
            ->when($request->filled('tempoh'), fn ($q) => $q->where('tempoh_perakaunan', $request->query('tempoh')))
            ->when($request->filled('kod_daerah'), fn ($q) => $q->where('kod_daerah', $request->query('kod_daerah')))
            ->get();
        $groups = [];
        foreach ($rows as $r) {
            $key = $r->tempoh_perakaunan.'|'.$r->kod_daerah.'|'.$r->kategori_hartanah.'|'.$r->status_perolehan;
            $groups[$key] ??= ['tempoh' => $r->tempoh_perakaunan, 'kod_daerah' => $r->kod_daerah, 'kategori_hartanah' => Kod::KATEGORI_HARTANAH[$r->kategori_hartanah] ?? $r->kategori_hartanah,
                'status_perolehan' => Kod::STATUS_PEROLEHAN[$r->status_perolehan] ?? $r->status_perolehan, 'bil_tempat' => 0, 'hasil' => 0, 'agihan' => 0, 'penerima' => []];
            $groups[$key]['bil_tempat']++;
            $groups[$key]['hasil'] += Wang::sen($r->hasil_kutipan);
            $groups[$key]['agihan'] += Wang::sen($r->amaun_agihan);
            foreach ($r->pecahan_penerima as $p) {
                $groups[$key]['penerima'][$p['nama']] = ($groups[$key]['penerima'][$p['nama']] ?? 0) + Wang::sen($p['amaun']);
            }
        }

        return response()->json(['data' => array_values(array_map(fn ($g) => [
            ...$g, 'hasil' => Wang::dec($g['hasil']), 'agihan' => Wang::dec($g['agihan']),
            'penerima' => array_map(fn ($n, $s) => ['nama' => $n, 'amaun' => Wang::dec($s)], array_keys($g['penerima']), $g['penerima']),
        ], $groups))]);
    }
}
