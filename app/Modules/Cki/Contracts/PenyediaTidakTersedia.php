<?php

namespace App\Modules\Cki\Contracts;

use RuntimeException;

/** The central provider (or GRP) is unavailable or timed out — fallback-mode territory (SRS-CKI-08). */
class PenyediaTidakTersedia extends RuntimeException
{
    public function __construct(string $message = '', public readonly ?int $httpStatus = null, public readonly ?string $kodRalat = null)
    {
        parent::__construct($message);
    }
}
