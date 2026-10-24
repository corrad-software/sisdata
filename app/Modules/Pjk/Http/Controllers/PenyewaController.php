<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pjk\Http\Present;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Services\PenyewaService;
use App\Modules\Pjk\Support\Akses;
use App\Modules\Pjk\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SRS-PJK-01 tenant register. Lists always mask identity numbers; the full
 * record needs pjk.sulit.lihat and every such view is audited.
 */
class PenyewaController
{
    public function __construct(private Akses $akses, private PenyewaService $service, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $page = $this->akses->penyewa($user)
            ->when($q !== '', function ($w) use ($q) {
                $digits = preg_replace('/\D/', '', $q) ?? '';
                $w->where(fn ($x) => $x->where('nama', 'ilike', '%'.$q.'%')
                    ->when(strlen($digits) >= 4, fn ($y) => $y->orWhereRaw("regexp_replace(coalesce(no_pengenalan, ''), '\\D', '', 'g') = ?", [$digits])->orWhere('no_pendaftaran_syarikat', $q)));
            })
            ->when($request->filled('jenis_entiti'), fn ($w) => $w->where('jenis_entiti', $request->query('jenis_entiti')))
            ->withCount('kontrak')->orderBy('nama')->paginate(min(100, (int) $request->query('per_page', 20)));

        return response()->json([
            'data' => collect($page->items())->map(fn (Penyewa $p) => Present::penyewa($p) + ['bil_kontrak' => (int) $p->getAttribute('kontrak_count')])->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $p = $this->akses->penyewa($user)->findOrFail($id);
        $full = $this->akses->bolehLihatPenuh($user);
        if ($full) {
            $this->audit->record('lihat', 'pjk', 'penyewa', $p->id, meta: ['klasifikasi' => 'sulit', 'medan' => 'butiran_penuh']);
        }
        $contracts = Kontrak::query()->where('penyewa_id', $p->id)->orderByDesc('tarikh_mula')->get();

        return response()->json(['data' => Present::penyewa($p, $full) + [
            'boleh_lihat_penuh' => $full,
            'kontrak' => $contracts->map(fn (Kontrak $k) => [
                'id' => $k->id, 'no_kontrak' => $k->no_kontrak, 'status' => $k->status, 'status_nama' => Kod::STATUS_KONTRAK[$k->status] ?? $k->status,
                'tarikh_mula' => $k->tarikh_mula?->format('Y-m-d'), 'tarikh_tamat' => $k->tarikh_tamat?->format('Y-m-d'), 'kadar' => Present::wang($k->kadar),
            ])->values()->all(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $p = $this->service->create($request->all(), $user);

        return response()->json(['data' => Present::penyewa($p, $this->akses->bolehLihatPenuh($user)), 'message' => __('pjk::pjk.mesej.penyewa_didaftar')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $p = $this->service->update($this->akses->penyewa($user)->findOrFail($id), $request->all());

        return response()->json(['data' => Present::penyewa($p, $this->akses->bolehLihatPenuh($user)), 'message' => __('pjk::pjk.mesej.disimpan')]);
    }
}
