<?php

namespace App\Modules\Kes\Contracts;

use RuntimeException;

/** An external legal system (court e-Filing, AG Chambers) is down or refused the call — fall back to manual entry. */
class CourtSystemUnavailable extends RuntimeException {}
