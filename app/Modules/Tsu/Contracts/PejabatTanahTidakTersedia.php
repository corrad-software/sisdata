<?php

namespace App\Modules\Tsu\Contracts;

use RuntimeException;

/** The Land Office provider is down or does not cover the district — use the structured manual mode. */
class PejabatTanahTidakTersedia extends RuntimeException {}
