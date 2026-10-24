<?php

/*
| MOD-ADM infrastructure settings (merged as config('adm')). Business-level,
| admin-changeable values are Parameters, not config — these are only the
| addresses of the services the health screen probes (SRS-ADM-16).
*/

$keycloak = rtrim((string) env('KEYCLOAK_BASE_URL', 'http://localhost:8180'), '/');

return [
    'kesihatan' => [
        'tamat_masa' => (float) env('ADM_KESIHATAN_TAMAT_MASA', 2),

        'http' => [
            'minio' => [
                'label' => 'MinIO (storan dokumen)',
                'url' => rtrim((string) env('DOKUMEN_S3_ENDPOINT', 'http://127.0.0.1:9000'), '/').'/minio/health/live',
            ],
            'geoserver' => [
                'label' => 'GeoServer (peta)',
                'url' => rtrim((string) env('GEOSERVER_URL', 'http://127.0.0.1:8085/geoserver'), '/').'/web/',
            ],
            'keycloak' => [
                'label' => 'Keycloak (SSO MAIS)',
                'url' => $keycloak.'/realms/'.env('KEYCLOAK_REALM', 'mais').'/.well-known/openid-configuration',
            ],
            'mailpit' => [
                'label' => 'Mailpit (e-mel)',
                'url' => rtrim((string) env('MAILPIT_UI_URL', 'http://127.0.0.1:8025'), '/').'/livez',
            ],
        ],

        'soket' => [
            'clamav' => [
                'label' => 'ClamAV (antivirus)',
                'alamat' => 'unix://'.env('CLAMAV_SOCKET', '/opt/homebrew/var/run/clamav/clamd.sock'),
                'hantar' => "zPING\0",
                'jangka' => 'PONG',
            ],
            'smtp' => [
                'label' => 'Pelayan SMTP',
                'alamat' => 'tcp://'.env('MAIL_HOST', '127.0.0.1').':'.env('MAIL_PORT', 1025),
                'jangka' => '220',
            ],
        ],
    ],

    'siem' => [
        'fail' => env('ADM_SIEM_FAIL', storage_path('logs/siem-keselamatan.log')),
    ],

    'log' => [
        // Minimum level written to adm_log_ralat.
        'aras_pangkalan_data' => env('ADM_LOG_ARAS', 'warning'),
        // Structured JSON file channel (one object per line).
        'json' => (bool) env('ADM_LOG_JSON', true),
    ],
];
