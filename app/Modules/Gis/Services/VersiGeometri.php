<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * SRS-GIS-30: geometry versions of a lot. MOD-PRO's data_spatial is the
 * version store (every upload makes a new row, the previous one kept with
 * semasa = false); MOD-GIS lists and compares them (area, change, Hausdorff
 * distance, added/removed parts). Restoring = re-submitting an old version
 * through MOD-PRO's spatial API, which creates a new version and is audited.
 */
class VersiGeometri
{
    public function __construct(private LotScope $scope) {}

    /** @return list<array<string, mixed>> */
    public function versions(User $user, string $lotId): array
    {
        $this->assertVisible($user, $lotId);

        return array_map(fn ($r) => [
            'id' => (int) $r->id,
            'versi' => (int) $r->versi,
            'semasa' => (bool) $r->semasa,
            'sumber_data' => $r->sumber_data,
            'srid_asal' => (int) $r->srid_asal,
            'status_pengesahan' => $r->status_pengesahan,
            'keluasan_m2' => round((float) $r->luas, 2),
            'dicipta_pada' => $r->created_at,
            'pemuat_naik' => $r->pemuat_naik,
            'geometri' => json_decode((string) $r->j, true),
        ], DB::select(
            'SELECT ds.id, ds.versi, ds.semasa, ds.sumber_data, ds.srid_asal, ds.status_pengesahan, ds.created_at, u.name AS pemuat_naik,
                    ST_Area(ds.geometri::geography) AS luas, ST_AsGeoJSON(ds.geometri, 7) AS j
             FROM data_spatial ds LEFT JOIN users u ON u.id = ds.id_pemuat_naik WHERE ds.lot_id = ? ORDER BY ds.versi DESC, ds.id DESC',
            [$lotId],
        ));
    }

    /** @return array<string, mixed> */
    public function compare(User $user, string $lotId, int $a, int $b): array
    {
        $this->assertVisible($user, $lotId);
        $r = DB::selectOne(
            'WITH a AS (SELECT geometri g, versi FROM data_spatial WHERE lot_id = ? AND id = ?), b AS (SELECT geometri g, versi FROM data_spatial WHERE lot_id = ? AND id = ?)
             SELECT a.versi AS versi_a, b.versi AS versi_b, ST_Area(a.g::geography) AS luas_a, ST_Area(b.g::geography) AS luas_b,
                    ST_HausdorffDistance(ST_Transform(a.g, 3380), ST_Transform(b.g, 3380)) AS hausdorff_m,
                    ST_Area(ST_Difference(b.g, a.g)::geography) AS luas_ditambah, ST_Area(ST_Difference(a.g, b.g)::geography) AS luas_dibuang,
                    ST_Equals(a.g, b.g) AS sama,
                    ST_AsGeoJSON(ST_Difference(b.g, a.g), 7) AS ditambah, ST_AsGeoJSON(ST_Difference(a.g, b.g), 7) AS dibuang
             FROM a, b',
            [$lotId, $a, $lotId, $b],
        );
        if ($r === null) {
            throw new NotFoundHttpException(__('gis::gis.versi.tidak_wujud'));
        }

        return [
            'versi_a' => (int) $r->versi_a, 'versi_b' => (int) $r->versi_b,
            'luas_a_m2' => round((float) $r->luas_a, 2), 'luas_b_m2' => round((float) $r->luas_b, 2),
            'beza_luas_m2' => round((float) $r->luas_b - (float) $r->luas_a, 2),
            'hausdorff_m' => round((float) $r->hausdorff_m, 3),
            'luas_ditambah_m2' => round((float) $r->luas_ditambah, 2), 'luas_dibuang_m2' => round((float) $r->luas_dibuang, 2),
            'sama' => (bool) $r->sama,
            'ditambah' => json_decode((string) $r->ditambah, true),
            'dibuang' => json_decode((string) $r->dibuang, true),
        ];
    }

    private function assertVisible(User $user, string $lotId): void
    {
        [$where, $bindings] = $this->scope->where($user, 'l');
        $exists = preg_match('/^[0-9a-f-]{36}$/i', $lotId) === 1
            && DB::selectOne("SELECT 1 AS ok FROM lot_tanah l WHERE l.id = ? AND {$where}", [$lotId, ...$bindings]) !== null;
        if (! $exists) {
            throw new NotFoundHttpException(__('gis::gis.versi.lot_tidak_wujud'));
        }
    }
}
