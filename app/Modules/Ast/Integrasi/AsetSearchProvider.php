<?php

namespace App\Modules\Ast\Integrasi;

use App\Models\User;
use App\Modules\Adm\Contracts\DeepLinkProvider;
use App\Modules\Adm\Contracts\FilterableSearchProvider;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Support\Lalai;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** SRS-GEN-05: assets in global and detailed search (name, reference, QR ref, lot number, daerah / mukim), scoped to the user. */
class AsetSearchProvider implements DeepLinkProvider, FilterableSearchProvider
{
    public function entity(): string
    {
        return 'aset_fizikal';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        return $this->searchWithFilters($query, [], $user, $limit);
    }

    public function searchWithFilters(string $query, array $filters, User $user, int $limit = 10): array
    {
        if (! $user->can('ast.aset.lihat')) {
            return [];
        }
        $q = AsetFizikal::query()->untuk($user);
        $term = trim($query);
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $q->where(fn ($w) => $w->where('nama_aset', 'ilike', $like)->orWhere('no_rujukan_aset', 'ilike', $like)->orWhere('qr_ref', strtoupper($term))
                ->orWhereIn('lot_id', fn ($s) => $s->select('id')->from('lot_tanah')->where('no_lot', 'ilike', $like)));
        }
        if (! empty($filters['daerah'])) {
            $q->where('kod_daerah', $filters['daerah']);
        }
        if (! empty($filters['mukim'])) {
            $q->where('kod_mukim', $filters['mukim']);
        }
        if (! empty($filters['no_lot'])) {
            $q->whereIn('lot_id', fn ($s) => $s->select('id')->from('lot_tanah')->where('no_lot', 'ilike', '%'.$filters['no_lot'].'%'));
        }
        if (! empty($filters['status_tanah'])) {
            $q->where('status_kondisi', $filters['status_tanah']);
        }
        $rows = $q->orderBy('status_arkib')->orderBy('nama_aset')->limit($limit)->get();
        $lots = DB::table('lot_tanah')->whereIn('id', $rows->pluck('lot_id')->all())->pluck('no_lot', 'id');

        return $rows->map(fn (AsetFizikal $a) => [
            'id' => $a->id, 'title' => $a->nama_aset.' ('.$a->no_rujukan_aset.')',
            'subtitle' => 'Aset fizikal · '.($lots[$a->lot_id] ?? '').' · '.(Lalai::KONDISI[$a->status_kondisi] ?? $a->status_kondisi).($a->status_arkib ? ' · diarkib' : ''),
            'link' => '/admin/ast/aset/'.$a->id,
        ])->values()->all();
    }

    public function link(string $id, User $user): ?string
    {
        return Str::isUuid($id) && $user->can('ast.aset.lihat') && AsetFizikal::query()->untuk($user)->whereKey($id)->exists() ? '/admin/ast/aset/'.$id : null;
    }
}
