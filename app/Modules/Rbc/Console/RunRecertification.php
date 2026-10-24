<?php

namespace App\Modules\Rbc\Console;

use App\Modules\Rbc\Services\Recertification;
use App\Modules\Rbc\Services\SecurityPolicy;
use Illuminate\Console\Command;

/** SRS-RBC-27: scheduled daily. */
class RunRecertification extends Command
{
    protected $signature = 'rbc:recert';

    protected $description = 'Close overdue access recertification cycles and open new ones per the configured interval';

    public function handle(Recertification $recert, SecurityPolicy $policy): int
    {
        $policy->flush();
        $result = $recert->runSchedule();
        $this->info("Kitaran dibuka: {$result['dibuka']}, ditutup: {$result['ditutup']}.");

        return self::SUCCESS;
    }
}
