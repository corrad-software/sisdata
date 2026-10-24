<?php

namespace App\Modules\Ast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ast\Http\Present;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Models\AsetPemeriksaan;
use App\Modules\Ast\Services\PemeriksaanService;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ast\Support\Rujukan;
use App\Modules\Dms\Contracts\DocumentRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Inspections (SRS-AST-20/21): online from the asset profile / after a QR
 * scan, and the field-app (PWA) API — download the in-scope asset list for
 * offline use, then sync inspections and photos idempotently.
 */
class PemeriksaanController extends Controller
{
    public function __construct(private PemeriksaanService $service, private Present $present) {}

    public function index(string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);

        return response()->json(['data' => AsetPemeriksaan::query()->with('pegawai')->where('id_aset', $a->id)->orderByDesc('tarikh_pemeriksaan')->orderByDesc('created_at')->limit(100)->get()
            ->map(fn (AsetPemeriksaan $p) => $this->present->pemeriksaan($p) + ['di_lokasi' => $this->service->diLokasi($p->jarak_dari_aset_m !== null ? (float) $p->jarak_dari_aset_m : null)])->values()]);
    }

    public function store(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $r = $this->service->rekod($a, $this->rules($request), $request->user(), $request->input('kaedah') === 'qr' ? 'qr' : 'manual');

        return response()->json(['data' => $this->present->pemeriksaan($r['pemeriksaan']), 'status_segerak' => $r['status'], 'message' => __('ast::ast.pemeriksaan_direkod')], $r['status'] === 'pendua' ? 200 : 201);
    }

    /** PWA bundle: in-scope active assets (optionally one district / lot) + checklist + labels, for offline use. */
    public function bundle(Request $request, Parameters $parameters, Rujukan $ref): JsonResponse
    {
        $q = AsetFizikal::query()->where('status_arkib', false)
            ->when($request->query('kod_daerah'), fn ($w, $v) => $w->where('kod_daerah', $v))
            ->when($request->query('lot_id'), fn ($w, $v) => $w->where('lot_id', $v))
            ->orderBy('kod_daerah')->orderBy('nama_aset')->limit(3000);
        $rows = $q->get();
        $lots = DB::table('lot_tanah')->whereIn('id', $rows->pluck('lot_id')->unique()->all())->pluck('no_lot', 'id');
        $kat = AsetKategori::query()->get()->keyBy('id');

        return response()->json(['data' => [
            'dijana_pada' => now()->toIso8601String(),
            'aset' => $rows->map(fn (AsetFizikal $a) => [
                'id' => $a->id, 'qr_ref' => $a->qr_ref, 'no_rujukan_aset' => $a->no_rujukan_aset, 'nama_aset' => $a->nama_aset, 'kategori' => $kat[$a->id_kategori]->nama_kategori ?? '',
                'no_lot' => $lots[$a->lot_id] ?? '', 'daerah' => $ref->namaDaerah($a->kod_daerah), 'mukim' => $ref->namaMukim($a->kod_mukim),
                'status_kondisi' => $a->status_kondisi, 'latitud' => $a->latitud !== null ? (float) $a->latitud : null, 'longitud' => $a->longitud !== null ? (float) $a->longitud : null,
                'tarikh_pemeriksaan_terakhir' => $a->tarikh_pemeriksaan_terakhir?->toDateString(),
                'lewat' => $a->tarikh_pemeriksaan_terakhir === null || $a->tarikh_pemeriksaan_terakhir->lt(now()->subMonthsNoOverflow($kat[$a->id_kategori]->tempoh_pemeriksaan_bulan ?? 12)),
            ])->values(),
            'senarai_semak' => Rujukan::senarai(Lalai::SENARAI_SEMAK), 'kondisi' => Rujukan::senarai(Lalai::KONDISI),
            'jenis_penyelenggaraan' => Rujukan::senarai(Lalai::JENIS_PENYELENGGARAAN),
            'toleransi_lokasi_m' => (int) $parameters->get('ast.toleransi_lokasi_m', 150),
            'boleh_cipta_kerja' => $request->user()->can('ast.penyelenggaraan.cipta'),
        ]]);
    }

    public function segerak(Request $request): JsonResponse
    {
        $d = $this->rules($request, true);
        $a = AsetFizikal::query()->find($d['id_aset']) ?? throw ValidationException::withMessages(['id_aset' => __('ast::ast.aset_tidak_wujud')]);
        $r = $this->service->rekod($a, $d, $request->user(), 'pwa');

        return response()->json(['data' => $this->present->pemeriksaan($r['pemeriksaan']), 'status_segerak' => $r['status']], $r['status'] === 'pendua' ? 200 : 201);
    }

    public function gambar(Request $request, string $pemeriksaan): JsonResponse
    {
        $p = AsetPemeriksaan::query()->findOrFail($pemeriksaan);
        if ($p->id_pegawai !== $request->user()->id && ! $request->user()->can('ast.pemeriksaan.cipta')) {
            abort(403);
        }
        $a = AsetFizikal::query()->findOrFail($p->id_aset);
        $meta = $request->validate([
            'fail' => ['required', 'file', 'image', 'max:20480'], 'id_luar_talian' => ['required', 'uuid'], 'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'], 'ketepatan_m' => ['nullable', 'numeric', 'min:0'], 'tarikh_gambar' => ['nullable', 'date'], 'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $r = $this->service->tambahGambar($a, $request->file('fail'), $meta, $request->user(), $p);
        } catch (DocumentRejected $e) {
            throw ValidationException::withMessages(['fail' => $e->getMessage()]);
        }

        return response()->json(['data' => ['id' => $r['gambar']->id], 'status_segerak' => $r['status']], $r['status'] === 'pendua' ? 200 : 201);
    }

    public function saya(Request $request): JsonResponse
    {
        return response()->json(['data' => AsetPemeriksaan::query()->with(['aset' => fn ($q) => $q->withoutGlobalScope('access')])->where('id_pegawai', $request->user()->id)
            ->orderByDesc('created_at')->limit(50)->get()->map(fn (AsetPemeriksaan $p) => $this->present->pemeriksaan($p) + ['nama_aset' => $p->aset?->nama_aset, 'no_rujukan_aset' => $p->aset?->no_rujukan_aset])->values()]);
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, bool $pwa = false): array
    {
        return $request->validate([
            'id_aset' => [$pwa ? 'required' : 'prohibited', 'uuid'],
            'id_luar_talian' => [$pwa ? 'required' : 'nullable', 'uuid'],
            'tarikh_pemeriksaan' => ['required', 'date', 'before_or_equal:today'],
            'status_kondisi_dilapor' => ['required', Rule::in(array_keys(Lalai::KONDISI))],
            'isu_dikenalpasti' => ['nullable', 'string', 'max:5000'],
            'tindakan_disyorkan' => ['nullable', 'string', 'max:5000'],
            'senarai_semak' => ['nullable', 'array', 'max:50'],
            'senarai_semak.*.kod' => ['required', 'string', 'max:40'],
            'senarai_semak.*.status' => ['required', Rule::in(['baik', 'isu', 'kritikal', 'tiada'])],
            'senarai_semak.*.catatan' => ['nullable', 'string', 'max:1000'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'ketepatan_gps_m' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'masa_mula' => ['nullable', 'date'],
            'masa_tamat' => ['nullable', 'date'],
            'cipta_kerja' => ['nullable', 'boolean'],
            'jenis_penyelenggaraan' => ['nullable', Rule::in(array_keys(Lalai::JENIS_PENYELENGGARAAN))],
        ]);
    }
}
