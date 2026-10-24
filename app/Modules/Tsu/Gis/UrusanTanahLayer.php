<?php

namespace App\Modules\Tsu\Gis;

use App\Models\User;
use App\Modules\Gis\Contracts\ClassifiedMapLayer;
use App\Modules\Tsu\Support\Lalai;
use App\Modules\Tsu\Support\SkopSql;
use Illuminate\Support\Facades\DB;

/**
 * Layer "tsu.urusan": lots with an active Land Office matter, coloured by
 * urusan type, with status / current stage / lateness. Scoped like the urusan.
 */
class UrusanTanahLayer implements ClassifiedMapLayer
{
    public const WARNA = [
        'tukar_syarat' => '#2563EB', 'pecah_sempadan' => '#9333EA', 'pecah_bahagian' => '#C026D3', 'penyatuan' => '#0891B2',
        'pelupusan' => '#DC2626', 'serah_balik_beri_milik' => '#D97706', 'hak_milik_kekal' => '#16A34A',
    ];

    public function __construct(private SkopSql $skop) {}

    public function key(): string
    {
        return 'tsu.urusan';
    }

    public function label(): string
    {
        return 'Urusan Tanah Aktif (Pejabat Tanah)';
    }

    public function group(): string
    {
        return 'operasi';
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
        return ['medan' => 'jenis_urusan', 'entri' => array_map(fn ($k) => ['nilai' => $k, 'label' => Lalai::JENIS_URUSAN[$k], 'warna' => self::WARNA[$k]], array_keys(self::WARNA))];
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        if (! $user->can($this->permission())) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        [$where, $wb] = $this->skop->where($user, 'u', $filters);
        $b = [today()->toDateString(), ...$wb];
        $sql = "SELECT u.id AS urusan_id, l.id AS lot_id, l.no_lot, u.no_rujukan_mais, u.jenis_urusan, u.status, u.kod_daerah, u.kod_mukim, p.nama_peringkat,
                    (p.tarikh_sasaran < ?::date) AS lewat, ST_AsGeoJSON(ST_SimplifyPreserveTopology(ds.geometri, 0.000005), 7) AS g
                FROM urusan_tanah u
                JOIN urusan_lot ul ON ul.urusan_id = u.id
                JOIN lot_tanah l ON l.id = ul.lot_id AND l.deleted_at IS NULL
                JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
                LEFT JOIN urusan_peringkat p ON p.urusan_id = u.id AND p.no_urutan = u.peringkat_semasa
                WHERE u.status IN ('aktif', 'menunggu_kelulusan') AND {$where}";
        if (! empty($filters['status']) && is_string($filters['status'])) {
            $sql .= ' AND u.status = ?';
            $b[] = $filters['status'];
        }
        if ($bbox !== null) {
            $sql .= ' AND ds.geometri && ST_MakeEnvelope(?, ?, ?, ?, 4326)';
            array_push($b, ...array_map('floatval', $bbox));
        }
        $sql .= ' LIMIT '.max(1, min($limit, 50000));
        $features = [];
        foreach (DB::select($sql, $b) as $r) {
            $features[] = ['type' => 'Feature', 'id' => $r->urusan_id.':'.$r->lot_id, 'geometry' => json_decode((string) $r->g, true), 'properties' => [
                'id' => $r->urusan_id, 'label' => $r->no_lot.' — '.$r->no_rujukan_mais, 'pautan' => '/admin/tsu/urusan/'.$r->urusan_id,
                'lot_id' => $r->lot_id, 'no_lot' => $r->no_lot, 'jenis_urusan' => $r->jenis_urusan, 'jenis_nama' => Lalai::JENIS_URUSAN[$r->jenis_urusan] ?? $r->jenis_urusan,
                'status' => $r->status, 'status_nama' => Lalai::STATUS_URUSAN[$r->status] ?? $r->status, 'peringkat' => $r->nama_peringkat, 'lewat' => (bool) $r->lewat,
                'kod_daerah' => $r->kod_daerah, 'kod_mukim' => $r->kod_mukim, 'warna' => self::WARNA[$r->jenis_urusan] ?? '#6B7280',
            ]];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
