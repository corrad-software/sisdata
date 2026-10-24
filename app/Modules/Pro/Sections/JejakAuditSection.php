<?php

namespace App\Modules\Pro\Sections;

use App\Models\User;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Pro\Services\LotQuery;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tab "Dokumen & Jejak": audit chronology of the lot and its geometry, read
 * from MOD-AUD's append-only audit_rekod (read-only; MOD-AUD exposes no read
 * contract yet). Only for holders of pro.jejak.lihat.
 */
class JejakAuditSection implements ProfileSection
{
    public function __construct(private LotQuery $lots, private AccessControl $access) {}

    public function key(): string
    {
        return 'jejak_audit';
    }

    public function tab(): string
    {
        return 'dokumen';
    }

    public function label(): string
    {
        return (string) __('pro::pro.seksyen.jejak_audit');
    }

    /** @return list<object> */
    private function rows(string $lotId, User $user, int $limit = 100): array
    {
        if (! $this->access->allows($user, 'pro.jejak.lihat') || $this->lots->find($user, $lotId) === null || ! Schema::hasTable('audit_rekod')) {
            return [];
        }
        $spatialIds = DB::table('data_spatial')->where('lot_id', $lotId)->pluck('id')->map(fn ($id) => (string) $id)->all();

        return DB::table('audit_rekod as a')->leftJoin('users as u', 'u.id', '=', 'a.id_pengguna')
            ->where(fn ($w) => $w->where(fn ($q) => $q->where('a.nama_entiti', 'lot_tanah')->where('a.id_rekod', $lotId))
                ->orWhere(fn ($q) => $q->where('a.nama_entiti', 'data_spatial')->whereIn('a.id_rekod', $spatialIds)))
            ->whereNotIn('a.jenis_tindakan', ['lihat'])
            ->orderByDesc('a.no_urutan')->limit($limit)
            ->get(['a.id', 'a.tarikh_masa_utc', 'a.jenis_tindakan', 'a.nama_entiti', 'a.status_transaksi', 'a.sebab_perubahan', 'u.name as pengguna'])
            ->all();
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if (! $this->access->allows($user, 'pro.jejak.lihat')) {
            return null;
        }
        $rows = $this->rows($lotId, $user);
        $changes = $rows === [] ? collect() : DB::table('audit_perubahan_medan')->whereIn('id_audit', array_map(fn ($r) => $r->id, $rows))->get()->groupBy('id_audit');

        return [
            'items' => array_map(fn ($r) => [
                'tarikh' => (string) $r->tarikh_masa_utc,
                'label' => (string) __('pro::pro.tindakan_audit.'.$r->jenis_tindakan) === 'pro::pro.tindakan_audit.'.$r->jenis_tindakan ? $r->jenis_tindakan : (string) __('pro::pro.tindakan_audit.'.$r->jenis_tindakan),
                'nilai' => $r->pengguna,
                'entiti' => $r->nama_entiti,
                'status' => $r->status_transaksi,
                'sebab' => $r->sebab_perubahan,
                'medan' => ($changes[$r->id] ?? collect())->map(fn ($c) => ['medan' => $c->nama_medan, 'asal' => $c->nilai_asal, 'baharu' => $c->nilai_baharu])->values()->all(),
            ], $rows),
            'link' => '/admin/aud/kronologi?nama_entiti=lot_tanah&id_rekod='.$lotId,
        ];
    }

    public function timeline(string $lotId, User $user): array
    {
        return [];
    }
}
