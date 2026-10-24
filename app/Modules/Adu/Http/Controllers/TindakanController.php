<?php

namespace App\Modules\Adu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\LawatanTapak;
use App\Modules\Adu\Services\AduanService;
use App\Modules\Adu\Support\Akses;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Aud\Contracts\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** SRS-ADU-10/11/14/17/19: officer actions on a complaint. */
class TindakanController extends Controller
{
    public function __construct(private AduanService $service, private Akses $akses, private AduanController $aduan) {}

    public function tugaskan(Request $request, Aduan $aduan): JsonResponse
    {
        $data = $request->validate(['id_pegawai' => ['required', 'integer', 'exists:users,id'], 'catatan' => ['nullable', 'string', 'max:1000']]);
        $this->service->tugaskan($aduan, User::query()->findOrFail($data['id_pegawai']), $request->user(), $data['catatan'] ?? null);

        return $this->ok($request, $aduan, 'ditugaskan');
    }

    public function mulaSiasatan(Request $request, Aduan $aduan): JsonResponse
    {
        $this->urus($request, $aduan);
        $this->service->mulaSiasatan($aduan, $request->user());

        return $this->ok($request, $aduan, 'siasatan');
    }

    public function tindakan(Request $request, Aduan $aduan): JsonResponse
    {
        $this->urus($request, $aduan);
        $data = $request->validate([
            'jenis' => ['required', Rule::in(['tindakan', 'catatan', 'komunikasi', 'pembetulan'])],
            'keterangan' => ['required', 'string', 'min:3', 'max:5000'],
            'maklum_pengadu' => ['nullable', 'boolean'],
        ]);
        $this->service->tindakan($aduan, $request->user(), $data['jenis'], $data['keterangan'], (bool) ($data['maklum_pengadu'] ?? false));

        return $this->ok($request, $aduan, 'direkod');
    }

    public function jadualLawatan(Request $request, Aduan $aduan): JsonResponse
    {
        $this->urus($request, $aduan);
        $data = $request->validate(['tarikh_jadual' => ['required', 'date', 'after_or_equal:today'], 'id_pegawai' => ['nullable', 'integer', 'exists:users,id']]);
        $this->service->jadualLawatan($aduan, $request->user(), $data['tarikh_jadual'], $data['id_pegawai'] ?? null);

        return $this->ok($request, $aduan, 'lawatan_dijadual', 201);
    }

    public function rekodLawatan(Request $request, Aduan $aduan, LawatanTapak $lawatan): JsonResponse
    {
        abort_unless($lawatan->aduan_id === $aduan->id, 404);
        $this->urus($request, $aduan);
        $data = $request->validate(['penemuan' => ['required', 'string', 'min:3', 'max:5000'], 'tarikh_lawatan' => ['nullable', 'date', 'before_or_equal:now'], 'id_rekod_lawatan_ptn' => ['nullable', 'string', 'max:64']]);
        $this->service->rekodLawatan($lawatan, $request->user(), $data['penemuan'], $data['tarikh_lawatan'] ?? null, $data['id_rekod_lawatan_ptn'] ?? null);

        return $this->ok($request, $aduan, 'direkod');
    }

    public function rujuk(Request $request, Aduan $aduan): JsonResponse
    {
        $this->urus($request, $aduan);
        $data = $request->validate([
            'jenis_rujukan' => ['required', Rule::in(array_keys(Lalai::JENIS_RUJUKAN))],
            'nama_agensi' => ['nullable', 'required_if:jenis_rujukan,agensi_luar', 'string', 'max:200'],
            'keterangan' => ['required', 'string', 'min:5', 'max:5000'],
        ]);
        $this->service->rujuk($aduan, $request->user(), $data['jenis_rujukan'], $data['keterangan'], $data['nama_agensi'] ?? null);

        return $this->ok($request, $aduan, 'dirujuk');
    }

    public function eskalasi(Request $request, Aduan $aduan): JsonResponse
    {
        $this->urus($request, $aduan);
        $data = $request->validate(['sebab' => ['required', 'string', 'min:5', 'max:2000'], 'id_pegawai' => ['nullable', 'integer', 'exists:users,id']]);
        $this->service->eskalasiManual($aduan, $request->user(), $data['sebab'], isset($data['id_pegawai']) ? User::query()->find($data['id_pegawai']) : null);

        return $this->ok($request, $aduan, 'dieskalasi');
    }

    public function pendua(Request $request, Aduan $aduan): JsonResponse
    {
        $data = $request->validate(['id_aduan_induk' => ['required', 'uuid']]);
        $induk = Aduan::query()->findOrFail($data['id_aduan_induk']);
        $this->service->tandaPendua($aduan, $induk, $request->user());

        return $this->ok($request, $aduan, 'pendua');
    }

    public function bukanMais(Request $request, Aduan $aduan): JsonResponse
    {
        $data = $request->validate([
            'jawapan' => ['required', 'string', 'min:10', 'max:5000'],
            'agensi' => ['nullable', 'array', 'max:10'],
            'agensi.*.nama' => ['required', 'string', 'max:200'],
            'agensi.*.jenis' => ['required', 'string', 'max:30'],
        ]);
        $this->service->sahkanBukanMais($aduan, $request->user(), $data['jawapan'], $data['agensi'] ?? null);

        return $this->ok($request, $aduan, 'penutupan_dimohon');
    }

    public function penutupan(Request $request, Aduan $aduan): JsonResponse
    {
        $this->urus($request, $aduan);
        $data = $request->validate([
            'kategori_resolusi' => ['required', Rule::in(array_keys(Lalai::KATEGORI_RESOLUSI))],
            'keterangan_resolusi' => ['required', 'string', 'min:10', 'max:5000'],
            'justifikasi' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $this->service->mohonPenutupan($aduan, $request->user(), $data['kategori_resolusi'], $data['keterangan_resolusi'], $data['justifikasi']);

        return $this->ok($request, $aduan, 'penutupan_dimohon');
    }

    public function bukaSemula(Request $request, Aduan $aduan): JsonResponse
    {
        $this->urus($request, $aduan);
        $data = $request->validate(['sebab' => ['required', 'string', 'min:5', 'max:2000']]);
        $this->service->bukaSemula($aduan, $request->user(), $data['sebab']);

        return $this->ok($request, $aduan, 'dibuka_semula');
    }

    private function urus(Request $request, Aduan $aduan): void
    {
        if (! $this->akses->bolehUrus($request->user(), $aduan)) {
            app(AuditLogger::class)->record('kemas_kini', 'adu', 'aduan', $aduan->id, 'gagal', 'bukan_pegawai_ditugaskan');
            abort(403, __('adu::adu.ralat.bukan_pegawai'));
        }
    }

    private function ok(Request $request, Aduan $aduan, string $message, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $this->aduan->detail($aduan->refresh(), $request->user()), 'message' => __('adu::adu.mesej.'.$message)], $status);
    }
}
