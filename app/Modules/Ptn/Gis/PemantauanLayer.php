<?php

namespace App\Modules\Ptn\Gis;

use App\Models\User;
use App\Modules\Gis\Contracts\ClassifiedMapLayer;
use App\Modules\Gis\Contracts\VectorTileLayer;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\LotSql;
use Illuminate\Support\Facades\DB;

/**
 * Mandatory GIS layer 6 "Pemantauan Tanah" (SRS-GIS-11 slot ptn.*,
 * SRS-PTN-16): lot polygons coloured by monitoring status — lawatan dalam
 * tempoh / hampir tempoh / tertunggak (current lawatan thresholds), plus the
 * last site condition and open findings. Lots with an open critical finding
 * are red. Same RBC scope as the lot itself (LotSql).
 */
class PemantauanLayer implements ClassifiedMapLayer, VectorTileLayer
{
    public const WARNA = ['dalam_tempoh' => '#16A34A', 'hampir_tempoh' => '#EAB308', 'tertunggak' => '#DC2626', 'tiada_jadual' => '#9CA3AF'];

    public const COLUMNS = ['id', 'label', 'pautan', 'status_pemantauan', 'tarikh_sasaran_seterusnya', 'lawatan_terakhir', 'keadaan_tapak', 'isu_terbuka', 'isu_kritikal', 'kod_daerah', 'kod_mukim'];

    public function __construct(private LotSql $lots, private KodWarna $warna) {}

    public function key(): string
    {
        return 'ptn.pemantauan';
    }

    public function label(): string
    {
        return 'Pemantauan Tanah';
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
        return 'ptn.peta.lihat';
    }

    public function klasifikasi(): string
    {
        return 'dalaman';
    }

    public function legend(): array
    {
        $labels = ['dalam_tempoh' => 'Lawatan dalam tempoh', 'hampir_tempoh' => 'Hampir tempoh lawatan', 'tertunggak' => 'Lawatan tertunggak / penemuan kritikal', 'tiada_jadual' => 'Tiada jadual'];

        return ['medan' => 'status_pemantauan', 'entri' => array_map(fn ($k) => ['nilai' => $k, 'label' => $labels[$k], 'warna' => self::WARNA[$k]], array_keys(self::WARNA))];
    }

    /** @return array{0: string, 1: list<mixed>} */
    public function source(User $user, array $filters = []): array
    {
        [$where, $bindings] = $this->lots->where($user, 'l', $filters);
        $a = $this->warna->ambang('lawatan');
        $yellow = $a->tempoh_kelewatan_kuning ?? 14;
        $red = $a->tempoh_kelewatan_merah ?? 0;
        $sql = "SELECT l.id::text AS id, l.no_lot AS label, '/admin/ptn/lot/' || l.id::text AS pautan,
                CASE WHEN i.kritikal > 0 THEN 'tertunggak'
                     WHEN j.seterusnya IS NULL THEN 'tiada_jadual'
                     WHEN CURRENT_DATE - j.seterusnya > ? THEN 'tertunggak'
                     WHEN j.seterusnya - CURRENT_DATE <= ? THEN 'hampir_tempoh'
                     ELSE 'dalam_tempoh' END AS status_pemantauan,
                j.seterusnya::text AS tarikh_sasaran_seterusnya, v.tarikh_lawatan::text AS lawatan_terakhir, v.keadaan_tapak,
                COALESCE(i.terbuka, 0) AS isu_terbuka, COALESCE(i.kritikal, 0) AS isu_kritikal, l.kod_daerah, l.kod_mukim, ds.geometri AS geom
            FROM lot_tanah l
            JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
            LEFT JOIN LATERAL (SELECT MIN(tarikh_sasaran) AS seterusnya FROM jadual_pemantauan WHERE lot_id = l.id AND status_pelaksanaan IN ('dirancang','ditugaskan','dalam_pelaksanaan')) j ON true
            LEFT JOIN LATERAL (SELECT tarikh_lawatan, keadaan_tapak FROM rekod_lawatan WHERE lot_id = l.id AND status_lawatan IN ('dihantar','disahkan') ORDER BY tarikh_lawatan DESC LIMIT 1) v ON true
            LEFT JOIN LATERAL (SELECT COUNT(*) AS terbuka, COUNT(*) FILTER (WHERE tahap_keterukan = 'kritikal') AS kritikal FROM isu_pemantauan WHERE lot_id = l.id AND status_isu IN ('terbuka','dalam_tindakan')) i ON true
            WHERE {$where}";

        return [$sql, [$red, $yellow, ...$bindings]];
    }

    public function tileSource(User $user, array $filters = []): array
    {
        [$sql, $b] = $this->source($user, $filters);

        return ['sql' => $sql, 'bindings' => $b, 'columns' => self::COLUMNS];
    }

    public function scopeSignature(User $user): string
    {
        return $this->lots->signature($user, $this->key().':'.$this->warna->ambang('lawatan')?->id);
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        if (! $user->can($this->permission())) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        [$sql, $b] = $this->source($user, $filters);
        $q = 'SELECT s.*, ST_AsGeoJSON(ST_SimplifyPreserveTopology(s.geom, 0.000005), 7) AS _g FROM ('.$sql.') s WHERE s.geom IS NOT NULL';
        if (! empty($filters['status_pemantauan']) && is_string($filters['status_pemantauan'])) {
            $q .= ' AND s.status_pemantauan = ?';
            $b[] = $filters['status_pemantauan'];
        }
        if ($bbox !== null) {
            $q .= ' AND s.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)';
            array_push($b, ...array_map('floatval', $bbox));
        }
        $q .= ' LIMIT '.max(1, min($limit, 50000));
        $features = [];
        foreach (DB::select($q, $b) as $row) {
            $p = (array) $row;
            $g = json_decode((string) $p['_g'], true);
            unset($p['_g'], $p['geom']);
            $p['warna'] = self::WARNA[$p['status_pemantauan']] ?? '#9CA3AF';
            $features[] = ['type' => 'Feature', 'id' => $p['id'], 'geometry' => $g, 'properties' => $p];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
