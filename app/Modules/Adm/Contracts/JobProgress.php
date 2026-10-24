<?php

namespace App\Modules\Adm\Contracts;

/**
 * Lets an artisan command run by the ADM job scheduler report how many
 * records it processed (SRS-ADM-08). Commands call it as they go:
 *
 *     app(JobProgress::class)->processed(count($batch));
 *
 * Outside a scheduled run it is a harmless counter nobody reads.
 */
interface JobProgress
{
    public function processed(int $count = 1): void;
}
