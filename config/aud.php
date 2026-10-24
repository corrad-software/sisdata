<?php

/*
| MOD-AUD (app/Modules/Aud) configuration. Values that admins may
| change at runtime (retention) live in the audit_retensi_polisi table; these
| are deployment defaults.
*/

return [

    // Production: the DB role the app connects as, when it differs from the table owner
    // (see Database/sql/peranan-pengeluaran.sql). Gets SELECT/INSERT only.
    'db_app_role' => env('AUD_DB_APP_ROLE'),

    // Local time zone recorded next to UTC (SRS-AUD-24).
    'zon_waktu_tempatan' => env('AUD_ZON_WAKTU', 'Asia/Kuala_Lumpur'),

    /*
    | Tables never audited by the global model listener: the audit store itself
    | (it is append-only and hash-chained on its own) and framework plumbing.
    */
    'jadual_dikecualikan' => [
        'audit_rekod', 'audit_perubahan_medan', 'audit_eksport', 'audit_integriti_semakan',
        'audit_arkib', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens', 'migrations', 'telescope_entries',
        'pulse_entries', 'pulse_values', 'pulse_aggregates',
    ],

    // Module code for tables of models outside app/Modules (namespace gives the rest).
    'modul_jadual' => [
        'users' => 'rbc',
        'personal_access_tokens' => 'rbc',
        'settings' => 'adm',
        'notifications' => 'ntf',
        'audit_retensi_polisi' => 'aud',
    ],

    // Approximate location from IP (SRS-AUD-19): mock until MAIS supplies a GeoIP source.
    'geoip' => env('AUD_GEOIP', 'mock'),

    // Fields never recorded as change entries (noise). Per table: 'jadual' => [medan…].
    'medan_diabaikan' => [
        '*' => ['created_at', 'updated_at'],
        'personal_access_tokens' => ['last_used_at'],
        'users' => ['remember_token'],
    ],

    /*
    | SRS-AUD-25: a field whose name matches any of these patterns is recorded
    | as changed but its values are replaced by the mask. Models add their own
    | via Auditable::auditSensitive(); a model's $hidden fields are masked too.
    */
    'corak_medan_sensitif' => [
        '/pass(word|wd)?/i', '/kata_?laluan/i', '/token/i', '/secret/i', '/rahsia_kunci/i',
        '/api_?key/i', '/kunci_?api/i', '/private_?key/i', '/kunci_?(peribadi|penyulitan|sulit)/i',
        '/encryption/i', '/^otp/i', '/totp/i', '/mfa_/i', '/two_factor/i',
        '/(^|_)no_?kp($|_)/i', '/(^|_)kp_?no($|_)/i', '/nric/i', '/mykad/i', '/(^|_)ic_?(no|number)($|_)/i',
        '/(^|_)no_?ic($|_)/i', '/pasport|passport/i', '/no_?pengenalan/i',
    ],
    'topeng' => '[DITAPIS]',

    // Header a client can use to give the "sebab perubahan" (SRS-AUD-03).
    'header_sebab' => 'X-Sebab-Perubahan',
    // Header a mobile app sends to identify its channel (SRS-AUD-04).
    'header_saluran' => 'X-Saluran',

    // SRS-AUD-20: hard floor; the policy table can only go higher.
    'retensi_minimum_bulan' => 24,

    // SRS-AUD-15 automatic reports.
    'waktu_pejabat' => [
        'mula' => env('AUD_WAKTU_MULA', '08:00'),
        'tamat' => env('AUD_WAKTU_TAMAT', '17:00'),
        // ISO-8601 day numbers (1 = Isnin … 7 = Ahad). Selangor: Isnin–Jumaat.
        'hari_bekerja' => [1, 2, 3, 4, 5],
        // Public holidays (Y-m-d), extended by MOD-ADM later.
        'cuti_umum' => [],
    ],
    'ambang_kelompok' => (int) env('AUD_AMBANG_KELOMPOK', 100), // actions per user per hour
    'ambang_eksport_besar' => (int) env('AUD_AMBANG_EKSPORT', 1000), // rows per export

    // Export / search limits.
    'had_eksport' => (int) env('AUD_HAD_EKSPORT', 50000),

    // SRS-AUD-22 SIEM streaming: null | jsonl | cef
    'siem' => [
        'pemacu' => env('AUD_SIEM', 'jsonl'),
        'laluan' => env('AUD_SIEM_LALUAN', storage_path('logs/siem/audit.jsonl')),
        // cef: write to syslog() (true) or to the file at 'laluan_cef'.
        'syslog' => (bool) env('AUD_SIEM_SYSLOG', false),
        'laluan_cef' => env('AUD_SIEM_LALUAN_CEF', storage_path('logs/siem/audit.cef')),
    ],

    // SRS-AUD-26: entries that could not be written are spooled here and replayed by aud:ulang-spool.
    'laluan_spool' => storage_path('app/aud/spool.jsonl'),
    'laluan_insiden' => storage_path('logs/aud-insiden.log'),

    'jadual' => [
        'semak_integriti' => env('AUD_CRON_INTEGRITI', '0 * * * *'),
        'arkib' => env('AUD_CRON_ARKIB', '30 2 * * *'),
        'laporan' => env('AUD_CRON_LAPORAN', '0 7 * * *'),
    ],
];
