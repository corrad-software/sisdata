<?php

namespace App\Modules\Pjk\Contracts;

use RuntimeException;

/** A gateway callback whose signature (or timestamp window) failed verification. */
class InvalidGatewaySignature extends RuntimeException {}
