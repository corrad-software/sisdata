<?php

/*
| MOD-KBA · local AI only (SRS-KBA-15, SRS-DSH-18). Both endpoints must be
| loopback / private-network addresses; a public host is refused and the
| module falls back (hashing embedder / extractive answers). Under PHPUnit
| the defaults never touch a model — tests bind deterministic fakes.
*/

$testing = env('APP_ENV') === 'testing';

return [
    'embedding' => [
        // ollama | cincangan (deterministic hashing, no model)
        'pemacu' => env('KBA_EMBEDDING_PEMACU', $testing ? 'cincangan' : 'ollama'),
        'url' => env('KBA_OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('KBA_EMBEDDING_MODEL', 'nomic-embed-text'),
        'dimensi' => 768,
        'timeout' => (int) env('KBA_EMBEDDING_TIMEOUT', 60),
        'saiz_kelompok' => 16,
    ],

    'llm' => [
        // ollama | tiada (extractive answers only)
        'pemacu' => env('KBA_LLM_PEMACU', $testing ? 'tiada' : 'ollama'),
        'url' => env('KBA_OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('KBA_LLM_MODEL', 'qwen2.5:1.5b'),
        'timeout' => (int) env('KBA_LLM_TIMEOUT', 90),
        'konteks' => (int) env('KBA_LLM_KONTEKS', 4096),
    ],

    // Project baseline documents indexed by `php artisan kba:index-baseline`
    // (SRS-KBA-01). CLAUDE.md and developer notes are NOT user documents.
    'baseline' => [
        ['laluan' => 'docs/srs/SRS-SISDATA.md', 'tajuk' => 'Spesifikasi Keperluan Sistem (SRS) SISDATA', 'jenis' => 'SRS', 'klasifikasi' => 'dalaman',
            // Security design is classified (SRS-KBA-08): only roles allowed to read Sulit see it.
            'peraturan_seksyen' => [['padanan' => '13. Keperluan Keselamatan', 'klasifikasi' => 'sulit', 'peranan' => []]]],
        ['laluan' => 'docs/PLAN-SISDATA.md', 'tajuk' => 'Pelan Pembangunan SISDATA', 'jenis' => 'reka_bentuk', 'klasifikasi' => 'dalaman'],
        ['laluan' => 'docs/data/erd.md', 'tajuk' => 'Rajah Hubungan Entiti (ERD) SISDATA', 'jenis' => 'SDS', 'klasifikasi' => 'dalaman'],
        ['laluan' => 'docs/data/justifikasi-pangkalan-data.md', 'tajuk' => 'Justifikasi Pangkalan Data SISDATA', 'jenis' => 'SDS', 'klasifikasi' => 'dalaman'],
        ['laluan' => 'docs/data/kamus-data.md', 'tajuk' => 'Kamus Data SISDATA', 'jenis' => 'SDS', 'klasifikasi' => 'dalaman'],
        ['laluan' => 'docs/data/model-fizikal.md', 'tajuk' => 'Model Data Fizikal SISDATA', 'jenis' => 'SDS', 'klasifikasi' => 'dalaman'],
    ],
];
