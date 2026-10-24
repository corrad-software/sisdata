<?php

namespace App\Modules\Kes\Http\Controllers;

use App\Modules\Kes\Http\Present;
use App\Modules\Kes\Models\Keputusan;
use App\Modules\Kes\Models\Penyelesaian;
use App\Modules\Kes\Models\Rayuan;
use App\Modules\Kes\Models\TindakanSusulan;
use App\Modules\Kes\Services\KeputusanService;
use App\Modules\Kes\Support\Akses;
use App\Modules\Kes\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** SRS-KES-16..18: decisions, follow-up tasks, title updates; appeals and settlements. */
class KeputusanController extends KesBaseController
{
    public function __construct(Akses $akses, private KeputusanService $service, private Present $present)
    {
        parent::__construct($akses);
    }

    public function store(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate([
            'jenis_keputusan' => ['required', Rule::in(Keputusan::JENIS)],
            'tarikh_keputusan' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'peringkat_mahkamah' => ['nullable', Rule::in(array_keys(Kod::PERINGKAT_MAHKAMAH))],
            'ringkasan_perintah' => ['required', 'string', 'max:10000'],
            'implikasi' => ['nullable', 'string', 'max:10000'],
            'id_dokumen_penghakiman' => ['nullable', 'string', 'max:64'],
            'tindakan' => ['sometimes', 'array'], 'tindakan.*' => [Rule::in(TindakanSusulan::JENIS)],
        ]);
        $this->dokumenKes($k->id, $d['id_dokumen_penghakiman'] ?? null, 'id_dokumen_penghakiman');
        $r = $this->service->rekod($k, $d, $user);

        return response()->json(['data' => [...$this->present->keputusan($r), 'tindakan' => $r->tindakan()->get()->map(fn ($t) => $this->present->tindakan($t))->values()], 'message' => __('kes::kes.mesej.keputusan', ['n' => $r->tindakan()->count()])], 201);
    }

    public function tambahTindakan(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate([
            'jenis_tindakan' => ['required', Rule::in(TindakanSusulan::JENIS)], 'keterangan' => ['required', 'string', 'max:1000'],
            'tarikh_sasaran' => ['nullable', 'date_format:Y-m-d'], 'kes_keputusan_id' => ['nullable', 'integer'], 'id_lot_terjejas' => ['nullable', 'uuid'],
        ]);
        if (! empty($d['kes_keputusan_id'])) {
            Keputusan::query()->where('kes_mahkamah_id', $k->id)->findOrFail($d['kes_keputusan_id']);
        }
        if (! empty($d['id_lot_terjejas'])) {
            abort_unless(DB::table('kes_mahkamah_lot')->where('kes_mahkamah_id', $k->id)->where('lot_id', $d['id_lot_terjejas'])->exists(), 422, (string) __('kes::kes.ralat.lot_tiada'));
        }

        return response()->json(['data' => $this->present->tindakan($this->service->tindakan($k, $d, $user)), 'message' => __('kes::kes.mesej.tindakan')], 201);
    }

    /** SRS-KES-18: propose the title change to MOD-HKM with the court-order cross-reference. */
    public function kemasKiniRekod(Request $request, string $kes, int $tindakan): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $t = TindakanSusulan::query()->where('kes_mahkamah_id', $k->id)->findOrFail($tindakan);
        $d = $request->validate([
            'no_hak_milik' => ['required', 'string', 'max:80'], 'kod_mukim' => ['nullable', 'string', 'max:6'],
            'jenis_perubahan' => ['required', Rule::in(['butiran', 'status', 'pemilik', 'sekatan'])], 'data' => ['required', 'array'],
            'id_dokumen_perintah' => ['required', 'string', 'max:64'], 'lot_id' => ['nullable', 'uuid'], 'ringkasan' => ['nullable', 'string', 'max:1000'],
        ]);
        $r = $this->service->kemasKiniRekod($t, $d, $user);

