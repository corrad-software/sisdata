<?php

namespace App\Modules\Dms\Contracts;

use RuntimeException;

/** Upload refused (type, content mismatch, size, virus). getMessage() is user-safe Malay. */
class DocumentRejected extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
