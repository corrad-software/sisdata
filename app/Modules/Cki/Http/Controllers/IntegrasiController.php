<?php

namespace App\Modules\Cki\Http\Controllers;

use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Cki\Contracts\GrpPembayaran;
use App\Modules\Cki\Contracts\PenyediaCukaiBerpusat;
use App\Modules\Cki\Http\Present;
use App\Modules\Cki\Integration\MockGrpPembayaran;
use App\Modules\Cki\Integration\MockPenyediaMbi;
use App\Modules\Cki\Models\Liputan;
use App\Modules\Cki\Models\LogIntegrasiCukai;
use App\Modules\Cki\Models\ModOperasi;
use App\Modules\Cki\Services\LiputanService;
use App\Modules\Cki\Services\SemakanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** SRS-CKI-04/06/08/10/24: coverage register, operating modes, integration log, on-demand check and (mock only) outage simulation. */
class IntegrasiController
{
    public function __construct(private LiputanService $liputan) {}

    public function index(PenyediaCukaiBerpusat $provider): JsonResponse
    {
        $counts = DB::table('obligasi_cukai')->whereNull('deleted_at')->where('aktif', true)->groupBy('kod_agensi')->selectRaw('kod_agensi, count(*) as n')->pluck('n', 'kod_agensi');

        return response()->json(['data' => [
            'penyedia' => $provider->code(),
            'mock' => $provider instanceof MockPenyediaMbi,
            'mock_turun' => $provider instanceof MockPenyediaMbi && $provider->isDown(),
            'agensi' => Liputan::query()->orderBy('jenis_agensi')->orderBy('kod_agensi')->get()->map(fn (Liputan $l) => Present::liputan($l) + ['bil_obligasi' => (int) ($counts[$l->kod_agensi] ?? 0)])->all(),
            'mod_aktif' => ModOperasi::query()->whereNull('tarikh_tamat')->orderByDesc('tarikh_mula')->get()->map(fn (ModOperasi $m) => [
                'id' => $m->id, 'kod_agensi' => $m->kod_agensi, 'mod' => $m->mod, 'tarikh_mula' => $m->tarikh_mula->toIso8601String(), 'sebab' => $m->sebab,
            ])->all(),
        ]]);
    }

    public function update(Request $request, Liputan $liputan): JsonResponse
    {
        $data = $request->validate(['nama_agensi' => 'sometimes|string|max:200', 'perkhidmatan_semakan' => 'sometimes|boolean', 'perkhidmatan_bayaran' => 'sometimes|boolean', 'catatan' => 'nullable|string|max:2000']);

        return response()->json(['data' => Present::liputan($this->liputan->kemaskini($liputan, $data)), 'message' => __('cki::cki.mesej.liputan')]);
    }

    public function seed(): JsonResponse
    {
        return response()->json(['data' => ['ditambah' => $this->liputan->lalai()]]);
    }

    public function check(Request $request, SemakanService $semakan): JsonResponse
    {
        $data = $request->validate(['kod_agensi' => 'nullable|string|max:30', 'tahun' => 'nullable|integer|min:1990|max:2100']);

        return response()->json(['data' => $semakan->jalankan($data['kod_agensi'] ?? null, isset($data['tahun']) ? (int) $data['tahun'] : null, $request->user())]);
    }

    public function log(Request $request): JsonResponse
    {
        $page = LogIntegrasiCukai::query()
            ->when($request->filled('kod_agensi'), fn ($w) => $w->where('kod_agensi', $request->query('kod_agensi')))
            ->when($request->query('berjaya') !== null && $request->query('berjaya') !== '', fn ($w) => $w->where('berjaya', $request->boolean('berjaya')))
            ->orderByDesc('id')->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (LogIntegrasiCukai $l) => [
                'id' => $l->id, 'jenis_panggilan' => $l->jenis_panggilan, 'kod_agensi' => $l->kod_agensi, 'masa_panggilan' => $l->masa_panggilan->toIso8601String(),
                'masa_tindak_balas' => $l->masa_tindak_balas, 'status_http' => $l->status_http, 'berjaya' => $l->berjaya, 'kod_ralat' => $l->kod_ralat,
                'ringkasan_payload' => $l->ringkasan_payload, 'rujukan_transaksi' => $l->rujukan_transaksi, 'bilangan_cuba_semula' => $l->bilangan_cuba_semula,
            ])->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /** Test / demo hook — only while the mock adapters are bound (never against a live provider). */
    public function simulate(Request $request, AuditLogger $audit, PenyediaCukaiBerpusat $mbi, GrpPembayaran $grp): JsonResponse
    {
        $data = $request->validate(['sasaran' => 'required|in:mbi,grp', 'turun' => 'required|boolean', 'kod_agensi' => 'nullable|string|max:30']);
        abort_unless($data['sasaran'] === 'mbi' ? $mbi instanceof MockPenyediaMbi : $grp instanceof MockGrpPembayaran, 404);
        if ($data['sasaran'] === 'mbi' && $mbi instanceof MockPenyediaMbi) {
            $mbi->setDown((bool) $data['turun'], $data['kod_agensi'] ?? null);
        } elseif ($grp instanceof MockGrpPembayaran) {
            $grp->setDown((bool) $data['turun']);
        }
        $audit->record('simulasi_integrasi', 'cki', 'daftar_liputan_integrasi', $data['kod_agensi'] ?? null, meta: $data);

        return response()->json(['data' => $data, 'message' => __('cki::cki.mesej.simulasi')]);
    }
}
