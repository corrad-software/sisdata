<?php

namespace App\Modules\Ntf\Console;

use App\Modules\Ntf\Services\Scheduler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RemindersCommand extends Command
{
    protected $signature = 'ntf:peringatan {--tarikh= : Run as if today were this date (Y-m-d)}';

    protected $description = 'Run every registered scheduled reminder source (SRS-NTF-07)';

    public function handle(Scheduler $scheduler): int
    {
        $date = $this->option('tarikh');
        $sent = $scheduler->runReminders($date ? CarbonImmutable::parse((string) $date)->startOfDay() : null);
        $this->info("Peringatan dihantar kepada {$sent} penerima.");

        return self::SUCCESS;
    }
}
