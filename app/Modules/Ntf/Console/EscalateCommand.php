<?php

namespace App\Modules\Ntf\Console;

use App\Modules\Ntf\Services\Scheduler;
use Illuminate\Console\Command;

class EscalateCommand extends Command
{
    protected $signature = 'ntf:eskalasi';

    protected $description = 'Escalate unread Kritikal notifications past their escalation period (SRS-NTF-18)';

    public function handle(Scheduler $scheduler): int
    {
        $this->info('Dieskalasi: '.$scheduler->escalate());

        return self::SUCCESS;
    }
}
