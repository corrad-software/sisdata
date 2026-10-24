<?php

namespace App\Modules\Per\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Per\Http\CasePresenter;
use App\Modules\Per\Models\BayaranNotis5a;
use App\Modules\Per\Models\DokumenPemerolehan;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PihakBerkaitan;
use App\Modules\Per\Services\CaseDocuments;
use App\Modules\Per\Services\CaseService;
use App\Modules\Per\Services\FaraidVerifier;
use App\Modules\Per\Services\Payments5a;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sub-records of a case: related parties (SRS-PER-23), documents and
 * checklist (16/22), 5A payments (04/05) and e-Faraid verification (13).
 */
class ButiranKesController extends Controller
{
    public function __construct(
        private CaseService $cases,
        private CasePresenter $presenter,
        private AccessControl $access,
        private AuditLogger $audit,
    ) {}

    // ------------------------------------------------------------- parties

    public function addParty(Request $request, string $kes): JsonResponse
    {
        $case = $this->find($kes);
        $this->assertOpen($case);
        $data = $request->validate(KesController::partyRules());
        $party = $this->cases->addParty($case, $data);

        return response()->json(['data' => $this->presenter->party($party, $this->sulit($request))], 201);
    }

    public function updateParty(Request $request, string $kes, int $pihak): JsonResponse
    {
        $case = $this->find($kes);
        $this->assertOpen($case);
        $party = PihakBerkaitan::query()->where('id_pemerolehan', $case->id)->findOrFail($pihak);
        $rules = collect(KesController::partyRules())->map(fn ($r) => ['sometimes', ...$r])->all();
        $data = $request->validate($rules);
        // Identity fields of a party may only be changed by an officer cleared for Sulit data.
        if (! $this->sulit($request)) {
            unset($data['nama'], $data['no_dokumen_pengenalan']);
        }
        $party->forceFill($data)->save();

        return response()->json(['data' => $this->presenter->party($party, $this->sulit($request))]);
    }

    public function deleteParty(Request $request, string $kes, int $pihak): JsonResponse
    {
        $case = $this->find($kes);
        $this->assertOpen($case);
        PihakBerkaitan::query()->where('id_pemerolehan', $case->id)->findOrFail($pihak)->delete();

        return response()->json(['message' => __('per::per.pihak_dipadam')]);
    }

    /** GET /kes/{id}/pihak/{pihak}/penuh — unmasked ID number; Sulit, audited (SRS §4.2). */
    public function revealParty(Request $request, string $kes, int $pihak): JsonResponse
    {
        $case = $this->find($kes);
        $party = PihakBerkaitan::query()->where('id_pemerolehan', $case->id)->findOrFail($pihak);
        $this->audit->record('lihat', 'per', 'pihak_berkaitan_pemerolehan', $party->id, meta: [
            'no_kes' => $case->no_kes, 'medan' => 'no_dokumen_pengenalan', 'klasifikasi' => 'sulit',
        ]);

        return response()->json(['data' => $this->presenter->party($party, true, true)]);
    }

    public function verifyFaraid(Request $request, string $kes, FaraidVerifier $verifier): JsonResponse
    {
        $case = $this->find($kes);
        $this->assertOpen($case);
        $verifier->verify($case, $this->user($request));

        return response()->json(['data' => $this->presenter->detail($case->refresh(), $this->user($request), $this->cases), 'message' => __('per::per.e_faraid_disahkan')]);
    }

    // ------------------------------------------------------------- documents

    /** POST /kes/{id}/dokumen — multipart `fail` (+ id_semak) or JSON {id_semak, rujukan_dms}. */
    public function storeDocument(Request $request, string $kes, CaseDocuments $documents): JsonResponse
    {
        $case = $this->find($kes);
        $this->assertOpen($case);
        $data = $request->validate([
            'fail' => ['nullable', 'required_without:rujukan_dms', 'file'],
            'rujukan_dms' => ['nullable', 'required_without:fail', 'uuid'],
            'id_semak' => ['nullable', 'required_with:rujukan_dms', 'integer'],
            'jenis_dokumen' => ['nullable', 'string', 'max:60'],
            ...$this->locationRules(),
        ]);

        $doc = $request->hasFile('fail')
            ? $documents->upload($case, isset($data['id_semak']) ? (int) $data['id_semak'] : null, $request->file('fail'), $data, $this->user($request))
            : $documents->link($case, (int) $data['id_semak'], (string) $data['rujukan_dms'], $data, $this->user($request));

        return response()->json(['data' => $this->presenter->document($doc->load(['semak', 'pegawaiTerima', 'pergerakan'])), 'message' => __('per::per.dokumen_direkod')], 201);
    }

