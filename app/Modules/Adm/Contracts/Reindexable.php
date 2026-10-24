<?php

namespace App\Modules\Adm\Contracts;

/**
 * Optional on a SearchProvider that keeps its own index (tsvector column,
 * materialised view…). Admins trigger it from the search settings screen
 * (SRS-ADM-20); it runs queued. Return the number of records indexed.
 */
interface Reindexable
{
    public function reindex(): int;
}
