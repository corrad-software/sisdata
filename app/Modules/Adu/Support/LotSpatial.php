<?php

namespace App\Modules\Adu\Support;

use Illuminate\Support\Facades\DB;

/**
 * SRS-ADU-09/10: point → MAIS lot. Pro\Contracts\LotRegistry has no spatial
 * lookup yet, so this reads MOD-PRO's current geometry (data_spatial,
 * semasa) read-only with PostGIS. Should move behind LotRegistry::lotAt()
 * when MOD-PRO adds it (noted in the module report).
 */
class LotSpatial
{
    /** Lot whose current polygon contains the point, or (within $toleranceM metres) the nearest one. */
    public function lotAt(float $lat, float $lng, int $toleranceM = 0): ?string
    {
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)';
        $row = DB::selectOne(
            "SELECT ds.lot_id FROM data_spatial ds JOIN lot_tanah l ON l.id = ds.lot_id AND l.deleted_at IS NULL
             WHERE ds.semasa AND ST_Contains(ds.geometri, {$point}) LIMIT 1",
            [$lng, $lat],
        );
        if ($row) {
            return (string) $row->lot_id;
        }
        if ($toleranceM <= 0) {
            return null;
        }
        $row = DB::selectOne(
            "SELECT ds.lot_id FROM data_spatial ds JOIN lot_tanah l ON l.id = ds.lot_id AND l.deleted_at IS NULL
             WHERE ds.semasa AND ST_DWithin(ds.geometri::geography, {$point}::geography, ?)
             ORDER BY ST_Distance(ds.geometri::geography, {$point}::geography) LIMIT 1",
            [$lng, $lat, $toleranceM, $lng, $lat],
        );

        return $row ? (string) $row->lot_id : null;
    }

    /** @return array{lat: float, lng: float}|null centroid of the lot (for a complaint linked by lot only). */
    public function pusatLot(string $lotId): ?array
    {
        $row = DB::selectOne(
            'SELECT ST_Y(c) AS lat, ST_X(c) AS lng FROM (
                SELECT COALESCE((SELECT ST_PointOnSurface(geometri) FROM data_spatial WHERE lot_id = ? AND semasa LIMIT 1), l.koordinat_pusat) AS c
                FROM lot_tanah l WHERE l.id = ?) x WHERE c IS NOT NULL',
            [$lotId, $lotId],
        );

        return $row ? ['lat' => (float) $row->lat, 'lng' => (float) $row->lng] : null;
    }
}
