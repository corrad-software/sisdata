<?php

namespace App\Modules\Wfe\Console;

use App\Modules\Wfe\Services\SlaMonitor;
use Illuminate\Console\Command;

/** SRS-WFE-06..08: scheduled SLA check (every 15 minutes, see WfeServiceProvider). */
class SemakSla extends Command
{
    protected $signature = 'wfe:semak-sla';

    protected $description = 'Semak SLA tugasan workflow: sambung tangguhan, amaran SLA dan eskalasi berperingkat';

    public function handle(SlaMonitor $monitor): int
    {
        $result = $monitor->run();
        $this->info(sprintf('Disambung: %d, amaran: %d, eskalasi: %d', $result['disambung'], $result['amaran'], $result['eskalasi']));

        return self::SUCCESS;
    }
}
