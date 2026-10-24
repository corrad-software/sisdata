<?php

namespace App\Modules\Gis\Services\Geocoding;

use App\Models\User;
use App\Modules\Gis\Contracts\Geocoder;
use App\Modules\Gis\Services\LotScope;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\DB;

/**
 * Default location search: lot numbers (through MOD-PRO's LotRegistry, so
 * the user's scope applies), then daerah / mukim / PBT names (ref_sempadan)
 * and mosques (ref_tempat_ibadat). No external call.
 */
class LocalGeocoder implements Geocoder
{
    public function __construct(private LotRegistry $lots, private LotScope $scope, private AccessControl $access) {}

    public function search(string $query, User $user, int $limit = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }
        $results = [];

        if ($this->access->allows($user, 'pro.lot.lihat')) {
            $rows = $this->lots->search($query, $user, $limit);
            $ids = array_column($rows, 'id');
            if ($ids !== []) {
                [$where, $bindings] = $this->scope->where($user, 'l');
                $geo = collect(DB::select(
                    'SELECT l.id::text AS id, ST_X(c) AS lng, ST_Y(c) AS lat, ST_XMin(e) AS x0, ST_YMin(e) AS y0, ST_XMax(e) AS x1, ST_YMax(e) AS y1
                     FROM lot_tanah l LEFT JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
                     CROSS JOIN LATERAL (SELECT COALESCE(l.koordinat_pusat, ST_PointOnSurface(ds.geometri)) AS c, ST_Envelope(ds.geometri) AS e) g
                     WHERE l.id::text IN ('.implode(',', array_fill(0, count($ids), '?')).") AND {$where}",
                    [...$ids, ...$bindings],
                ))->keyBy('id');
                foreach ($rows as $row) {
                    $g = $geo->get($row['id']);
                    if ($g === null || $g->lng === null) {
                        continue;
                    }
                    $results[] = [
                        'label' => $row['no_lot'].' · '.$row['kod_mukim'],
                        'jenis' => 'lot',
                        'lng' => (float) $g->lng,
                        'lat' => (float) $g->lat,
                        'bbox' => $g->x0 !== null ? [(float) $g->x0, (float) $g->y0, (float) $g->x1, (float) $g->y1] : null,
                        'id' => $row['id'],
                        'pautan' => $this->lots->profileLink($row['id']),
                    ];
                }
            }
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';
        foreach (DB::select(
            'SELECT jenis, kod, nama, ST_X(ST_PointOnSurface(geometri)) AS lng, ST_Y(ST_PointOnSurface(geometri)) AS lat,
                    ST_XMin(geometri) AS x0, ST_YMin(geometri) AS y0, ST_XMax(geometri) AS x1, ST_YMax(geometri) AS y1
             FROM ref_sempadan WHERE (nama ILIKE ? OR kod = ?) AND jenis <> \'negeri\' ORDER BY jenis, nama LIMIT ?',
            [$like, $query, $limit],
        ) as $r) {
            $results[] = ['label' => ucfirst((string) $r->jenis).' '.$r->nama, 'jenis' => (string) $r->jenis, 'lng' => (float) $r->lng, 'lat' => (float) $r->lat,
                'bbox' => [(float) $r->x0, (float) $r->y0, (float) $r->x1, (float) $r->y1], 'id' => (string) $r->kod, 'pautan' => null];
        }

        foreach (DB::select('SELECT id, nama, jenis, ST_X(geometri) AS lng, ST_Y(geometri) AS lat FROM ref_tempat_ibadat WHERE nama ILIKE ? ORDER BY nama LIMIT ?', [$like, $limit]) as $r) {
            $results[] = ['label' => (string) $r->nama, 'jenis' => 'tempat_ibadat', 'lng' => (float) $r->lng, 'lat' => (float) $r->lat, 'bbox' => null, 'id' => (string) $r->id, 'pautan' => null];
        }

        return array_slice($results, 0, max($limit, 1) * 2);
    }
}
