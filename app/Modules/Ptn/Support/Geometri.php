<?php

namespace App\Modules\Ptn\Support;

use Illuminate\Support\Facades\DB;

/**
 * SRS-PTN-07 geofence + map helpers on MOD-PRO's current lot geometry
 * (data_spatial, semasa) — read-only PostGIS, like MOD-ADU's LotSpatial.
 * Pro\Contracts\LotRegistry has no spatial method yet (noted in the report).
 */
class Geometri
{
    /** Metres from the point to the lot polygon (0 = inside), null when the lot has no geometry. */
    public function jarak(string $lotId, float $lat, float $lng): ?float
    {
        $row = DB::selectOne(
            'SELECT CASE WHEN ST_Covers(geometri, p) THEN 0 ELSE ST_Distance(geometri::geography, p::geography) END AS m
             FROM data_spatial, (SELECT ST_SetSRID(ST_MakePoint(?, ?), 4326) AS p) pt WHERE lot_id = ? AND semasa LIMIT 1',
            [$lng, $lat, $lotId],
        );

        return $row ? round((float) $row->m, 2) : null;
    }

    /** @return array<string, mixed>|null GeoJSON geometry of the lot (simplified) */
    public function geojson(string $lotId): ?array
    {
        $row = DB::selectOne('SELECT ST_AsGeoJSON(geometri, 7) AS g FROM data_spatial WHERE lot_id = ? AND semasa LIMIT 1', [$lotId]);

        return $row ? json_decode((string) $row->g, true) : null;
    }

    /**
     * @param  list<string>  $lotIds
     * @return array<string, array<string, mixed>> lot id => GeoJSON geometry
     */
    public function geojsonBanyak(array $lotIds): array
    {
        if ($lotIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($lotIds), '?'));
        $out = [];
        foreach (DB::select("SELECT lot_id, ST_AsGeoJSON(ST_SimplifyPreserveTopology(geometri, 0.000005), 7) AS g FROM data_spatial WHERE semasa AND lot_id IN ({$marks})", $lotIds) as $r) {
            $out[(string) $r->lot_id] = json_decode((string) $r->g, true);
        }

        return $out;
    }

    /** @return array{lat: float, lng: float}|null */
    public function pusat(string $lotId): ?array
    {
        $row = DB::selectOne(
            'SELECT ST_Y(c) AS lat, ST_X(c) AS lng FROM (SELECT COALESCE((SELECT ST_PointOnSurface(geometri) FROM data_spatial WHERE lot_id = ? AND semasa LIMIT 1), l.koordinat_pusat) AS c FROM lot_tanah l WHERE l.id = ?) x WHERE c IS NOT NULL',
            [$lotId, $lotId],
        );

        return $row ? ['lat' => (float) $row->lat, 'lng' => (float) $row->lng] : null;
    }

    /**
     * Small SVG location map for the PDF report: lot polygon plus visit and
     * photo points (red when outside the boundary). No external tiles needed.
     *
     * @param  list<array{lat: float, lng: float, luar: bool, label: string}>  $titik
     */
    public function svg(string $lotId, array $titik, int $w = 420, int $h = 300): ?string
    {
        $geom = $this->geojson($lotId);
        if ($geom === null) {
            return null;
        }
        $rings = [];
        $polys = $geom['type'] === 'MultiPolygon' ? $geom['coordinates'] : [$geom['coordinates']];
        foreach ($polys as $poly) {
            foreach ($poly as $ring) {
                $rings[] = $ring;
            }
        }
        $xs = [];
        $ys = [];
        foreach ($rings as $ring) {
            foreach ($ring as $p) {
                $xs[] = (float) $p[0];
                $ys[] = (float) $p[1];
            }
        }
        foreach ($titik as $t) {
            $xs[] = $t['lng'];
            $ys[] = $t['lat'];
        }
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);
        $pad = 0.15;
        $dx = max($maxX - $minX, 0.0002);
        $dy = max($maxY - $minY, 0.0002);
        $minX -= $dx * $pad;
        $maxX += $dx * $pad;
        $minY -= $dy * $pad;
        $maxY += $dy * $pad;
        $kx = cos(deg2rad(($minY + $maxY) / 2));
        $scale = min($w / (($maxX - $minX) * $kx), $h / ($maxY - $minY));
        $px = fn (float $x) => round(($x - $minX) * $kx * $scale, 1);
        $py = fn (float $y) => round($h - ($y - $minY) * $scale, 1);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'"><rect width="100%" height="100%" fill="#f4f4f5"/>';
        foreach ($rings as $ring) {
            $pts = implode(' ', array_map(fn ($p) => $px((float) $p[0]).','.$py((float) $p[1]), $ring));
            $svg .= '<polygon points="'.$pts.'" fill="#2563eb" fill-opacity="0.15" stroke="#1d4ed8" stroke-width="2"/>';
        }
        foreach ($titik as $i => $t) {
            $svg .= '<circle cx="'.$px($t['lng']).'" cy="'.$py($t['lat']).'" r="5" fill="'.($t['luar'] ? '#dc2626' : '#16a34a').'" stroke="#fff" stroke-width="1.5"/>';
            $svg .= '<text x="'.($px($t['lng']) + 7).'" y="'.($py($t['lat']) + 4).'" font-size="10" fill="#18181b">'.e($t['label']).'</text>';
        }

        return $svg.'<text x="6" y="14" font-size="10" fill="#52525b">U ↑</text></svg>';
    }
}
