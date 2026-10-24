<?php

namespace App\Modules\Cki\Http\Controllers;

use App\Modules\Cki\Models\Penyelarasan;
use App\Modules\Cki\Models\RekonsiliasiLarian;
use App\Modules\Cki\Services\RekonsiliasiService;
use App\Modules\Cki\Support\Kod;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-CKI-20: run reconciliation and list deviations (district-scoped). */
class RekonsiliasiController
{
    public function index(Request $request): JsonResponse
    {
        $runs = RekonsiliasiLarian::query()->orderByDesc('id')->limit(20)->get();
        $runId = $request->integer('larian') ?: $runs->first()?->id;
        $rows = $runId ? Penyelarasan::query()->where('id_larian', $runId)->orderBy('jenis')->orderBy('id')->get() : collect();

        return response()->json(['data' => [
            'larian' => $runs->map(fn (RekonsiliasiLarian $r) => ['id' => $r->id, 'dari' => $r->dari->format('Y-m-d'), 'hingga' => $r->hingga->format('Y-m-d'), 'bil_padan' => $r->bil_padan, 'bil_sisihan' => $r->bil_sisihan, 'dicipta' => $r->created_at->toIso8601String()])->all(),
            'larian_dipilih' => $runId,
            'sisihan' => $rows->map(fn (Penyelarasan $p) => [
                'id' => $p->id, 'jenis' => $p->jenis, 'jenis_nama' => Kod::JENIS_SISIHAN[$p->jenis] ?? $p->jenis, 'id_bil' => $p->id_bil, 'id_transaksi' => $p->id_transaksi,
                'id_resit' => $p->id_resit, 'rujukan_grp' => $p->rujukan_grp, 'amaun_sistem' => $p->amaun_sistem, 'amaun_grp' => $p->amaun_grp, 'amaun_resit' => $p->amaun_resit,
                'catatan' => $p->catatan_sisihan, 'status' => $p->status_padanan, 'kod_daerah' => $p->kod_daerah,
            ])->all(),
        ]]);
    }

    public function store(Request $request, RekonsiliasiService $service): JsonResponse
    {
        $data = $request->validate(['dari' => 'required|date', 'hingga' => 'required|date|after_or_equal:dari']);
        $run = $service->jalankan(CarbonImmutable::parse($data['dari']), CarbonImmutable::parse($data['hingga']), $request->user());

        return response()->json(['data' => ['id' => $run->id, 'bil_padan' => $run->bil_padan, 'bil_sisihan' => $run->bil_sisihan], 'message' => __('cki::cki.mesej.rekonsiliasi')], 201);
    }
}
