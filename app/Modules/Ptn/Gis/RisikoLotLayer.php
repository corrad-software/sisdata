<?php

namespace App\Modules\Ptn\Gis;

use App\Models\User;
use App\Modules\Gis\Contracts\ClassifiedMapLayer;
use App\Modules\Gis\Contracts\VectorTileLayer;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\LotSql;
use Illuminate\Support\Facades\DB;

/**
 * SRS-PTN-14/16/17: "Risiko Aset Tanah (PTN)" — lot polygons coloured
 * hijau / kuning / merah from the current risk rating under the CURRENT
 * thresholds (a threshold change shows on the next tile request — the
 * threshold row id is part of the tile cache signature). Lots with an open
 * critical finding are forced red. Only the overall score is exposed.
 */
class RisikoLotLayer implements ClassifiedMapLayer, VectorTileLayer
{
    public const COLUMNS = ['id', 'label', 'pautan', 'kod_warna', 'skor_keseluruhan', 'paksa_merah', 'kod_daerah', 'kod_mukim'];

    public function __construct(private LotSql $lots, private KodWarna $warna) {}

    public function key(): string
    {
        return 'ptn.risiko';
    }

    public function label(): string
    {
        return 'Risiko Aset Tanah (Pemantauan)';
    }

    public function group(): string
    {
        return 'risiko';
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
        return ['medan' => 'kod_warna', 'entri' => array_map(fn ($k) => ['nilai' => $k, 'label' => KodWarna::papar($k)['label'], 'warna' => KodWarna::papar($k)['hex']], ['hijau', 'kuning', 'merah'])];
    }

    /** @return array{0: string, 1: list<mixed>} */
    public function source(User $user, array $filters = []): array
    {
        [$where, $bindings] = $this->lots->where($user, 'l', $filters);
        [$case, $cb] = $this->warna->sqlSkor('r.skor_keseluruhan');
        $sql = "SELECT l.id::text AS id, l.no_lot AS label, '/admin/ptn/lot/' || l.id::text AS pautan,
                CASE WHEN r.paksa_merah THEN 'merah' ELSE {$case} END AS kod_warna,
                r.skor_keseluruhan::float8 AS skor_keseluruhan, r.paksa_merah, l.kod_daerah, l.kod_mukim, ds.geometri AS geom
            FROM lot_tanah l
            JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
            JOIN risk_rating_lot r ON r.lot_id = l.id AND r.semasa
            WHERE {$where}";

        return [$sql, [...$cb, ...$bindings]];
    }

    public function tileSource(User $user, array $filters = []): array
    {
        [$sql, $b] = $this->source($user, $filters);

        return ['sql' => $sql, 'bindings' => $b, 'columns' => self::COLUMNS];
    }

    public function scopeSignature(User $user): string
    {
        $a = $this->warna->ambang('keseluruhan');

        return $this->lots->signature($user, $this->key().':'.$a?->id.':'.DB::table('risk_rating_lot')->max('tarikh_kiraan'));
    }

    public function features(User $user, ?array $bbox = null, array $filters = [], int $limit = 5000): array
    {
        if (! $user->can($this->permission())) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }
        [$sql, $b] = $this->source($user, $filters);
        $q = 'SELECT s.*, ST_AsGeoJSON(ST_SimplifyPreserveTopology(s.geom, 0.000005), 7) AS _g FROM ('.$sql.') s WHERE s.geom IS NOT NULL';
        if (! empty($filters['kod_warna']) && is_string($filters['kod_warna'])) {
            $q .= ' AND s.kod_warna = ?';
            $b[] = $filters['kod_warna'];
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
            $p['warna'] = KodWarna::papar((string) $p['kod_warna'])['hex'];
            $features[] = ['type' => 'Feature', 'id' => $p['id'], 'geometry' => $g, 'properties' => $p];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