        return response()->json(['data' => ['id' => $r->id, 'id_cadangan_hkm' => $r->id_cadangan_hkm, 'no_kes_mahkamah' => $r->no_kes_mahkamah, 'id_dokumen_perintah' => $r->id_dokumen_perintah], 'message' => __('kes::kes.mesej.rekod_hkm')], 201);
    }

    public function rekodDikemasKini(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);

        return response()->json(['data' => DB::table('kes_pengemaskinian_rekod')->where('kes_mahkamah_id', $k->id)->orderByDesc('id')->get()]);
    }

    public function rayuan(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate([
            'pihak_merayu' => ['required', Rule::in(['mais', 'pihak_lawan'])], 'kes_keputusan_id' => ['nullable', 'integer'],
            'mahkamah_rayuan' => ['nullable', 'string', 'max:150'], 'peringkat_mahkamah' => ['nullable', Rule::in(array_keys(Kod::PERINGKAT_MAHKAMAH))],
            'no_kes_rayuan' => ['nullable', 'string', 'max:80'], 'tarikh_had_failan' => ['nullable', 'date_format:Y-m-d'],
            'tarikh_failan' => ['nullable', 'date_format:Y-m-d'], 'alasan' => ['required', 'string', 'max:5000'],
        ]);
        if (! empty($d['kes_keputusan_id'])) {
            Keputusan::query()->where('kes_mahkamah_id', $k->id)->findOrFail($d['kes_keputusan_id']);
        }

        return response()->json(['data' => $this->present->rayuan($this->service->rayuan($k, $d, $user)), 'message' => __('kes::kes.mesej.rayuan')], 201);
    }

    public function putusRayuan(Request $request, string $kes, int $rayuan): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $r = Rayuan::query()->where('kes_mahkamah_id', $k->id)->whereIn('status', ['difailkan'])->findOrFail($rayuan);
        $d = $request->validate([
            'keputusan_rayuan' => ['required', Rule::in(Keputusan::JENIS)], 'no_kes_rayuan' => ['nullable', 'string', 'max:80'],
            'tarikh_keputusan' => ['nullable', 'date_format:Y-m-d'], 'ringkasan_perintah' => ['nullable', 'string', 'max:5000'], 'rekod_keputusan' => ['nullable', 'boolean'],
        ]);
        $this->service->putusRayuan($r, $d, $user);

        return response()->json(['data' => $this->present->rayuan($r->refresh()), 'message' => __('kes::kes.mesej.disimpan')]);
    }

    public function penyelesaian(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate(['terma' => ['required', 'string', 'max:10000'], 'nilai_penyelesaian' => ['nullable', 'regex:/^\d{1,14}(\.\d{1,2})?$/'], 'tarikh_cadangan' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['data' => $this->present->penyelesaian($this->service->penyelesaian($k, $d, $user)), 'message' => __('kes::kes.mesej.aliran_dimulakan')], 201);
    }

    public function meterai(Request $request, string $kes, int $penyelesaian): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $p = Penyelesaian::query()->where('kes_mahkamah_id', $k->id)->findOrFail($penyelesaian);
        $d = $request->validate(['id_dokumen_perjanjian' => ['required', 'string', 'max:64'], 'tarikh_persetujuan' => ['required', 'date_format:Y-m-d']]);
        $this->dokumenKes($k->id, $d['id_dokumen_perjanjian'], 'id_dokumen_perjanjian');
        $this->service->meterai($p, $d['id_dokumen_perjanjian'], $d['tarikh_persetujuan'], $user);

        return response()->json(['data' => $this->present->penyelesaian($p->refresh()), 'message' => __('kes::kes.mesej.dimeterai')]);
    }

    private function dokumenKes(string $kesId, ?string $docId, string $field): void
    {
        if ($docId !== null && $docId !== '' && ! DB::table('kes_dokumen')->where('kes_mahkamah_id', $kesId)->where('id_dokumen_dms', $docId)->exists()) {
            throw ValidationException::withMessages([$field => __('kes::kes.ralat.dokumen_perintah')]);
        }
    }
}
