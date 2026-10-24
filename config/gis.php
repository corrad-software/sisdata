<?php

/*
| MOD-GIS infrastructure settings (config('gis.*')). Tunables officers
| change live are ADM parameters (gis.*) instead.
|
| GeoServer stands in for SuperMap (PLAN §2). It is NEVER exposed to the
| browser or external tools directly: Laravel's /api/v1/gis/ogc/* proxy
| authenticates the caller, checks layer access and injects the scope as
| CQL_FILTER, then calls GeoServer with a dedicated service account that is
| the only non-admin principal allowed to read the workspace (data ACL set by
| `php artisan gis:geoserver-setup`). In production, bind GeoServer to a
| private interface / firewall it to the app servers.
*/

return [
    'geoserver' => [
        'url' => rtrim((string) env('GEOSERVER_URL', 'http://127.0.0.1:8085/geoserver'), '/'),
        'admin_user' => env('GEOSERVER_ADMIN_USER', 'admin'),
        'admin_password' => env('GEOSERVER_ADMIN_PASSWORD', 'geoserver'),
        'workspace' => env('GIS_GEOSERVER_WORKSPACE', 'sisdata'),
        'proxy_user' => env('GIS_GEOSERVER_PROXY_USER', 'sisdata_proxy'),
        'proxy_password' => env('GIS_GEOSERVER_PROXY_PASSWORD', 'sisdata_proxy_dev'),
        'timeout' => (int) env('GIS_GEOSERVER_TIMEOUT', 30),
    ],

    // SISDATA layer key => GeoServer layer name (inside the workspace) published over OGC.
    'ogc_layers' => [
        'gis.lot' => 'lot',
    ],

    'ogr2ogr' => env('GIS_OGR2OGR', 'ogr2ogr'),
    'ogrinfo' => env('GIS_OGRINFO', 'ogrinfo'),
];
