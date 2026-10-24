<?php

namespace App\Modules\Rbc\Console;

use App\Modules\Rbc\Services\AccountStatusService;
use App\Modules\Rbc\Services\SecurityPolicy;
use Illuminate\Console\Command;

/** SRS-RBC-16: scheduled hourly. */
class CheckAccountStatuses extends Command
{
    protected $signature = 'rbc:semak-status';

    protected $description = 'Expire accounts past their access end date and suspend accounts inactive beyond the configured days';

    public function handle(AccountStatusService $statuses, SecurityPolicy $policy): int
    {
        $policy->flush();
        $result = $statuses->sweep();
        $this->info("Tamat tempoh: {$result['tamat_tempoh']}, digantung: {$result['gantung']}.");

        return self::SUCCESS;
    }
}