    public function updateDocument(Request $request, string $kes, int $dokumen, CaseDocuments $documents): JsonResponse
    {
        $case = $this->find($kes);
        $doc = DokumenPemerolehan::query()->where('id_pemerolehan', $case->id)->findOrFail($dokumen);
        $data = $request->validate(['status_dokumen' => ['sometimes', 'in:diterima,disahkan,ditolak'], ...$this->locationRules()]);
        $documents->update($case, $doc, $data);

        return response()->json(['data' => $this->presenter->document($doc->load(['semak', 'pegawaiTerima', 'pergerakan']))]);
    }

    public function moveDocument(Request $request, string $kes, int $dokumen, CaseDocuments $documents): JsonResponse
    {
        $case = $this->find($kes);
        $doc = DokumenPemerolehan::query()->where('id_pemerolehan', $case->id)->findOrFail($dokumen);
        $data = $request->validate(['jenis' => ['required', 'in:keluar,pulang'], 'tujuan' => ['nullable', 'required_if:jenis,keluar', 'string', 'max:255']]);
        $documents->move($doc, $data['jenis'], $data['tujuan'] ?? null, $this->user($request));

        return response()->json(['data' => $this->presenter->document($doc->refresh()->load(['semak', 'pegawaiTerima', 'pergerakan.pegawai']))]);
    }

    // ------------------------------------------------------------- 5A payments

    public function storePayment(Request $request, string $kes, Payments5a $payments): JsonResponse
    {
        $case = $this->find($kes);
        $this->assertOpen($case);
        $data = $request->validate([
            'jumlah_dibayar' => ['required', 'numeric', 'gt:0', 'max:99999999999999'],
            'tarikh_bayaran' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'mod_bayaran' => ['required', Rule::in(BayaranNotis5a::MOD)],
            'no_resit' => ['required', 'string', 'max:60'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        /** @var array{jumlah_dibayar: float, tarikh_bayaran: string, mod_bayaran: string, no_resit: string, catatan?: string|null} $data */
        $payments->record($case, $data, $this->user($request));

        return response()->json(['data' => $payments->summary($case), 'message' => __('per::per.bayaran_direkod')], 201);
    }

    public function confirmPayment(Request $request, string $kes, int $bayaran, Payments5a $payments): JsonResponse
    {
        $case = $this->find($kes);
        $payment = BayaranNotis5a::query()->where('id_pemerolehan', $case->id)->findOrFail($bayaran);
        $data = $request->validate(['terima' => ['required', 'boolean'], 'catatan' => ['nullable', 'string', 'max:2000']]);
        $payments->confirm($case, $payment, (bool) $data['terima'], $this->user($request), $data['catatan'] ?? null);

        return response()->json(['data' => $payments->summary($case), 'message' => __('per::per.bayaran_diproses')]);
    }

    // -------------------------------------------------------------

    /** @return array<string, list<mixed>> */
    private function locationRules(): array
    {
        return [
            'tarikh_dokumen' => ['nullable', 'date_format:Y-m-d'],
            'tarikh_terima' => ['nullable', 'date_format:Y-m-d'],
            'kod_peti_besi' => ['nullable', 'string', 'max:30'],
            'no_fail' => ['nullable', 'string', 'max:50'],
            'lokasi_rak' => ['nullable', 'string', 'max:50'],
            'status_dokumen_asal' => ['nullable', Rule::in(DokumenPemerolehan::STATUS_ASAL)],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function sulit(Request $request): bool
    {
        return $this->access->allows($this->user($request), 'per.sulit.lihat');
    }

    private function assertOpen(Pemerolehan $case): void
    {
        if (in_array($case->status_pemerolehan, [Pemerolehan::SELESAI, Pemerolehan::DIBATALKAN], true)) {
            throw new HttpException(409, __('per::per.kes_ditutup'));
        }
    }

    private function find(string $id): Pemerolehan
    {
        abort_unless(Str::isUuid($id), 404);

        return Pemerolehan::query()->with(['saluran', 'peringkat'])->findOrFail($id);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
