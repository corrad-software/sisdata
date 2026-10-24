<?php

namespace App\Modules\Tsu\Http\Controllers;

use App\Modules\Tsu\Models\TemplatPeringkat;
use App\Modules\Tsu\Models\TemplatProses;
use App\Modules\Tsu\Services\TemplatService;
use App\Modules\Tsu\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-TSU-01/02: process templates — admins version them without a code release. */
class TemplatController extends TsuController
{
    private const KESAN = ['perlu_lantikan', 'perlu_hasil_ukur_diterima', 'perlu_rujukan_etanah', 'perlu_rujukan_ptd', 'perlu_kos_dibayar', 'perlu_penilaian', 'perlu_pemindahan_selesai', 'kemas_kini_syarat', 'cipta_lot_baharu', 'arkib_lot_dilupus', 'cadang_geometri'];

    public function index(Request $request): JsonResponse
    {
        $rows = TemplatProses::query()->with('peringkat')->when(! $request->boolean('semua_versi'), fn ($q) => $q->where('status_aktif', true))->orderBy('jenis_urusan')->orderByDesc('versi')->get();

        return response()->json(['data' => $rows->map(fn (TemplatProses $t) => $this->out($t))->values()]);
    }

    public function show(string $jenis): JsonResponse
    {
        abort_unless(isset(Lalai::JENIS_URUSAN[$jenis]), 404);
        $all = TemplatProses::query()->with('peringkat')->where('jenis_urusan', $jenis)->orderByDesc('versi')->get();

        return response()->json(['data' => ['aktif' => $all->firstWhere('status_aktif', true) ? $this->out($all->firstWhere('status_aktif', true)) : null, 'versi' => $all->map(fn ($t) => ['id' => $t->id, 'versi' => $t->versi, 'status_aktif' => $t->status_aktif, 'created_at' => $t->created_at?->toIso8601String()])->values()]]);
    }

    public function store(Request $request, string $jenis, TemplatService $svc): JsonResponse
    {
        abort_unless(isset(Lalai::JENIS_URUSAN[$jenis]), 404);
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'], 'keterangan' => ['nullable', 'string', 'max:2000'], 'aliran_kelulusan' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_.]+$/'],
            'peringkat' => ['required', 'array', 'min:1', 'max:40'],
            'peringkat.*.kod' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'distinct'], 'peringkat.*.nama_peringkat' => ['required', 'string', 'max:150'],
            'peringkat.*.tindakan_diperlukan' => ['nullable', 'string', 'max:2000'], 'peringkat.*.pihak_bertanggungjawab' => ['required', Rule::in(array_keys(Lalai::PIHAK))],
            'peringkat.*.tempoh_sasaran_hari' => ['required', 'integer', 'min:1', 'max:1000'],
            'peringkat.*.senarai_dokumen_wajib' => ['nullable', 'array'], 'peringkat.*.senarai_dokumen_wajib.*' => [Rule::in(array_keys(Lalai::KATEGORI_DOKUMEN))],
            'peringkat.*.senarai_kategori_kos' => ['nullable', 'array'], 'peringkat.*.senarai_kategori_kos.*' => [Rule::in(array_keys(Lalai::KATEGORI_KOS))],
            'peringkat.*.perlu_kelulusan' => ['nullable', 'boolean'], 'peringkat.*.kesan' => ['nullable', Rule::in(self::KESAN)],
        ]);
        $t = $svc->newVersion($jenis, $data, $this->user($request));

        return response()->json(['message' => __('tsu::tsu.templat.versi_baharu', ['versi' => $t->versi]), 'data' => $this->out($t)], 201);
    }

    /** @return array<string, mixed> */
    private function out(TemplatProses $t): array
    {
        return [
            'id' => $t->id, 'jenis_urusan' => $t->jenis_urusan, 'jenis_nama' => Lalai::JENIS_URUSAN[$t->jenis_urusan] ?? $t->jenis_urusan, 'versi' => $t->versi,
            'nama' => $t->nama, 'keterangan' => $t->keterangan, 'aliran_kelulusan' => $t->aliran_kelulusan, 'status_aktif' => $t->status_aktif,
            'jumlah_hari' => (int) $t->peringkat->sum('tempoh_sasaran_hari'),
            'peringkat' => $t->peringkat->map(fn (TemplatPeringkat $p) => [
                'no_urutan' => $p->no_urutan, 'kod' => $p->kod, 'nama_peringkat' => $p->nama_peringkat, 'tindakan_diperlukan' => $p->tindakan_diperlukan,
                'pihak_bertanggungjawab' => $p->pihak_bertanggungjawab, 'tempoh_sasaran_hari' => $p->tempoh_sasaran_hari, 'senarai_dokumen_wajib' => $p->senarai_dokumen_wajib,
                'senarai_kategori_kos' => $p->senarai_kategori_kos, 'perlu_kelulusan' => $p->perlu_kelulusan, 'kesan' => $p->kesan,
            ])->values()->all(),
        ];
    }
}
