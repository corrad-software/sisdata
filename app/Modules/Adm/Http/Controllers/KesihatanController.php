<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Models\Kesihatan;
use App\Modules\Adm\Services\Kesihatan\PemantauKesihatan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-ADM-16: the single health screen. */
class KesihatanController extends Controller
{
    public function __invoke(Request $request, PemantauKesihatan $monitor): JsonResponse
    {
        return response()->json(['data' => $monitor->overview($request->boolean('segar'))]);
    }

    /** Availability history per component (report "kesihatan sistem dan ketersediaan perkhidmatan"). */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate(['komponen' => ['nullable', 'string', 'max:40'], 'hari' => ['nullable', 'integer', 'min:1', 'max:90']]);
        $since = now()->subDays((int) ($data['hari'] ?? 7));

        $rows = Kesihatan::query()->toBase()
            ->where('dicatat_pada', '>=', $since)
            ->when($data['komponen'] ?? null, fn ($q, $k) => $q->where('komponen', $k))
            ->selectRaw("komponen, count(*) as jumlah, sum(case when status = 'ok' then 1 else 0 end) as ok, avg(masa_respons_ms) as purata_ms")
            ->groupBy('komponen')->orderBy('komponen')->get()
            ->map(fn ($r) => [
                'komponen' => $r->komponen,
                'jumlah_semakan' => (int) $r->jumlah,
                'ketersediaan_peratus' => $r->jumlah > 0 ? round($r->ok / $r->jumlah * 100, 2) : null,
                'purata_masa_respons_ms' => $r->purata_ms !== null ? (int) round((float) $r->purata_ms) : null,
            ]);

        return response()->json(['data' => $rows, 'sejak' => $since->toIso8601String()]);
    }
}
