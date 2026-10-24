<?php

namespace App\Modules\Ptn\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Ptn\Http\Present;
use App\Modules\Ptn\Models\GambarLawatan;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\TemplatSenaraiSemak;
use App\Modules\Ptn\Services\LawatanService;
use App\Modules\Ptn\Services\Penjadualan;
use App\Modules\Ptn\Support\Akses;
use App\Modules\Ptn\Support\Geometri;
use App\Modules\Ptn\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SRS-PTN-05 field app API (/admin/ptn/lapangan PWA): download the officer's
 * assigned visits as one offline bundle (lot, polygon, checklist template,
 * previous visit), then sync visits and photos back idempotently.
 */
class LapanganController extends Controller
{
    public function __construct(private Present $present, private Akses $akses, private LotRegistry $lots) {}

    public function tugasan(Request $request, Geometri $geo, Penjadualan $penjadualan): JsonResponse
    {
        $user = $request->user();
        $hari = (int) $request->integer('hari', 60);
        $rows = JadualPemantauan::query()->with('templat')->where('id_pegawai_bertugas', $user->id)->whereIn('status_pelaksanaan', ['ditugaskan', 'dalam_pelaksanaan'])
            ->where('tarikh_sasaran', '<=', now()->addDays(max(1, min(366, $hari)))->toDateString())->orderBy('tarikh_sasaran')->limit(200)->get();
        $lotIds = $rows->pluck('lot_id')->unique()->values()->all();
        $lots = $this->lots->summaries($lotIds, $user);
        $geoms = $geo->geojsonBanyak($lotIds);
        $prev = [];
        foreach (RekodLawatan::query()->whereIn('lot_id', $lotIds)->whereIn('status_lawatan', ['dihantar', 'disahkan'])->orderByDesc('tarikh_lawatan')->get(['id', 'lot_id', 'tarikh_lawatan', 'keadaan_tapak', 'ringkasan_penemuan']) as $v) {
            $prev[$v->lot_id] ??= ['id' => $v->id, 'tarikh_lawatan' => $v->tarikh_lawatan->toDateString(), 'keadaan_tapak' => $v->keadaan_tapak, 'ringkasan_penemuan' => $v->ringkasan_penemuan,
                'item' => $v->item()->get(['kod_item', 'status_item', 'catatan'])->map(fn ($i) => ['kod_item' => $i->kod_item, 'status_item' => $i->status_item, 'catatan' => $i->catatan])->all()];
        }
        $templates = [];
        $out = [];
        foreach ($rows as $j) {
            if (! isset($lots[$j->lot_id])) {
                continue;
            }
            $t = $j->templat ?? $penjadualan->templatUntuk($lots[$j->lot_id]['kategori_guna_tanah'], $j->jenis_pemeriksaan);
            if ($t !== null) {
                $templates[$t->id] = ['id' => $t->id, 'kod' => $t->kod, 'nama' => $t->nama, 'versi' => $t->versi, 'senarai_item' => $t->senarai_item];
            }
            $out[] = $this->present->jadual($j, $lots) + [
                'id_templat' => $t?->id, 'lot' => $lots[$j->lot_id], 'geometri' => $geoms[$j->lot_id] ?? null, 'pusat' => $geo->pusat($j->lot_id),
                'lawatan_terdahulu' => $prev[$j->lot_id] ?? null,
            ];
        }

        return response()->json(['data' => [
            'dijana_pada' => now()->toIso8601String(), 'pengguna' => ['id' => $user->id, 'nama' => $user->name],
            'tugasan' => $out, 'templat' => array_values($templates),
            'toleransi_lingkungan_m' => (float) app(Parameters::class)->get('ptn.toleransi_lingkungan_m', 10),
            'rujukan' => ['keadaan_tapak' => Lalai::KEADAAN_TAPAK, 'kategori_isu' => Lalai::KATEGORI_ISU, 'status_item' => Lalai::STATUS_ITEM, 'kategori_item' => Lalai::KATEGORI_ITEM, 'jenis_penyelenggaraan' => Lalai::JENIS_PENYELENGGARAAN, 'medan_cadangan' => Lalai::MEDAN_CADANGAN],
        ]]);
    }

    /** The officer's own recent visits (status after sync: dihantar, konflik, dikembalikan…). */
    public function lawatanSaya(Request $request): JsonResponse
    {
        $rows = RekodLawatan::query()->with('pegawai')->where('id_pegawai', $request->user()->id)->orderByDesc('updated_at')->limit(50)->get();
        $lots = $this->lots->summaries($rows->pluck('lot_id')->unique()->values()->all(), $request->user());

        return response()->json(['data' => $rows->map(fn ($l) => $this->present->lawatan($l, $lots[$l->lot_id]['no_lot'] ?? null) + [
            'item' => $l->item()->get(['kod_item', 'status_item', 'catatan', 'kategori_isu', 'tahap_keterukan']),
        ])->values()]);
    }

