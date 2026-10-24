<?php

namespace App\Modules\Gis\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Authenticated OGC proxy (SRS-GIS-25, GIS-32): WMS/WFS for dashboards,
 * the field PWA and external GIS tools, without ever exposing GeoServer.
 *
 *  - Only whitelisted OGC parameters pass; anything that could widen the
 *    query (CQL_FILTER, FILTER, FEATUREID, VIEWPARAMS, ENV, SLD…) is refused.
 *  - Every requested layer must be published (gis_lapisan.terbit_ogc) and
 *    allowed to the caller (LayerService::authorize — denials audited).
 *  - The caller's scope is injected as CQL_FILTER per layer; GeoServer is
 *    called with a service account (the workspace is closed to anonymous).
 *  - URLs in capabilities documents are rewritten to this proxy.
 *
 * WMTS/GWC tile caching is deliberately not offered for scoped layers: a
 * shared tile cache would serve one user's scope to another. MapLibre uses
 * the scoped MVT endpoint instead.
 */
class OgcProxy
{
    private const PARAMS = [
        'service', 'version', 'request', 'layers', 'query_layers', 'typename', 'typenames', 'styles', 'bbox', 'width', 'height', 'format',
        'info_format', 'srs', 'crs', 'transparent', 'bgcolor', 'x', 'y', 'i', 'j', 'feature_count', 'outputformat', 'count', 'maxfeatures',
        'startindex', 'srsname', 'propertyname', 'sortby', 'exceptions', 'tiled', 'legend_options', 'layer', 'style', 'sld_version', 'resulttype',
        'format_options', 'scale',
    ];

    private const FORBIDDEN = ['cql_filter', 'filter', 'featureid', 'resourceid', 'viewparams', 'env', 'sld', 'sld_body', 'storedquery_id', 'filter_language'];

    private const REQUESTS = [
        'wms' => ['getcapabilities', 'getmap', 'getfeatureinfo', 'getlegendgraphic'],
        'wfs' => ['getcapabilities', 'describefeaturetype', 'getfeature'],
    ];

    private const CONTENT_TYPES = ['image/', 'application/xml', 'text/xml', 'application/vnd.ogc', 'application/json', 'application/geo+json', 'text/plain', 'text/html', 'application/gml', 'text/javascript'];

