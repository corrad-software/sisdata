<?php

namespace App\Modules\Tkt\Console;

use App\Modules\Tkt\Services\SlaTiket;
use App\Modules\Tkt\Services\TiketService;
use Illuminate\Console\Command;

/** SRS-TKT-09/12: SLA warnings + escalations, verification reminders, auto-close (every 5 minutes). */
class SemakSlaCommand extends Command
{
    protected $signature = 'tkt:semak-sla';

    protected $description = 'Semak SLA tiket sokongan: amaran, eskalasi automatik, peringatan pengesahan dan tutup automatik (MOD-TKT)';

    public function handle(SlaTiket $sla, TiketService $service): int
    {
        $a = $sla->semak();
        $b = $service->automatik();
        $this->info("Amaran: {$a['amaran']}, eskalasi: {$a['eskalasi']}, peringatan pengesahan: {$b['peringatan']}, ditutup automatik: {$b['ditutup']}.");

        return self::SUCCESS;
    }
}
