<?php

namespace App\Modules\Tsu\Gis;

use App\Models\User;
use App\Modules\Gis\Contracts\ClassifiedMapLayer;
use App\Modules\Tsu\Support\SkopSql;
use Illuminate\Support\Facades\DB;

/**
 * SRS-TSU-18: location of survey results in the GIS module, shown against
 * the title / land-use / zoning layers MOD-GIS already serves. Coloured by
 * verification status.
 */
class HasilUkurLayer implements ClassifiedMapLayer
{
    public const WARNA = ['menunggu' => '#EAB308', 'diterima' => '#16A34A', 'ditolak' => '#DC2626'];

    public function __construct(private SkopSql $skop) {}

    public function key(): string
    {
        return 'tsu.hasil_ukur';
    }

    public function label(): string
    {
        return 'Hasil Ukur (Tukar Syarat, Pelupusan dan Ukur)';
    }

    public function group(): string
    {
        return 'hartanah';
    }

    public function geometryType(): string
    {
        return 'polygon';
    }

    public function permission(): string
    {
        return 'tsu.peta.lihat';
    }

    public function klasifikasi(): string
    {
        return 'dalaman';
    }

    public function legend(): array
    {
        $labels = ['menunggu' => 'Menunggu pengesahan', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'];

        return ['medan' => 'status_pengesahan', 'entri' => array_map(fn ($k) => ['nilai' => $k, 'label' => $labels[$k], 'warna' => self::WARNA[$k]], array_keys(self::WARNA))];
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        if (! $user->can($this->permission())) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        [$where, $b] = $this->skop->where($user, 'u', $filters);
        $sql = "SELECT h.id, h.urusan_id, h.no_lot_cadangan, h.status_pengesahan, h.keluasan_dikira, h.keluasan_dokumen, h.sisihan_peratus, h.dalam_toleransi, u.no_rujukan_mais,
                    ST_AsGeoJSON(h.geometri, 7) AS g FROM hasil_ukur h JOIN urusan_tanah u ON u.id = h.urusan_id WHERE {$where}";
        if (! empty($filters['status_pengesahan']) && is_string($filters['status_pengesahan'])) {
            $sql .= ' AND h.status_pengesahan = ?';
            $b[] = $filters['status_pengesahan'];
        }
        if ($bbox !== null) {
            $sql .= ' AND h.geometri && ST_MakeEnvelope(?, ?, ?, ?, 4326)';
            array_push($b, ...array_map('floatval', $bbox));
        }
        $sql .= ' ORDER BY h.created_at DESC LIMIT '.max(1, min($limit, 50000));
        $out = [];
        foreach (DB::select($sql, $b) as $r) {
            $out[] = ['type' => 'Feature', 'id' => $r->id, 'geometry' => json_decode((string) $r->g, true), 'properties' => [
                'id' => $r->id, 'label' => ($r->no_lot_cadangan ?: 'Hasil ukur').' — '.$r->no_rujukan_mais, 'pautan' => '/admin/tsu/urusan/'.$r->urusan_id.'?tab=ukur',
                'status_pengesahan' => $r->status_pengesahan, 'keluasan_dikira' => (float) $r->keluasan_dikira, 'keluasan_dokumen' => $r->keluasan_dokumen !== null ? (float) $r->keluasan_dokumen : null,
                'sisihan_peratus' => $r->sisihan_peratus !== null ? (float) $r->sisihan_peratus : null, 'dalam_toleransi' => $r->dalam_toleransi, 'warna' => self::WARNA[$r->status_pengesahan] ?? '#6B7280',
            ]];
        }

        return ['type' => 'FeatureCollection', 'features' => $out];
    }
}
