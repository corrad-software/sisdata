<?php

namespace App\Modules\Kes\Models;

/**
 * Base for the append-only history tables (DB triggers reject UPDATE/DELETE):
 * status history, officer history, record updates, access log, chronology.
 */
abstract class Sejarah extends KesModel
{
    public const UPDATED_AT = null;
}
