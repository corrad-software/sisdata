<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Models\User;
use App\Modules\Pjk\Http\Present;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\SejarahStatusPremis;
use App\Modules\Pjk\Services\PremisService;
use App\Modules\Pjk\Services\SaluranPemerolehan;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-PJK-03/04: premise register and the (internal) offer catalogue. */
class PremisController
{
    public function __construct(private PremisService $service, private LotRegistry $lots, private SaluranPemerolehan $saluran) {}

    public function index(Request $request): JsonResponse
    {
        $page = self::filter(PremisAset::query(), $request)->orderBy('kod_premis')->paginate(min(100, (int) $request->query('per_page', 20)));
        $lots = $this->lots->summaries(collect($page->items())->pluck('lot_id')->unique()->values()->all(), $request->user());

        return response()->json([
            'data' => collect($page->items())->map(fn (PremisAset $p) => Present::premis($p) + ['no_lot' => $lots[$p->lot_id]['no_lot'] ?? null])->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /**
     * SRS-PJK-04 filters: daerah, mukim, kategori, keluasan, julat kadar, status.
     *
     * @param  Builder<PremisAset>  $q
     * @return Builder<PremisAset>
     */
    public static function filter(Builder $q, Request $request): Builder
    {
        $s = trim((string) $request->query('q', ''));

        return $q
            ->when($s !== '', fn ($w) => $w->where(fn ($x) => $x->where('nama_premis', 'ilike', '%'.$s.'%')->orWhere('kod_premis', 'ilike', '%'.$s.'%')->orWhere('alamat', 'ilike', '%'.$s.'%')))
            ->when($request->filled('kod_daerah'), fn ($w) => $w->where('kod_daerah', $request->query('kod_daerah')))
            ->when($request->filled('kod_mukim'), fn ($w) => $w->where('kod_mukim', $request->query('kod_mukim')))
            ->when($request->filled('kategori_hartanah'), fn ($w) => $w->where('kategori_hartanah', $request->query('kategori_hartanah')))
            ->when($request->filled('jenis_aset'), fn ($w) => $w->where('jenis_aset', $request->query('jenis_aset')))
            ->when($request->filled('status_premis'), fn ($w) => $w->where('status_premis', $request->query('status_premis')))
            ->when($request->filled('bahagian'), fn ($w) => $w->where('bahagian', $request->query('bahagian')))
            ->when($request->filled('lot_id'), fn ($w) => $w->where('lot_id', $request->query('lot_id')))
            ->when(is_numeric($request->query('keluasan_min')), fn ($w) => $w->where('keluasan', '>=', $request->query('keluasan_min')))
            ->when(is_numeric($request->query('keluasan_max')), fn ($w) => $w->where('keluasan', '<=', $request->query('keluasan_max')))
            ->when(is_numeric($request->query('kadar_min')), fn ($w) => $w->where('kadar_tawaran', '>=', Wang::norm((string) $request->query('kadar_min'))))
            ->when(is_numeric($request->query('kadar_max')), fn ($w) => $w->where('kadar_tawaran', '<=', Wang::norm((string) $request->query('kadar_max'))));
    }

    public function show(Request $request, PremisAset $premis): JsonResponse
    {
        $lot = $this->lots->find($premis->lot_id, $request->user());
        $history = SejarahStatusPremis::query()->where('premis_id', $premis->id)->orderByDesc('id')->limit(50)->get();
        $contracts = Kontrak::query()->where('premis_id', $premis->id)->orderByDesc('tarikh_mula')->get();

        return response()->json(['data' => Present::premis($premis) + [
            'no_lot' => $lot['no_lot'] ?? null,
            'status_perolehan' => $this->saluran->statusFor($premis),
            'sejarah_status' => $history->map(fn (SejarahStatusPremis $h) => ['tarikh' => $h->getAttribute('tarikh')?->format('Y-m-d'), 'status_sebelum' => $h->status_sebelum, 'status_selepas' => $h->status_selepas, 'status_nama' => Kod::STATUS_PREMIS[$h->status_selepas] ?? $h->status_selepas, 'sebab' => $h->sebab])->all(),
            'kontrak' => $contracts->map(fn (Kontrak $k) => ['id' => $k->id, 'no_kontrak' => $k->no_kontrak, 'penyewa' => $k->penyewa, 'status' => $k->status, 'status_nama' => Kod::STATUS_KONTRAK[$k->status] ?? $k->status, 'tarikh_mula' => $k->tarikh_mula?->format('Y-m-d'), 'tarikh_tamat' => $k->tarikh_tamat?->format('Y-m-d')])->all(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => Present::premis($this->service->create($request->all(), $user)), 'message' => __('pjk::pjk.mesej.premis_didaftar')], 201);
    }

    public function update(Request $request, PremisAset $premis): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => Present::premis($this->service->update($premis, $request->all(), $user)), 'message' => __('pjk::pjk.mesej.disimpan')]);
    }

    /** Lot picker (through MOD-PRO's LotRegistry, scoped). */
    public function lots(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->lots->search((string) $request->query('q', ''), $request->user(), 20, array_filter([
            'kod_daerah' => (string) $request->query('kod_daerah', ''), 'bahagian_pemilik' => (string) $request->query('bahagian', ''),
        ]))]);
    }
}
