<?php

return [
    // SRS-LAP-07/08: reporting queries run on the read replica when
    // LAP_DB_READ_HOST is set (connection `lap_baca`), else on the default
    // connection. Production: a dedicated role (e.g. sisdata_lap) that can
    // read only the `laporan` schema views.
    'baca' => [
        'host' => env('LAP_DB_READ_HOST'),
        'port' => env('LAP_DB_READ_PORT'),
        'database' => env('LAP_DB_READ_DATABASE'),
        'username' => env('LAP_DB_READ_USERNAME'),
        'password' => env('LAP_DB_READ_PASSWORD'),
    ],

    // Metabase OSS static embedding (SRS-LAP-01): signed JWT, locked params.
    'metabase' => [
        'url' => env('METABASE_SITE_URL', 'http://127.0.0.1:3030'),
        'secret' => env('METABASE_EMBED_SECRET'),
        'minit_luput' => (int) env('METABASE_EMBED_MINUTES', 10),
    ],

    // Background / scheduled export files (SRS-LAP-07).
    'disk' => env('LAP_DISK', 'local'),
];
