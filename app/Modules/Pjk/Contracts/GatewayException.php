<?php

namespace App\Modules\Pjk\Contracts;

use RuntimeException;

/** The external system is unavailable or timed out (circuit-breaker / manual fallback territory). */
class GatewayException extends RuntimeException {}
