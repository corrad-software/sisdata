<?php

namespace App\Modules\Cki\Support;

use RuntimeException;

/** Rollback marker for import dry runs. */
final class DryRun extends RuntimeException {}
