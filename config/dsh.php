<?php

return [
    // SRS-DSH-13: dashboard analytics read from a replica when configured.
    'baca' => [
        'host' => env('DSH_DB_READ_HOST'),
        'port' => env('DSH_DB_READ_PORT'),
        'username' => env('DSH_DB_READ_USERNAME'),
        'password' => env('DSH_DB_READ_PASSWORD'),
    ],

    // SRS-DSH-18: AI engine — rule-based by default; "ollama" only with a
    // loopback / private-network URL (a public host is refused).
    'ai' => [
        'pemacu' => env('DSH_AI_PEMACU', 'peraturan'),
        'ollama_url' => env('DSH_AI_OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('DSH_AI_MODEL', 'llama3.2'),
        'timeout' => (int) env('DSH_AI_TIMEOUT', 20),
    ],
];
