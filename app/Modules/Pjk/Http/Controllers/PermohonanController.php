<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pjk\Http\Present;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PermohonanSewaan;
use App\Modules\Pjk\Services\GatewayService;
use App\Modules\Pjk\Services\PermohonanService;
use App\Modules\Pjk\Support\Akses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * SRS-PJK-06: rental applications — counter (officers) and the public portal
 * (integration account with pjk.portal.cipta; two-way: submit + status back).
 */
class PermohonanController
{
    public function __construct(private PermohonanService $service, private Akses $akses, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $page = PermohonanSewaan::query()->with('premis:id,kod_premis,nama_premis')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('sumber'), fn ($q) => $q->where('sumber', $request->query('sumber')))
            ->when($request->filled('kod_daerah'), fn ($q) => $q->where('kod_daerah', $request->query('kod_daerah')))
            ->orderByDesc('created_at')->paginate(min(100, (int) $request->query('per_page', 20)));

        return response()->json([
            'data' => collect($page->items())->map(fn (PermohonanSewaan $p) => Present::permohonan($p))->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(Request $request, PermohonanSewaan $permohonan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $full = $this->akses->bolehLihatPenuh($user);
        if ($full) {
            $this->audit->record('lihat', 'pjk', 'permohonan_sewaan', $permohonan->id, meta: ['klasifikasi' => 'sulit']);
        }

        return response()->json(['data' => Present::permohonan($permohonan->load('premis'), $full)]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => Present::permohonan($this->service->create($request->all(), $user, 'kaunter')), 'message' => __('pjk::pjk.mesej.permohonan_diterima')], 201);
    }

    public function toContract(Request $request, PermohonanSewaan $permohonan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $k = $this->service->toContract($permohonan, $request->all(), $user);

        return response()->json(['data' => Present::kontrak($k), 'message' => __('pjk::pjk.mesej.kontrak_didaftar')], 201);
    }

    public function reject(Request $request, PermohonanSewaan $permohonan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = Validator::make($request->all(), ['catatan' => ['required', 'string', 'min:5', 'max:2000']], [], ['catatan' => 'sebab penolakan'])->validate();

        return response()->json(['data' => Present::permohonan($this->service->reject($permohonan, $data['catatan'], $user)), 'message' => __('pjk::pjk.mesej.disimpan')]);
    }

    // ---- Portal Hartanah Wakaf Selangor (integration account) ----

    public function portalStore(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $p = $this->service->create($request->all(), $user, 'portal');

        return response()->json(['data' => $this->service->statusFor($p)], $p->wasRecentlyCreated ? 201 : 200);
    }

    public function portalStatus(string $rujukan): JsonResponse
    {
        $p = PermohonanSewaan::query()->withoutGlobalScope('access')->where('rujukan_portal', $rujukan)->firstOrFail();

        return response()->json(['data' => $this->service->statusFor($p)]);
    }

    /** Portal-initiated online payment for a contract (payer completes it on the gateway page). */
    public function portalPayment(Request $request, GatewayService $gateway): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = Validator::make($request->all(), ['no_kontrak' => ['required', 'string', 'max:40']])->validate();
        $kontrak = Kontrak::query()->withoutGlobalScope('access')->where('no_kontrak', $data['no_kontrak'])->firstOrFail();
        $key = $request->header('Idempotency-Key');
        $tx = $gateway->initiate($kontrak, $request->except('no_kontrak', 'caj_ids'), is_string($key) && $key !== '' ? mb_substr($key, 0, 100) : null, $user, 'portal');

        return response()->json(['data' => ['no_rujukan' => $tx->no_rujukan, 'amaun' => $tx->amaun, 'status' => $tx->status, 'url_bayaran' => $tx->url_bayaran]], $tx->wasRecentlyCreated ? 201 : 200);
    }
}
