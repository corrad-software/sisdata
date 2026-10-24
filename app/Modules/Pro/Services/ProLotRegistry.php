<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Support\NoLot;
use Illuminate\Database\Eloquent\Builder;

/**
 * MOD-PRO's implementation of the LotRegistry contract. With a $user, the
 * user's RBC scope and classification level apply (a lot outside them
 * behaves as if it didn't exist); without one (console, queue) it reads the
 * whole register.
 */
class ProLotRegistry implements LotRegistry
{
    public function __construct(private LotQuery $lots) {}

    public function find(string $lotId, ?User $user = null): ?array
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $lotId)) {
            return null;
        }
        $lot = $this->query($user)->whereKey($lotId)->first();

        return $lot === null ? null : $this->row($lot);
    }

    public function summaries(array $lotIds, ?User $user = null): array
    {
        $ids = array_values(array_filter($lotIds, fn ($id) => (bool) preg_match('/^[0-9a-f-]{36}$/i', $id)));
        if ($ids === []) {
            return [];
        }

        return $this->query($user)->whereIn('lot_tanah.id', array_unique($ids))->get()
            ->mapWithKeys(fn (LotTanah $l) => [$l->id => $this->row($l)])->all();
    }

    public function search(string $query, ?User $user = null, int $limit = 20, array $filters = []): array
    {
        $q = $this->query($user)->where('status_lot', '!=', 'arkib');
        if (trim($query) !== '') {
            $key = NoLot::key($query);
            $q->where(fn ($w) => $w->where('no_lot', 'ilike', '%'.$query.'%')->orWhere('no_lot_normal', 'like', '%'.$key.'%'));
        }
        foreach (['kod_daerah', 'kod_mukim', 'bahagian_pemilik'] as $field) {
            if (! empty($filters[$field])) {
                $q->where('lot_tanah.'.$field, $filters[$field]);
            }
        }

        return $q->orderBy('no_lot')->limit(max(1, min($limit, 100)))->get()->map(fn (LotTanah $l) => $this->row($l))->values()->all();
    }

    /** @return array{id: string, no_lot: string, kod_mukim: string, kod_daerah: string, keluasan: ?string, unit_keluasan: string, kategori_guna_tanah: ?string, bahagian_pemilik: ?string, status_lot: string, klasifikasi: string} */
    private function row(LotTanah $lot): array
    {
        return [
            'id' => $lot->id,
            'no_lot' => $lot->no_lot,
            'kod_mukim' => $lot->kod_mukim,
            'kod_daerah' => $lot->kod_daerah,
            'keluasan' => $lot->keluasan,
            'unit_keluasan' => $lot->unit_keluasan,
            'kategori_guna_tanah' => $lot->kategori_guna_tanah,
            'bahagian_pemilik' => $lot->bahagian_pemilik,
            'status_lot' => $lot->status_lot,
            'klasifikasi' => $lot->klasifikasi,
        ];
    }

    public function exists(string $lotId, ?User $user = null): bool
    {
        return $this->find($lotId, $user) !== null;
    }

    public function findByNumber(string $kodMukim, string $noLot, ?User $user = null): ?string
    {
        $canonical = NoLot::canonical($noLot) ?? $noLot;

        $id = $this->query($user)->where('kod_mukim', $kodMukim)
            ->where(fn ($q) => $q->where('no_lot', $canonical)->orWhere('no_lot_normal', NoLot::key($noLot)))
            ->value('id');

        return $id !== null ? (string) $id : null;
    }

    public function profileLink(string $lotId): string
    {
        return '/admin/pro/lot/'.$lotId;
    }

    /** @return Builder<LotTanah> */
    private function query(?User $user): Builder
    {
        return $user !== null
            ? $this->lots->base($user)->where('status_lot', '!=', 'digabung')
            : LotTanah::query()->withoutGlobalScope('access');
    }
}
