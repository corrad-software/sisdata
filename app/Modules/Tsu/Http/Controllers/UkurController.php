<?php

namespace App\Modules\Tsu\Http\Controllers;

use App\Modules\Tsu\Http\Present;
use App\Modules\Tsu\Models\HasilUkur;
use App\Modules\Tsu\Models\LantikanJurukur;
use App\Modules\Tsu\Models\PanelJurukur;
use App\Modules\Tsu\Models\SebutHargaTawaran;
use App\Modules\Tsu\Models\SebutHargaUkur;
use App\Modules\Tsu\Services\HasilUkurService;
use App\Modules\Tsu\Services\SebutHargaService;
use App\Modules\Tsu\Services\UkurService;
use App\Modules\Tsu\Support\FailUkur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** SRS-TSU-08..13, 16..18: surveyor panel, quotations, appointment, progress, survey results. */
class UkurController extends TsuController
{
    public function __construct(private Present $present) {}

    // ---- Panel ------------------------------------------------------------------------

    public function panel(Request $request): JsonResponse
    {
        $q = PanelJurukur::query()->when($request->boolean('layak'), fn ($q) => $q->where('status_panel', 'aktif')->whereDate('tarikh_tamat_lesen', '>=', today()))->orderBy('nama_firma');

        return response()->json(['data' => $q->get()->map(fn ($p) => $this->present->panel($p))->values()]);
    }

