<?php

namespace App\Modules\Ast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ast\Http\Present;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetGambar;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Services\PemeriksaanService;
use App\Modules\Ast\Services\PenyelenggaraanService;
use App\Modules\Ast\Support\Akses;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Dms\Contracts\DocumentRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Maintenance (SRS-AST-14..17): schedules, the unit's calendar, work orders
 * (contractors see only their own — Support\Akses) and MOD-PTN needs.
 */
class PenyelenggaraanController extends Controller
{
    public function __construct(private PenyelenggaraanService $service, private Present $present, private Akses $akses) {}

    public function untukAset(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);

        return response()->json(['data' => [
            'jadual' => JadualPenyelenggaraan::query()->with('pegawai')->where('id_aset', $a->id)->orderBy('tarikh_jatuh_tempoh')->get()->map(fn ($j) => $this->present->jadual($j))->values(),
            'kerja' => RekodPenyelenggaraan::query()->with('pegawai')->where('id_aset', $a->id)->orderByDesc('created_at')->limit(200)->get()->map(fn ($k) => $this->present->kerja($k))->values(),
        ]]);
    }

    public function jadual(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $q = JadualPenyelenggaraan::query()->with(['pegawai', 'aset'])->whereHas('aset', fn ($w) => $w->where('status_arkib', false))
            ->when($request->query('kod_daerah'), fn ($w, $v) => $w->where('kod_daerah', $v))
            ->when($request->query('jenis'), fn ($w, $v) => $w->where('jenis_penyelenggaraan', $v));
        match ($request->query('status')) {
            'tertunggak' => $q->where('status', 'aktif')->whereDate('tarikh_jatuh_tempoh', '<', $today),
            'hampir' => $q->where('status', 'aktif')->whereDate('tarikh_jatuh_tempoh', '>=', $today)->whereRaw('tarikh_jatuh_tempoh <= (?::date + tempoh_amaran_hari)', [$today]),
            'tidak_aktif' => $q->where('status', 'tidak_aktif'),
            default => $q->where('status', 'aktif'),
        };
        $p = $q->orderBy('tarikh_jatuh_tempoh')->paginate(min(100, max(5, (int) $request->query('per_page', 25))));

        return response()->json(['data' => collect($p->items())->map(fn ($j) => $this->present->jadual($j))->values(), 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }

    public function simpanJadual(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        if ($a->status_arkib) {
            abort(422, __('ast::ast.aset_diarkib'));
        }
        $d = $request->validate([
            'jenis_penyelenggaraan' => ['required', Rule::in(array_keys(Lalai::JENIS_PENYELENGGARAAN)), Rule::unique('jadual_penyelenggaraan')->where('id_aset', $a->id)],
            'kitaran_bulan' => ['required', 'integer', 'between:1,240'], 'tarikh_jatuh_tempoh' => ['required', 'date'],
            'tempoh_amaran_hari' => ['nullable', 'integer', 'between:0,365'], 'id_pegawai_bertanggungjawab' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $j = JadualPenyelenggaraan::query()->create($d + [
            'id_aset' => $a->id, 'tempoh_amaran_hari' => $d['tempoh_amaran_hari'] ?? 14, 'id_pegawai_bertanggungjawab' => $d['id_pegawai_bertanggungjawab'] ?? $a->id_pegawai_bertanggungjawab,
            'status' => 'aktif', 'dijana_automatik' => false, 'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
        ]);

        return response()->json(['data' => $this->present->jadual($j->load('pegawai')), 'message' => __('ast::ast.jadual_disimpan')], 201);
    }

    public function kemaskiniJadual(Request $request, int $jadual): JsonResponse
    {
        $j = JadualPenyelenggaraan::query()->findOrFail($jadual);
        $d = $request->validate([
            'kitaran_bulan' => ['sometimes', 'integer', 'between:1,240'], 'tarikh_jatuh_tempoh' => ['sometimes', 'date'], 'tempoh_amaran_hari' => ['sometimes', 'integer', 'between:0,365'],
            'status' => ['sometimes', Rule::in(['aktif', 'tidak_aktif'])], 'id_pegawai_bertanggungjawab' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);
        $j->fill($d)->save();

        return response()->json(['data' => $this->present->jadual($j->load('pegawai')), 'message' => __('ast::ast.jadual_disimpan')]);
    }

    /**
     * SRS-AST-15 unit calendar: every occurrence of each active schedule in the
     * month (projected from the next due date by its cycle; overdue ones shown
     * on their due date) plus work orders planned in the month.
     */
    public function kalendar(Request $request): JsonResponse
    {
        $this->bolehLihatKerja($request);
        $bulan = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan')) ? (string) $request->query('bulan') : now()->format('Y-m');
        $mula = Carbon::parse($bulan.'-01')->startOfMonth();
        $akhir = $mula->copy()->endOfMonth();
        $today = now()->startOfDay();
        $events = [];
        JadualPenyelenggaraan::query()->with('aset')->where('status', 'aktif')->whereHas('aset', fn ($w) => $w->where('status_arkib', false))
            ->when($request->query('kod_daerah'), fn ($w, $v) => $w->where('kod_daerah', $v))
            ->whereDate('tarikh_jatuh_tempoh', '<=', $akhir->toDateString())->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$events, $mula, $akhir, $today) {
                foreach ($rows as $j) {
                    $d = $j->tarikh_jatuh_tempoh->copy();
                    for ($i = 0; $d->lte($akhir) && $i < 240; $i++) {
                        if ($d->gte($mula)) {
                            $events[] = [
                                'tarikh' => $d->toDateString(), 'jenis' => 'jadual', 'id' => 'j'.$j->id.'-'.$i, 'tajuk' => (Lalai::JENIS_PENYELENGGARAAN[$j->jenis_penyelenggaraan] ?? $j->jenis_penyelenggaraan).' — '.($j->aset->nama_aset ?? ''),
                                'status' => $i === 0 && $d->lt($today) ? 'tertunggak' : ($i === 0 ? 'jatuh_tempoh' : 'unjuran'), 'pautan' => '/admin/ast/aset/'.$j->id_aset,
                            ];
                        }
                        $d = $d->copy()->addMonthsNoOverflow(max(1, $j->kitaran_bulan));
                    }
                }
            });
        $kerja = $this->akses->hadKerja(RekodPenyelenggaraan::query()->with('aset'), $request->user())
            ->whereBetween('tarikh_dijadual', [$mula->toDateString(), $akhir->toDateString()])->whereNotIn('status', ['dibatalkan', 'ditolak'])->limit(2000)->get();
        foreach ($kerja as $k) {
            $events[] = ['tarikh' => $k->tarikh_dijadual?->toDateString(), 'jenis' => 'kerja', 'id' => $k->id, 'tajuk' => $k->no_rujukan.' — '.($k->aset->nama_aset ?? ''), 'status' => $k->status, 'pautan' => '/admin/ast/kerja/'.$k->id];
        }
        usort($events, fn ($x, $y) => strcmp((string) $x['tarikh'], (string) $y['tarikh']));

        return response()->json(['data' => ['bulan' => $bulan, 'acara' => $events]]);
    }

    public function kerja(Request $request): JsonResponse
    {
        $this->bolehLihatKerja($request);
        $q = $this->akses->hadKerja(RekodPenyelenggaraan::query()->with(['pegawai', 'aset']), $request->user())
            ->when($request->query('kod_daerah'), fn ($w, $v) => $w->where('kod_daerah', $v))
            ->when($request->query('sumber'), fn ($w, $v) => $w->where('sumber', $v))
            ->when($request->query('id_kontraktor'), fn ($w, $v) => $w->where('id_kontraktor', (int) $v))
            ->when($request->query('q'), fn ($w, $v) => $w->where(fn ($x) => $x->where('no_rujukan', 'ilike', '%'.$v.'%')->orWhere('keterangan', 'ilike', '%'.$v.'%')));
        $status = (string) $request->query('status', '');
        if ($status === 'terbuka') {
            $q->whereIn('status', Lalai::KERJA_TERBUKA);
        } elseif ($status === 'tertunggak') {
            $q->whereIn('status', Lalai::KERJA_TERBUKA)->whereDate('tarikh_dijadual', '<', now()->toDateString());
        } elseif ($status !== '') {
            $q->where('status', $status);
        }
        $p = $q->orderByDesc('created_at')->paginate(min(100, max(5, (int) $request->query('per_page', 25))));
        $kos = $request->user()->can('ast.penyelenggaraan.lihat');

        return response()->json(['data' => collect($p->items())->map(fn ($k) => $this->present->kerja($k, $kos))->values(), 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }

    public function showKerja(Request $request, string $kerja): JsonResponse
    {
        $this->bolehLihatKerja($request);
        $k = $this->akses->hadKerja(RekodPenyelenggaraan::query()->with(['pegawai', 'aset', 'kontraktor', 'jadual']), $request->user())->findOrFail($kerja);
        $gambar = AsetGambar::query()->withoutGlobalScope('access')->where('id_rekod_penyelenggaraan', $k->id)->orderBy('tarikh_gambar')->get()
            ->map(fn (AsetGambar $g) => ['id' => $g->id, 'kategori_gambar' => $g->kategori_gambar, 'kategori_nama' => Lalai::KATEGORI_GAMBAR[$g->kategori_gambar] ?? $g->kategori_gambar, 'tarikh_gambar' => $g->tarikh_gambar->toIso8601String(), 'keterangan' => $g->keterangan]);

        return response()->json(['data' => $this->present->kerja($k) + [
            'kontraktor' => $k->kontraktor ? $this->present->kontraktor($k->kontraktor) : null, 'gambar' => $gambar->values(),
            'jadual' => $k->jadual ? $this->present->jadual($k->jadual) : null,
            'boleh_urus' => $this->akses->bolehUrusKerja($request->user(), $k), 'kontraktor_sahaja' => $this->akses->kontraktorSahaja($request->user()),
        ]]);
    }

    public function simpanKerja(Request $request): JsonResponse
    {
        $d = $request->validate([
            'id_aset' => ['required', 'uuid'], 'jenis_kerja' => ['required', Rule::in(array_keys(Lalai::JENIS_KERJA))],
            'jenis_penyelenggaraan' => ['required', Rule::in(array_keys(Lalai::JENIS_PENYELENGGARAAN))], 'keterangan' => ['required', 'string', 'max:5000'],
            'keutamaan' => ['nullable', Rule::in(array_keys(Lalai::KEUTAMAAN))], 'tarikh_dijadual' => ['nullable', 'date'],
            'kos_anggaran' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'id_kontraktor' => ['nullable', 'integer'],
            'nama_syarikat_pelaksana' => ['nullable', 'string', 'max:200'], 'id_pegawai' => ['nullable', 'integer', 'exists:users,id'], 'id_jadual' => ['nullable', 'integer'],
        ]);
        $a = AsetFizikal::query()->find($d['id_aset']) ?? throw ValidationException::withMessages(['id_aset' => __('ast::ast.aset_tidak_wujud')]);
        if (! empty($d['id_jadual']) && ! JadualPenyelenggaraan::query()->where('id_aset', $a->id)->whereKey((int) $d['id_jadual'])->exists()) {
            throw ValidationException::withMessages(['id_jadual' => __('ast::ast.jadual_tidak_sah')]);
        }
        $k = $this->service->ciptaKerja($a, $d, $request->user(), ! empty($d['id_jadual']) ? 'jadual' : 'manual');

        return response()->json(['data' => $this->present->kerja($k->load('pegawai')), 'message' => __('ast::ast.kerja_dicipta', ['no' => $k->no_rujukan])], 201);
    }

    public function kemaskiniKerja(Request $request, string $kerja): JsonResponse
    {
        $user = $request->user();
        $k = $this->akses->hadKerja(RekodPenyelenggaraan::query(), $user)->findOrFail($kerja);
        if (! $this->akses->bolehUrusKerja($user, $k)) {
            abort(403);
        }
        $kontraktor = ! $user->can('ast.penyelenggaraan.kemas_kini');
        // A contractor only reports progress: status, dates, actual cost, notes.
        $rules = [
            'status' => ['sometimes', Rule::in($kontraktor ? ['dalam_pelaksanaan', 'selesai'] : array_keys(Lalai::STATUS_KERJA))],
            'tarikh_mula' => ['sometimes', 'nullable', 'date'], 'tarikh_siap' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'kos' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'], 'catatan_penyelesaian' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
        if (! $kontraktor) {
            $rules += [
                'keterangan' => ['sometimes', 'string', 'max:5000'], 'keutamaan' => ['sometimes', Rule::in(array_keys(Lalai::KEUTAMAAN))],
                'tarikh_dijadual' => ['sometimes', 'nullable', 'date'], 'kos_anggaran' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
                'id_kontraktor' => ['sometimes', 'nullable', 'integer'], 'nama_syarikat_pelaksana' => ['sometimes', 'nullable', 'string', 'max:200'],
                'jenis_penyelenggaraan' => ['sometimes', Rule::in(array_keys(Lalai::JENIS_PENYELENGGARAAN))],
            ];
        }
        $d = $request->validate($rules);
        if ($kontraktor && array_diff(array_keys($request->except(['_method'])), array_keys($rules)) !== []) {
            abort(403, __('ast::ast.kontraktor_terhad'));
        }
        $k = $this->service->kemaskiniKerja($k, $d, $user);

        return response()->json(['data' => $this->present->kerja($k->load('pegawai'), ! $kontraktor || $user->can('ast.penyelenggaraan.lihat')), 'message' => __('ast::ast.kerja_dikemas_kini')]);
    }

    public function gambarKerja(Request $request, string $kerja, PemeriksaanService $media): JsonResponse
    {
        $user = $request->user();
        $k = $this->akses->hadKerja(RekodPenyelenggaraan::query(), $user)->findOrFail($kerja);
        if (! $this->akses->bolehUrusKerja($user, $k)) {
            abort(403);
        }
        $meta = $request->validate(['fail' => ['required', 'file', 'image', 'max:20480'], 'kategori_gambar' => ['required', Rule::in(['sebelum', 'selepas'])], 'keterangan' => ['nullable', 'string', 'max:255'], 'latitud' => ['nullable', 'numeric'], 'longitud' => ['nullable', 'numeric'], 'id_luar_talian' => ['nullable', 'uuid']]);
        $a = AsetFizikal::query()->withoutGlobalScope('access')->findOrFail($k->id_aset);
        try {
            $r = $media->tambahGambar($a, $request->file('fail'), $meta, $user, null, $k);
        } catch (DocumentRejected $e) {
            throw ValidationException::withMessages(['fail' => $e->getMessage()]);
        }

        return response()->json(['data' => ['id' => $r['gambar']->id], 'message' => __('ast::ast.gambar_disimpan')], 201);
    }

    public function keperluanPtn(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->senaraiKeperluanPtn($request->user(), $request->query('lot_id') ? (string) $request->query('lot_id') : null)]);
    }

    public function tarikPtn(Request $request): JsonResponse
    {
        $lots = AsetFizikal::query()->where('status_arkib', false)->distinct()->pluck('lot_id')->all();
        $r = $this->service->tarikKeperluanPtn($request->user(), $lots);

        return response()->json(['data' => $r, 'message' => __('ast::ast.ptn_ditarik', ['n' => $r['dicipta'], 'p' => $r['perlu_dipadankan']])]);
    }

    public function pautPtn(Request $request, int $keperluan): JsonResponse
    {
        $d = $request->validate(['id_aset' => ['required', 'uuid']]);
        $a = AsetFizikal::query()->find($d['id_aset']) ?? throw ValidationException::withMessages(['id_aset' => __('ast::ast.aset_tidak_wujud')]);
        $k = $this->service->pautKeperluan($keperluan, $a, $request->user());

        return response()->json(['data' => $this->present->kerja($k), 'message' => __('ast::ast.kerja_dicipta', ['no' => $k->no_rujukan])], 201);
    }

    private function bolehLihatKerja(Request $request): void
    {
        if (! $request->user()->can('ast.penyelenggaraan.lihat') && ! $request->user()->can('ast.kerja.kemas_kini')) {
            abort(403);
        }
    }
}