    public function __construct(
        private LayerService $layers,
        private AccessControl $access,
        private AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $query */
    public function handle(User $user, string $service, array $query, string $proxyUrl): Response
    {
        $service = strtolower($service);
        if (! isset(self::REQUESTS[$service])) {
            throw new HttpException(404, __('gis::gis.ogc.servis_tidak_sah'));
        }
        $params = [];
        foreach ($query as $key => $value) {
            $k = strtolower((string) $key);
            if (in_array($k, self::FORBIDDEN, true)) {
                $this->audit->record('akses_ditolak', 'gis', 'ogc', $service, 'gagal', __('gis::gis.ogc.parameter_dilarang', ['parameter' => strtoupper($k)]));
                throw ValidationException::withMessages([$k => [__('gis::gis.ogc.parameter_dilarang', ['parameter' => strtoupper($k)])]]);
            }
            if (in_array($k, self::PARAMS, true) && is_scalar($value)) {
                $params[strtoupper($k)] = (string) $value;
            }
        }
        $params['SERVICE'] = strtoupper($service);
        $request = strtolower($params['REQUEST'] ?? '');
        if (! in_array($request, self::REQUESTS[$service], true)) {
            throw ValidationException::withMessages(['request' => [__('gis::gis.ogc.permintaan_tidak_sah')]]);
        }

        $published = $this->published($user);
        $layerParam = match (true) {
            isset($params['LAYERS']) => 'LAYERS',
            isset($params['TYPENAMES']) => 'TYPENAMES',
            isset($params['TYPENAME']) => 'TYPENAME',
            isset($params['LAYER']) => 'LAYER',
            default => null,
        };
        if ($request !== 'getcapabilities') {
            if ($layerParam === null) {
                throw ValidationException::withMessages(['layers' => [__('gis::gis.ogc.lapisan_diperlukan')]]);
            }
            $names = array_map('trim', explode(',', $params[$layerParam]));
            $filters = [];
            foreach ($names as $name) {
                $local = str_contains($name, ':') ? substr($name, strpos($name, ':') + 1) : $name;
                $key = array_search($local, (array) config('gis.ogc_layers'), true);
                if ($key === false) {
                    throw new HttpException(404, __('gis::gis.lapisan.tidak_wujud'));
                }
                $this->layers->authorize($user, (string) $key, 'ogc');
                if (! in_array($local, $published, true)) {
                    throw new HttpException(404, __('gis::gis.lapisan.tidak_wujud'));
                }
                $filters[] = $this->cql($user);
            }
            $params[$layerParam] = implode(',', array_map(fn ($n) => str_contains($n, ':') ? substr($n, strpos($n, ':') + 1) : $n, $names));
            if (isset($params['QUERY_LAYERS'])) {
                $params['QUERY_LAYERS'] = $params[$layerParam];
            }
            $params['CQL_FILTER'] = implode(';', $filters);
        }

        $base = (string) config('gis.geoserver.url');
        $workspace = (string) config('gis.geoserver.workspace');
        try {
            $response = Http::withBasicAuth((string) config('gis.geoserver.proxy_user'), (string) config('gis.geoserver.proxy_password'))
                ->timeout((int) config('gis.geoserver.timeout', 30))
                ->get("{$base}/{$workspace}/{$service}", $params);
        } catch (ConnectionException) {
            throw new HttpException(502, __('gis::gis.ogc.tidak_tersedia'));
        }

        if (in_array($request, ['getfeature', 'getfeatureinfo'], true)) {
            $this->audit->record('muat_turun', 'gis', 'ogc', $params[$layerParam] ?? $service, meta: [
                'servis' => $service, 'permintaan' => $request, 'bbox' => $params['BBOX'] ?? null, 'format' => $params['OUTPUTFORMAT'] ?? $params['INFO_FORMAT'] ?? null,
            ]);
        }

        $type = (string) $response->header('Content-Type');
        if (! collect(self::CONTENT_TYPES)->contains(fn ($t) => str_starts_with(strtolower($type), $t))) {
            $type = 'application/octet-stream';
        }
        $body = $response->body();
        if ($request === 'getcapabilities' || str_contains($type, 'xml')) {
            $body = str_replace(
                ["{$base}/{$workspace}/{$service}", "{$base}/{$workspace}/ows", "{$base}/ows", "{$base}/{$service}", htmlspecialchars("{$base}/{$workspace}/{$service}"), $base],
                [$proxyUrl, $proxyUrl, $proxyUrl, $proxyUrl, htmlspecialchars($proxyUrl), $proxyUrl],
                $body,
            );
        }

        return new Response($body, $response->status() >= 500 ? 502 : $response->status(), ['Content-Type' => $type, 'Cache-Control' => 'private, no-store']);
    }

    /** @return list<string> GeoServer layer names the user may request */
    public function published(User $user): array
    {
        $out = [];
        foreach ((array) config('gis.ogc_layers') as $key => $name) {
            $layer = app(LayerRegistry::class)->get((string) $key);
            if ($layer !== null && $this->layers->config($layer)->terbit_ogc && $this->layers->can($user, $layer)) {
                $out[] = (string) $name;
            }
        }

        return $out;
    }

    /** ECQL scope filter for the lot view (columns of the GeoServer SQL view). */
    public function cql(User $user): string
    {
        $parts = [];
        foreach (['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'] as $type => $column) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed === null) {
                continue;
            }
            $parts[] = $allowed === [] ? '1=0' : $column.' IN ('.$this->quote($allowed).')';
        }
        $levels = app(LotScope::class)->levels($user);
        $parts[] = 'klasifikasi IN ('.$this->quote($levels).')';

        return implode(' AND ', $parts);
    }

    /** @param list<string> $values */
    private function quote(array $values): string
    {
        return implode(',', array_map(fn ($v) => "'".str_replace("'", "''", preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $v) ?? '')."'", $values));
    }
}
