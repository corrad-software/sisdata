<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Gis\Contracts\VectorTileLayer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mapbox Vector Tiles via PostGIS ST_AsMVT. The layer's source SQL is already
 * scoped to the user; tiles are cached per (layer, scope signature, filters,
 * layer config stamp, z/x/y) in this app's cache store (Redis, prefixed per
 * environment), so users who see the same data share tiles and users who
 * don't never do.
 */
class TileService
{
    public function __construct(private Parameters $parameters, private LayerService $layers) {}

    /** @param array<string, mixed> $filters */
    public function tile(User $user, VectorTileLayer $layer, int $z, int $x, int $y, array $filters = []): string
    {
        if ($z < 0 || $z > 22 || $x < 0 || $y < 0 || $x >= 2 ** $z || $y >= 2 ** $z) {
            throw new NotFoundHttpException(__('gis::gis.tile.luar_julat'));
        }

        $ttl = (int) $this->parameters->get('gis.tile_cache_saat', 300);
        $build = fn () => $this->build($user, $layer, $z, $x, $y, $filters);
        if ($ttl <= 0) {
            return $build();
        }

        ksort($filters);
        $stamp = $this->layers->config($layer)->updated_at?->getTimestamp() ?? 0;
        $key = sprintf('gis:mvt:%s:%s:%s:%d:%d/%d/%d', $layer->key(), $layer->scopeSignature($user), substr(md5((string) json_encode($filters)), 0, 10), $stamp, $z, $x, $y);

        return (string) base64_decode((string) Cache::remember($key, $ttl, fn () => base64_encode($build())));
    }

    /** @param array<string, mixed> $filters */
    private function build(User $user, VectorTileLayer $layer, int $z, int $x, int $y, array $filters): string
    {
        $source = $layer->tileSource($user, $filters);
        $columns = array_values(array_filter($source['columns'], fn ($c) => preg_match('/^[a-z_][a-z0-9_]*$/', $c) === 1));
        $select = implode(', ', array_map(fn ($c) => 's."'.$c.'"', $columns));
        $select = $select === '' ? '' : ', '.$select;

        $row = DB::selectOne(
            "WITH env AS (SELECT ST_TileEnvelope(?, ?, ?) AS e3857)
             SELECT ST_AsMVT(t, ?::text, 4096, 'mvtgeom') AS mvt FROM (
                 SELECT ST_AsMVTGeom(ST_Transform(s.geom, 3857), env.e3857, 4096, 64, true) AS mvtgeom{$select}
                 FROM ({$source['sql']}) s, env
                 WHERE s.geom IS NOT NULL AND s.geom && ST_Transform(env.e3857, 4326)
             ) t WHERE t.mvtgeom IS NOT NULL",
            [$z, $x, $y, $layer->key(), ...$source['bindings']],
        );

        $mvt = $row?->mvt;
        if (is_resource($mvt)) {
            $mvt = stream_get_contents($mvt);
        }

        return (string) $mvt;
    }
}
