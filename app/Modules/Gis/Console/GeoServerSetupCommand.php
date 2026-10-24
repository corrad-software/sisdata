<?php

namespace App\Modules\Gis\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Idempotent GeoServer configuration for the OGC proxy (SRS-GIS-25/32):
 * workspace + PostGIS store on the CURRENT database connection, the lot
 * layer as a GeoServer SQL view (read-only SELECT over lot_tanah +
 * data_spatial; no DB object is created, so MOD-PRO's schema stays free),
 * an SLD style, and a data ACL so only the proxy's service account (and
 * admins) can read the workspace — anonymous WMS/WFS sees nothing.
 */
class GeoServerSetupCommand extends Command
{
    protected $signature = 'gis:geoserver-setup {--workspace= : Override config gis.geoserver.workspace} {--semak : Only check connectivity}';

    protected $description = 'Konfigurasi GeoServer (ruang kerja, stor PostGIS, lapisan lot, gaya, ACL) untuk proksi OGC MOD-GIS';

    public const LOT_SQL = "SELECT l.id::text AS id, l.no_lot, l.kod_daerah, l.kod_mukim, l.kategori_guna_tanah, l.bahagian_pemilik, l.status_lot, l.klasifikasi, l.keluasan::float8 AS keluasan, l.unit_keluasan, ds.status_pengesahan, ds.geometri AS geom FROM lot_tanah l JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa WHERE l.deleted_at IS NULL AND l.status_lot NOT IN ('arkib', 'digabung')";

    public function handle(): int
    {
        $base = (string) config('gis.geoserver.url');
        $ws = (string) ($this->option('workspace') ?: config('gis.geoserver.workspace'));
        $http = fn (): PendingRequest => Http::withBasicAuth((string) config('gis.geoserver.admin_user'), (string) config('gis.geoserver.admin_password'))
            ->acceptJson()->timeout(30)->baseUrl($base.'/rest');

        $about = $http()->get('/about/version.json');
        if (! $about->ok()) {
            $this->error("GeoServer tidak dapat dicapai di {$base} (HTTP {$about->status()}).");

            return self::FAILURE;
        }
        $version = collect((array) $about->json('about.resource'))->firstWhere('@name', 'GeoServer');
        $this->info('GeoServer dicapai: versi '.(is_array($version) ? ($version['Version'] ?? '?') : '?'));
        if ($this->option('semak')) {
            return self::SUCCESS;
        }

        if ($http()->get("/workspaces/{$ws}.json")->status() === 404) {
            $http()->post('/workspaces', ['workspace' => ['name' => $ws]])->throw();
            $this->line("Ruang kerja {$ws} dicipta.");
        }

        $db = config('database.connections.'.config('database.default'));
        $store = [
            'dataStore' => [
                'name' => 'sisdata_pg',
                'type' => 'PostGIS',
                'enabled' => true,
                'connectionParameters' => ['entry' => [
                    ['@key' => 'dbtype', '$' => 'postgis'],
                    ['@key' => 'host', '$' => (string) ($db['host'] ?? '127.0.0.1')],
                    ['@key' => 'port', '$' => (string) ($db['port'] ?? '5432')],
                    ['@key' => 'database', '$' => (string) ($db['database'] ?? '')],
                    ['@key' => 'schema', '$' => 'public'],
                    ['@key' => 'user', '$' => (string) ($db['username'] ?? '')],
                    ['@key' => 'passwd', '$' => (string) ($db['password'] ?? '')],
                    ['@key' => 'Expose primary keys', '$' => 'true'],
                    ['@key' => 'Loose bbox', '$' => 'true'],
                ]],
            ],
        ];
        if ($http()->get("/workspaces/{$ws}/datastores/sisdata_pg.json")->status() === 404) {
            $http()->post("/workspaces/{$ws}/datastores", $store)->throw();
            $this->line('Stor PostGIS sisdata_pg dicipta → '.($db['database'] ?? ''));
        } else {
            $http()->put("/workspaces/{$ws}/datastores/sisdata_pg", $store)->throw();
            $this->line('Stor PostGIS sisdata_pg dikemas kini → '.($db['database'] ?? ''));
        }

        $bbox = ['minx' => 100.7, 'maxx' => 102.0, 'miny' => 2.5, 'maxy' => 3.95, 'crs' => 'EPSG:4326'];
        $featureType = ['featureType' => [
            'name' => 'lot', 'nativeName' => 'lot', 'title' => 'Lot Tanah MAIS', 'srs' => 'EPSG:4326', 'projectionPolicy' => 'FORCE_DECLARED',
            'nativeBoundingBox' => $bbox, 'latLonBoundingBox' => $bbox, 'enabled' => true,
            'metadata' => ['entry' => [['@key' => 'JDBC_VIRTUAL_TABLE', 'virtualTable' => [
                'name' => 'lot', 'sql' => self::LOT_SQL, 'escapeSql' => false, 'keyColumn' => 'id',
                'geometry' => ['name' => 'geom', 'type' => 'MultiPolygon', 'srid' => 4326],
            ]]]],
        ]];
        if ($http()->get("/workspaces/{$ws}/datastores/sisdata_pg/featuretypes/lot.json")->status() === 404) {
            $http()->post("/workspaces/{$ws}/datastores/sisdata_pg/featuretypes", $featureType)->throw();
            $this->line('Lapisan lot (SQL view) diterbitkan.');
        } else {
            $http()->put("/workspaces/{$ws}/datastores/sisdata_pg/featuretypes/lot?recalculate=", $featureType)->throw();
            $this->line('Lapisan lot dikemas kini.');
        }

        $sld = $this->sld();
        if ($http()->get("/workspaces/{$ws}/styles/sisdata_lot.json")->status() === 404) {
            $http()->withBody($sld, 'application/vnd.ogc.sld+xml')->post("/workspaces/{$ws}/styles?name=sisdata_lot")->throw();
        } else {
            $http()->withBody($sld, 'application/vnd.ogc.sld+xml')->put("/workspaces/{$ws}/styles/sisdata_lot")->throw();
        }
        $http()->put("/layers/{$ws}:lot", ['layer' => ['defaultStyle' => ['name' => "{$ws}:sisdata_lot"]]])->throw();
        $this->line('Gaya sisdata_lot ditetapkan.');

        // Security: service account + role + data ACL closing the workspace to anonymous users.
        $user = (string) config('gis.geoserver.proxy_user');
        $role = 'ROLE_SISDATA_PROXY';
        $existing = collect((array) $http()->get('/security/usergroup/users.json')->json('users'))->pluck('userName')->all();
        if (! in_array($user, $existing, true)) {
            $http()->post('/security/usergroup/users', ['user' => ['userName' => $user, 'password' => (string) config('gis.geoserver.proxy_password'), 'enabled' => true]])->throw();
        } else {
            $http()->post("/security/usergroup/user/{$user}", ['user' => ['password' => (string) config('gis.geoserver.proxy_password'), 'enabled' => true]]);
        }
        $http()->post("/security/roles/role/{$role}");
        $http()->post("/security/roles/role/{$role}/user/{$user}");
        $rule = ["{$ws}.*.r" => "{$role},ADMIN"];
        $acl = $http()->post('/security/acl/layers', $rule);
        if ($acl->status() === 409) {
            $http()->put('/security/acl/layers', $rule)->throw();
        }
        $this->line("ACL data: {$ws}.*.r → {$role},ADMIN (tanpa nama ditolak).");

        // Verify: anonymous GetFeature must not return lot features.
        $anon = Http::timeout(15)->get("{$base}/{$ws}/wfs", ['service' => 'WFS', 'version' => '2.0.0', 'request' => 'GetFeature', 'typeNames' => "{$ws}:lot", 'count' => 1, 'outputFormat' => 'application/json']);
        $leak = $anon->ok() && is_array($anon->json('features'));
        $this->line('Semakan capaian tanpa nama: '.($leak ? 'TERDEDAH' : 'ditolak (baik)'));

        return $leak ? self::FAILURE : self::SUCCESS;
    }

