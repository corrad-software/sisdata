<?php

namespace App\Modules\Ntf\Console;

use App\Modules\Ntf\Services\Scheduler;
use Illuminate\Console\Command;

class RequeueCommand extends Command
{
    protected $signature = 'ntf:proses-tertunggak {--minit=10 : Re-queue deliveries waiting longer than this}';

    protected $description = 'Re-queue deliveries the queue lost, e.g. while Redis was down (SRS-NTF-21)';

    public function handle(Scheduler $scheduler): int
    {
        $this->info('Dimasukkan semula: '.$scheduler->requeueStale((int) $this->option('minit')));

        return self::SUCCESS;
    }
}
