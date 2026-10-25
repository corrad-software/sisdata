<?php

namespace App\Modules\Tkt\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tkt\Http\Present;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\Penugasan;
use App\Modules\Tkt\Services\SlaTiket;
use App\Modules\Tkt\Services\TiketService;
use App\Modules\Tkt\Support\Akses;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Support-team actions (route group `can:tkt.sokongan.kemas_kini`); each
 * also requires the officer to cover the ticket (assigned, team / team
 * module, or support management) — Support\Akses.
 */
class TindakanController extends Controller
{
    public function __construct(private TiketService $service, private Penugasan $penugasan, private Present $present, private Akses $akses) {}

    public function status(Request $request, string $tiket): JsonResponse
    {
        [$user, $t] = $this->urus($request, $tiket);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Lalai::STATUS))],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'penyelesaian' => ['nullable', 'string', 'max:10000'],
            'punca_akar' => ['nullable', 'string', 'max:5000'],
            'tindakan_pembetulan' => ['nullable', 'string', 'max:5000'],
            'masa_henti_minit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sebab_lepas_sla' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['status'] === 'tutup') {
            $this->service->tutup($t, $user, $data['catatan'] ?? null);
        } else {
            $this->service->tukarStatus($t, $data['status'], $user, $data['catatan'] ?? null, $data);
        }

        return $this->ok($t, $user, 'tkt::tkt.mesej.status');
    }

    public function catatan(Request $request, string $tiket): JsonResponse
    {
        [$user, $t] = $this->urus($request, $tiket);
        $data = $request->validate([
            'jenis' => ['required', Rule::in(['dalaman', 'kelihatan_pelapor'])],
            'kandungan' => ['required', 'string', 'max:10000'],
        ]);
        $this->service->catatan($t, $data['jenis'], $data['kandungan'], $user);

        return $this->ok($t, $user, 'tkt::tkt.mesej.catatan');
    }

    public function pegawaiLayak(Request $request, string $tiket): JsonResponse
    {
        [, $t] = $this->urus($request, $tiket);

        return response()->json(['data' => $this->penugasan->layak($t)]);
    }

    public function tugaskan(Request $request, string $tiket): JsonResponse
    {
        [$user, $t] = $this->urus($request, $tiket);
        $data = $request->validate([
            'id_pasukan' => ['required', 'integer'],
            'id_juruteknik' => ['nullable', 'integer'],
            'sebab' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->penugasan->tugasSemula($t, (int) $data['id_pasukan'], isset($data['id_juruteknik']) ? (int) $data['id_juruteknik'] : null, $data['sebab'], $user);

        return $this->ok($t, $user, 'tkt::tkt.mesej.ditugaskan');
    }

    /** L1 → L2 vendor (or back), with the vendor's reference number. */
    public function serahAras(Request $request, string $tiket): JsonResponse
    {
        [$user, $t] = $this->urus($request, $tiket);
        $data = $request->validate([
            'id_pasukan' => ['required', 'integer'],
            'rujukan_vendor' => ['nullable', 'string', 'max:120'],
            'catatan' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $this->penugasan->serahAras($t, (int) $data['id_pasukan'], $data['rujukan_vendor'] ?? null, $data['catatan'], $user);

        return $this->ok($t, $user, 'tkt::tkt.mesej.diserah');
    }

    public function vendor(Request $request, string $tiket): JsonResponse
    {
        [$user, $t] = $this->urus($request, $tiket);
        $data = $request->validate(['rujukan_vendor' => ['nullable', 'string', 'max:120'], 'nama_vendor' => ['nullable', 'string', 'max:150']]);
        $t->forceFill(array_filter($data, fn ($v) => $v !== null))->save();

        return $this->ok($t, $user, 'tkt::tkt.mesej.dikemas_kini');
    }

    /** Manual escalation to the next level of the hierarchy (SRS-TKT-09). */
    public function eskalasi(Request $request, string $tiket, SlaTiket $sla): JsonResponse
    {
        [$user, $t] = $this->urus($request, $tiket);
        $data = $request->validate([
            'penerima' => ['required', 'string', 'max:60', 'regex:/^(juruteknik|penyelia|pengurus|peranan:[a-z0-9_]+)$/'],
            'sebab' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $sla->eskalasi($t, max(1, $t->peringkat_eskalasi + 1), $data['penerima'], 'manual', 'manual', $data['sebab'], $user);

        return $this->ok($t, $user, 'tkt::tkt.mesej.dieskalasi');
    }

    public function ubah(Request $request, string $tiket): JsonResponse
    {
        [$user, $t] = $this->urus($request, $tiket);
        $data = $request->validate([
            'keterukan' => ['nullable', 'integer', 'between:1,4'],
            'kategori' => ['nullable', Rule::in(array_keys(Lalai::KATEGORI))],
            'modul' => ['nullable', Rule::in(array_keys(Lalai::MODUL))],
            'klasifikasi' => ['nullable', Rule::in(['dalaman', 'sulit'])],
            'sebab' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        if (($data['klasifikasi'] ?? null) === 'sulit' && ! $user->can('tkt.sulit.lihat')) {
            abort(403);
        }
        $this->service->ubahMedan($t, $data, $data['sebab'], $user);

        return $this->ok($t, $user, 'tkt::tkt.mesej.dikemas_kini');
    }

    public function pendua(Request $request, string $tiket): JsonResponse
    {
        [$user, $t] = $this->urus($request, $tiket);
        $data = $request->validate(['id_tiket_induk' => ['required', 'uuid'], 'catatan' => ['nullable', 'string', 'max:1000']]);
        $induk = Tiket::query()->bolehDilihat($user)->findOrFail($data['id_tiket_induk']);
        $this->service->tandaPendua($t, $induk, $user, $data['catatan'] ?? null);

        return $this->ok($t, $user, 'tkt::tkt.mesej.pendua');
    }

    /** The "ticket" is really a land complaint → MOD-ADU (ComplaintIntake). */
    public function rujukAduan(Request $request, string $tiket): JsonResponse
    {
        [$user, $t] = $this->urus($request, $tiket);
        $data = $request->validate([
            'kategori' => ['required', 'string', 'max:40'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
            'kod_daerah' => ['nullable', 'string', 'max:4'],
            'lot_id' => ['nullable', 'uuid'],
        ]);
        $this->service->rujukAduan($t, $data, $user);

        return $this->ok($t, $user, 'tkt::tkt.mesej.dirujuk_aduan');
    }

    public function mohonKelulusan(Request $request, string $tiket): JsonResponse
    {
        [$user, $t] = $this->urus($request, $tiket);
        $data = $request->validate(['justifikasi' => ['required', 'string', 'min:10', 'max:5000']]);
        $this->service->mohonKelulusan($t, $data['justifikasi'], $user);

        return $this->ok($t, $user, 'tkt::tkt.mesej.kelulusan_dimohon');
    }

    /** @return array{0: User, 1: Tiket} */
    private function urus(Request $request, string $id): array
    {
        $user = $request->user();
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $id) === 1, 404);
        $t = Tiket::query()->bolehDilihat($user)->with(['pelapor', 'juruteknik', 'pasukan'])->findOrFail($id);
        abort_unless($this->akses->bolehUrus($user, $t), 403);

        return [$user, $t];
    }

    private function ok(Tiket $t, User $user, string $message): JsonResponse
    {
        return response()->json(['data' => $this->present->butiran($t->refresh(), $user), 'message' => __($message)]);
    }
}
