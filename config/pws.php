<?php

/*
| MOD-PWS — Portal Hartanah Wakaf Selangor gateway (SRS-PWS-02/22/23/24).
| The portal itself is a separate WordPress system (repo folder pws/); this
| config only governs the public API SISDATA exposes to it. Tunables that
| admins change at runtime are ADM Parameters ("pws.*"), not these.
*/

return [
    // Official display name (SRS-PWS-01). "PWS" is an internal code only.
    'nama_rasmi' => 'Portal Hartanah Wakaf Selangor',

    // Browser origins allowed to call /api/v1/pws/awam/* directly (CORS). The
    // portal normally calls server-to-server, so this is only a guard.
    'asal_portal' => array_values(array_filter(array_map('trim', explode(',', (string) env('PWS_PORTAL_ORIGINS', 'http://127.0.0.1:8090,http://localhost:8090'))))),

    // Lifetime of a client-credentials access token (minutes) — "token bertempoh".
    'token_minit' => (int) env('PWS_TOKEN_MINUTES', 15),

    // After a secret rotation the previous secret keeps working this long (no outage, SRS-INT-05).
    'tempoh_tangguh_putaran_jam' => (int) env('PWS_ROTATION_GRACE_HOURS', 24),

    // Service accounts used to call MOD-PJK / MOD-PER integration endpoints
    // (seeded by database/seeders/PwsSeeder).
    'akaun_integrasi' => env('PWS_INTEGRATION_ACCOUNT', 'integrasi.portal-hws@sisdata.local'),
    'akaun_gateway_ujian' => env('PWS_GATEWAY_TEST_ACCOUNT', 'ujian.gateway-hws@sisdata.local'),

    // Mock payment-gateway simulation for the portal's test gateway page.
    // Never enable in production.
    'gateway_ujian' => (bool) env('PWS_GATEWAY_TEST', env('APP_ENV', 'production') !== 'production'),
];
