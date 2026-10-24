<?php

namespace App\Modules\Adm\Contracts;

/**
 * Encrypted secret store (SRS-ADM-28). Integration credentials, API keys and
 * tokens are set by an administrator (UI with second-admin approval, or
 * `php artisan adm:secret`) and read by the module that needs them. Values are
 * encrypted at rest with the application key and never returned by any API.
 */
interface Secrets
{
    public function get(string $key): ?string;

    public function has(string $key): bool;
}