    /** @return array<string, mixed> */
    private function rules(bool $create): array
    {
        return [
            'id_luar_talian' => [$create ? 'required' : 'nullable', 'uuid'], 'sumber' => ['nullable', Rule::in(['web', 'pwa'])],
            'id_jadual' => ['nullable', 'uuid'], 'lot_id' => ['nullable', 'uuid'], 'versi_jadual' => ['nullable', 'integer'],
            'tarikh_lawatan' => ['nullable', 'date', 'before_or_equal:tomorrow'], 'masa_mula' => ['nullable', 'date'], 'masa_tamat' => ['nullable', 'date'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'], 'ketepatan_gps_m' => ['nullable', 'numeric', 'min:0'],
            'keadaan_tapak' => ['nullable', Rule::in(array_keys(Lalai::KEADAAN_TAPAK))],
            'ringkasan_penemuan' => ['nullable', 'string', 'max:5000'], 'isu_pencerobohan' => ['nullable', 'string', 'max:5000'],
            'isu_penyelenggaraan' => ['nullable', 'string', 'max:5000'], 'cadangan_tindakan' => ['nullable', 'string', 'max:5000'],
            'item' => ['nullable', 'array', 'max:200'], 'item.*.kod_item' => ['required', 'string', 'max:50'],
            'item.*.status_item' => ['required', Rule::in(array_keys(Lalai::STATUS_ITEM))], 'item.*.catatan' => ['nullable', 'string', 'max:2000'],
            'item.*.kategori_isu' => ['nullable', Rule::in(array_keys(Lalai::KATEGORI_ISU))], 'item.*.tahap_keterukan' => ['nullable', Rule::in(array_keys(Lalai::TAHAP_KETERUKAN))],
            'penyelenggaraan' => ['nullable', 'array', 'max:20'], 'penyelenggaraan.*.jenis' => ['required', Rule::in(array_keys(Lalai::JENIS_PENYELENGGARAAN))],
            'penyelenggaraan.*.keterangan' => ['required', 'string', 'max:2000'], 'penyelenggaraan.*.keutamaan' => ['nullable', Rule::in(array_keys(Lalai::TAHAP_KETERUKAN))],
            'penyelenggaraan.*.tarikh_jadual' => ['nullable', 'date'],
            'cadangan' => ['nullable', 'array', 'max:10'], 'cadangan.*.medan' => ['required', Rule::in(array_keys(Lalai::MEDAN_CADANGAN))],
            'cadangan.*.nilai_dicadang' => ['required', 'string', 'max:2000'], 'cadangan.*.justifikasi' => ['required', 'string', 'max:2000'],
        ];
    }

    public function cipta(Request $request, LawatanService $service): JsonResponse
    {
        $d = $request->validate($this->rules(true));
        $user = $request->user();
        if (! $user->can('ptn.lapangan.kemas_kini') && ! $user->can('ptn.lawatan.cipta')) {
            abort(403);
        }
        // Without a schedule only officers allowed ad-hoc visits; a schedule reassigned after
        // download is NOT refused — the visit is kept as a conflict (no data loss, SRS-PTN-05).
        if (empty($d['id_jadual']) && ! $user->can('ptn.lawatan.cipta')) {
            abort(403, (string) __('ptn::ptn.tanpa_jadual'));
        }
        $r = $service->cipta($d, $user, $request->header('X-Sumber') === 'pwa' || ($d['sumber'] ?? null) === 'pwa' ? 'pwa' : 'web');

        return response()->json(['data' => $this->present->lawatan($r['lawatan']), 'status_segerak' => $r['status']], $r['status'] === 'pendua' ? 200 : 201);
    }

    private function milik(Request $request, string $id): RekodLawatan
    {
        $l = RekodLawatan::query()->find($id);
        if ($l === null || ! $this->akses->bolehUbahLawatan($request->user(), $l)) {
            abort(404);
        }

        return $l;
    }

    public function kemaskini(Request $request, string $lawatan, LawatanService $service): JsonResponse
    {
        $l = $this->milik($request, $lawatan);
        $d = $request->validate($this->rules(false));
        $service->simpan($l, $d);

        return response()->json(['data' => $this->present->lawatan($l->refresh())]);
    }

    public function gambar(Request $request, string $lawatan, LawatanService $service): JsonResponse
    {
        $l = $this->milik($request, $lawatan);
        $d = $request->validate([
            'fail' => ['required', 'file', 'max:25600'], 'id_luar_talian' => ['required', 'uuid'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'], 'ketepatan_m' => ['nullable', 'numeric', 'min:0'],
            'arah_kamera' => ['nullable', 'numeric'], 'tarikh_masa' => ['nullable', 'date'], 'sumber_gambar' => ['nullable', Rule::in(['kamera', 'galeri'])],
            'kod_item' => ['nullable', 'string', 'max:50'], 'jenis' => ['nullable', Rule::in(['umum', 'sebelum', 'selepas'])], 'kapsyen' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $r = $service->tambahGambar($l, $request->file('fail'), $d, $request->user());
        } catch (DocumentRejected $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'dokumen_ditolak', 'errors' => ['fail' => [$e->getMessage()]]], 422);
        }
        $g = GambarLawatan::query()->denganKoordinat()->findOrFail($r['gambar']->id);

        return response()->json(['data' => $this->present->gambar($g, false), 'status_segerak' => $r['status']], $r['status'] === 'pendua' ? 200 : 201);
    }

    public function hantar(Request $request, string $lawatan, LawatanService $service): JsonResponse
    {
        $l = $this->milik($request, $lawatan);
        $r = $service->hantar($l, $request->user());

        return response()->json(['data' => $this->present->lawatan($r['lawatan']), 'amaran' => $r['amaran'], 'message' => __('ptn::ptn.lawatan_dihantar')]);
    }

    public function templat(Request $request, string $templat): JsonResponse
    {
        $t = TemplatSenaraiSemak::query()->findOrFail($templat);

        return response()->json(['data' => ['id' => $t->id, 'kod' => $t->kod, 'nama' => $t->nama, 'versi' => $t->versi, 'senarai_item' => $t->senarai_item]]);
    }
}
