<?php

namespace App\Modules\Cki\Http\Controllers;

use App\Modules\Cki\Http\Present;
use App\Modules\Cki\Models\Konflik;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Services\Laporan;
use App\Modules\Cki\Services\SemakanService;
use App\Modules\Cki\Services\TugasanManual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** SRS-CKI-03 payment schedule, aliran langkah 3 manual task list, SRS-CKI-09 conflicts. */
class OperasiController
{
    public function schedule(Request $request, Laporan $laporan): JsonResponse
    {
        return response()->json(['data' => $laporan->jadual($request->user(), $request->query())]);
    }

    public function tasks(Request $request, TugasanManual $tugasan): JsonResponse
    {
        $tahun = (int) $request->query('tahun', Carbon::today()->format('Y'));
        $q = $tugasan->query($tahun, $request->user())->with('agensi')->leftJoin('lot_tanah as l', 'l.id', '=', 'obligasi_cukai.lot_id')
            ->select('obligasi_cukai.*', 'l.no_lot')
            ->when($request->filled('kod_daerah'), fn ($w) => $w->where('obligasi_cukai.kod_daerah', $request->query('kod_daerah')))
            ->orderBy('obligasi_cukai.kod_daerah')->orderBy('l.no_lot');
        $page = $q->paginate(min(100, max(5, (int) $request->query('per_page', 20))));

        return response()->json([
            'data' => collect($page->items())->map(fn (Obligasi $o) => Present::obligasi($o))->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'tahun' => $tahun],
        ]);
    }

    public function conflicts(Request $request): JsonResponse
    {
        $q = Konflik::query()->with('bil')->when($request->query('status', 'menunggu') !== 'semua', fn ($w) => $w->where('status_resolusi', $request->query('status', 'menunggu')))
            ->orderByDesc('id');
        $page = $q->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Konflik $k) => Present::konflik($k))->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function resolve(Request $request, Konflik $konflik, SemakanService $semakan): JsonResponse
    {
        $data = $request->validate(['keputusan' => ['required', Rule::in(['terima_api', 'kekal_manual'])], 'catatan' => 'required|string|min:5|max:2000']);

        return response()->json(['data' => Present::konflik($semakan->selesaikan($konflik, $data['keputusan'], $request->user(), $data['catatan'])), 'message' => __('cki::cki.mesej.konflik')]);
    }
}
