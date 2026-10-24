<?php

namespace App\Modules\Wfe\Support;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A workflow rule refused the action (segregation of duties, MFA needed,
 * wrong state…). The message is user-safe Malay; `code` lets the frontend
 * react (e.g. "mfa_diperlukan" → offer the step-up). Rendered in the common
 * API error shape (SRS-ADM-13).
 */
class WfeException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'code' => strtoupper($this->errorCode),
            'request_id' => AssignRequestId::current(),
        ], $this->status);
    }
}