    public function simpanPanel(Request $request, ?int $panel = null): JsonResponse
    {
        $p = $panel ? PanelJurukur::query()->findOrFail($panel) : new PanelJurukur;
        $data = $request->validate([
            'nama_firma' => [$panel ? 'sometimes' : 'required', 'string', 'max:200'],
            'no_lesen_jurukur' => [$panel ? 'sometimes' : 'required', 'string', 'max:40', Rule::unique('panel_jurukur', 'no_lesen_jurukur')->ignore($p->id)],
            'nama_jurukur_bertanggungjawab' => [$panel ? 'sometimes' : 'required', 'string', 'max:150'], 'maklumat_perhubungan' => ['sometimes', 'array'],
            'maklumat_perhubungan.emel' => ['nullable', 'email'], 'maklumat_perhubungan.telefon' => ['nullable', 'string', 'max:30'], 'maklumat_perhubungan.alamat' => ['nullable', 'string', 'max:500'],
            'pengalaman_tahun' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'], 'status_panel' => ['sometimes', Rule::in(['aktif', 'digantung', 'tamat'])],
            'tarikh_tamat_lesen' => [$panel ? 'sometimes' : 'required', 'date_format:Y-m-d'], 'id_pengguna' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);
        $p->forceFill($data)->save();

        return response()->json(['message' => __('tsu::tsu.disimpan'), 'data' => $this->present->panel($p->refresh())], $panel ? 200 : 201);
    }

    // ---- Survey overview of one urusan -------------------------------------------------

    public function ukur(string $id): JsonResponse
    {
        $u = $this->urusan($id);

        return response()->json(['data' => [
            'sebutharga' => SebutHargaUkur::query()->with('tawaran.jurukur')->where('urusan_id', $u->id)->orderByDesc('created_at')->get()->map(fn ($s) => $this->present->sebutharga($s))->values(),
            'lantikan' => LantikanJurukur::query()->with(['jurukur', 'kemajuan'])->where('urusan_id', $u->id)->orderByDesc('created_at')->get()->map(fn ($l) => $this->present->lantikan($l))->values(),
            'hasil' => HasilUkur::query()->where('urusan_id', $u->id)->orderByDesc('created_at')->get()->map(fn ($h) => $this->present->hasil($h))->values(),
        ]]);
    }

    // ---- Quotations ---------------------------------------------------------------------

    public function jemput(Request $request, string $id, SebutHargaService $svc): JsonResponse
    {
        $data = $request->validate([
            'tarikh_tutup' => ['required', 'date', 'after:now'], 'skop_kerja' => ['required', 'string', 'max:5000'],
            'jurukur_ids' => ['required', 'array', 'min:1', 'max:30'], 'jurukur_ids.*' => ['integer'],
        ]);
        $sh = $svc->create($this->urusan($id), $data, $this->user($request));

        return response()->json(['message' => __('tsu::tsu.sebutharga.dijemput', ['no' => $sh->no_jemputan]), 'data' => $this->present->sebutharga($sh->load('tawaran.jurukur'))], 201);
    }

    private function sh(string $id): SebutHargaUkur
    {
        $sh = SebutHargaUkur::query()->with('tawaran.jurukur')->findOrFail($id);
        $this->urusan($sh->urusan_id); // scope

        return $sh;
    }

    public function rekodTawaran(Request $request, string $sebutharga, int $tawaran, SebutHargaService $svc): JsonResponse
    {
        $sh = $this->sh($sebutharga);
        $t = SebutHargaTawaran::query()->where('sebutharga_id', $sh->id)->findOrFail($tawaran);
        $data = $request->validate(['harga_tawaran' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'], 'tempoh_siap_hari' => ['required', 'integer', 'min:1', 'max:2000'], 'pengalaman_tahun' => ['nullable', 'integer', 'min:0', 'max:80']]);
        $svc->recordOffer($t, $data, $this->user($request));

        return response()->json(['message' => __('tsu::tsu.sebutharga.tawaran_direkod')]);
    }

    public function perbandingan(Request $request, string $sebutharga, SebutHargaService $svc): JsonResponse
    {
        $sh = $this->sh($sebutharga);

        return response()->json(['data' => ['sebutharga' => $this->present->sebutharga($sh), 'jadual' => $svc->comparison($sh, $this->user($request))]]);
    }

    public function nilai(Request $request, string $sebutharga, SebutHargaService $svc): JsonResponse
    {
        $data = $request->validate(['id_tawaran_disyorkan' => ['required', 'integer'], 'justifikasi_pemilihan' => ['required', 'string', 'min:10', 'max:5000'], 'catatan' => ['nullable', 'array'], 'catatan.*' => ['nullable', 'string', 'max:1000']]);
        $sh = $svc->evaluate($this->sh($sebutharga), $data, $this->user($request));

        return response()->json(['message' => __('tsu::tsu.sebutharga.dihantar_kelulusan'), 'data' => $this->present->sebutharga($sh->load('tawaran.jurukur'))]);
    }

    public function lantik(Request $request, string $sebutharga, SebutHargaService $svc): JsonResponse
    {
        $data = $request->validate([
            'no_surat_setuju_terima' => ['required', 'string', 'max:60'], 'tarikh_surat_setuju_terima' => ['nullable', 'date_format:Y-m-d'],
            'nilai_kontrak' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'], 'tarikh_mula' => ['required', 'date_format:Y-m-d'],
            'tempoh_siap_hari' => ['nullable', 'integer', 'min:1', 'max:2000'], 'skop_kerja' => ['nullable', 'string', 'max:5000'],
        ]);
        $l = $svc->appoint($this->sh($sebutharga), $data, $this->user($request));

        return response()->json(['message' => __('tsu::tsu.sebutharga.dilantik'), 'data' => $this->present->lantikan($l->load('jurukur'))], 201);
    }

    public function jemputanPdf(string $sebutharga, SebutHargaService $svc): Response
    {
        $sh = $this->sh($sebutharga)->load('urusan');

        return response($svc->pdf($sh), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.str_replace('/', '-', $sh->no_jemputan).'.pdf"']);
    }

    // ---- Progress -------------------------------------------------------------------------

    public function lantikanSaya(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $rows = LantikanJurukur::query()->with(['jurukur', 'kemajuan', 'urusan'])->whereHas('jurukur', fn ($q) => $q->where('id_pengguna', $user->id))->orderByDesc('created_at')->get();

        return response()->json(['data' => $rows->map(fn ($l) => $this->present->lantikan($l) + ['no_rujukan_urusan' => $l->urusan->no_rujukan_mais, 'jenis_urusan' => $l->urusan->jenisLabel()])->values()]);
    }

    public function kemajuan(Request $request, string $lantikan, UkurService $svc): JsonResponse
    {
        $l = LantikanJurukur::query()->with('jurukur', 'urusan')->findOrFail($lantikan);
        $user = $this->user($request);
        if (! $user->can('tsu.jurukur.kemas_kini') || $l->jurukur->id_pengguna !== $user->id) {
            $this->urusan($l->urusan_id); // internal officers: normal scope
        }
        $data = $request->validate(['milestone' => ['required', 'string', 'max:150'], 'peratus_siap' => ['required', 'integer', 'min:0', 'max:100'], 'tarikh_lapor' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'catatan' => ['nullable', 'string', 'max:2000']]);
        $k = $svc->report($l, $data, $user);

        return response()->json(['message' => $k->lewat ? __('tsu::tsu.ukur.kemajuan_lewat') : __('tsu::tsu.ukur.kemajuan_direkod'), 'data' => ['lewat' => $k->lewat, 'peratus_dijangka' => $k->peratus_dijangka]], 201);
    }

    // ---- Survey results ---------------------------------------------------------------------

    public function importHasil(Request $request, string $id, HasilUkurService $svc): JsonResponse
    {
        $data = $request->validate([
            'format_fail' => ['required', Rule::in(['dxf', 'shp', 'geojson'])], 'sistem_koordinat' => ['required', 'integer', Rule::in(FailUkur::SRID)],
            'fail' => ['required_without:geojson', 'file', 'max:51200'], 'geojson' => ['required_without:fail', 'string'],
            'keluasan_dokumen' => ['nullable', 'numeric', 'gt:0'], 'lot_id' => ['nullable', 'uuid'], 'no_lot_cadangan' => ['nullable', 'string', 'max:50'], 'lantikan_id' => ['nullable', 'uuid'],
        ]);
        $rows = $svc->import($this->urusan($id), $data, $request->file('fail'), $this->user($request));

        return response()->json(['message' => __('tsu::tsu.ukur.diimport', ['n' => count($rows)]), 'data' => array_map(fn ($h) => $this->present->hasil($h), $rows)], 201);
    }

    private function hasil(string $id): HasilUkur
    {
        $h = HasilUkur::query()->findOrFail($id);
        $this->urusan($h->urusan_id);

        return $h;
    }

    public function banding(string $hasil, HasilUkurService $svc): JsonResponse
    {
        $h = $this->hasil($hasil);

        return response()->json(['data' => ['hasil' => $this->present->hasil($h), ...$svc->compare($h)]]);
    }

    public function sahkan(Request $request, string $hasil, HasilUkurService $svc): JsonResponse
    {
        $data = $request->validate(['status_pengesahan' => ['required', Rule::in(['diterima', 'ditolak'])], 'catatan' => ['nullable', 'string', 'max:2000'], 'id_dokumen_pelan_akui' => ['nullable', 'string', 'max:64']]);
        $h = $svc->verify($this->hasil($hasil), $data['status_pengesahan'], $this->user($request), $data['catatan'] ?? null, $data['id_dokumen_pelan_akui'] ?? null);

        return response()->json(['message' => __('tsu::tsu.ukur.disahkan_'.$h->status_pengesahan), 'data' => $this->present->hasil($h)]);
    }
}
