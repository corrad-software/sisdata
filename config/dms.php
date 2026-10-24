<?php

/*
| MOD-DMS infrastructure settings (deploy-time). Business limits that
| administrators change at runtime (whitelist, size limits, quota, expiry
| warnings…) are ADM Parameters declared in DmsServiceProvider, not here.
*/

return [
    'disk' => env('DMS_DISK', 'dokumen'),

    // SRS-DMS-20: key-encryption key for the per-file data keys (AES-256-GCM),
    // "base64:<32 bytes>". Empty → derived from APP_KEY with HKDF (dev only;
    // production must set a dedicated key, injected from the secrets vault).
    'encryption_key' => env('DMS_ENCRYPTION_KEY'),

    // SRS-DMS-07: clamd | fake (fake = EICAR-string detector for tests/dev without clamd).
    'scanner' => env('DMS_SCANNER', 'clamd'),
    'clamav' => [
        'socket' => env('CLAMAV_SOCKET', '/opt/homebrew/var/run/clamav/clamd.sock'),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 60),
    ],

    // SRS-DMS-12: text extraction / OCR binaries (poppler-utils + tesseract).
    'binaries' => [
        'pdftotext' => env('DMS_PDFTOTEXT', 'pdftotext'),
        'pdftoppm' => env('DMS_PDFTOPPM', 'pdftoppm'),
        'tesseract' => env('DMS_TESSERACT', 'tesseract'),
    ],
    'ocr_max_pages' => (int) env('DMS_OCR_MAX_PAGES', 30),
    'preview_max_pages' => (int) env('DMS_PREVIEW_MAX_PAGES', 50),
    'text_max_chars' => 500000,
];