    private function sld(): string
    {
        $rules = '';
        foreach (['hartanah' => '#2563EB', 'wakaf' => '#059669', 'baitulmal' => '#D97706'] as $value => $colour) {
            $rules .= <<<XML
                <Rule><Name>{$value}</Name><Title>{$value}</Title>
                  <ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>bahagian_pemilik</ogc:PropertyName><ogc:Literal>{$value}</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter>
                  <PolygonSymbolizer><Fill><CssParameter name="fill">{$colour}</CssParameter><CssParameter name="fill-opacity">0.45</CssParameter></Fill>
                  <Stroke><CssParameter name="stroke">{$colour}</CssParameter><CssParameter name="stroke-width">1.2</CssParameter></Stroke></PolygonSymbolizer>
                </Rule>
            XML;
        }

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.opengis.net/sld http://schemas.opengis.net/sld/1.0.0/StyledLayerDescriptor.xsd">
              <NamedLayer><Name>sisdata_lot</Name><UserStyle><Title>Lot Tanah MAIS (bahagian pemilik)</Title><FeatureTypeStyle>
                {$rules}
                <Rule><Name>lain</Name><ElseFilter/><PolygonSymbolizer><Fill><CssParameter name="fill">#52525B</CssParameter><CssParameter name="fill-opacity">0.4</CssParameter></Fill><Stroke><CssParameter name="stroke">#52525B</CssParameter></Stroke></PolygonSymbolizer></Rule>
              </FeatureTypeStyle></UserStyle></NamedLayer>
            </StyledLayerDescriptor>
            XML;
    }
}
