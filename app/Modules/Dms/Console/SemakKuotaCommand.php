<?php

namespace App\Modules\Dms\Console;

use App\Modules\Dms\Services\Lifecycle\QuotaService;
use Illuminate\Console\Command;

/** SRS-DMS-25 — scheduled hourly (also checked after every upload). */
class SemakKuotaCommand extends Command
{
    protected $signature = 'dms:semak-kuota';

    protected $description = 'Semak penggunaan storan dokumen dan beri amaran jika melebihi ambang';

    public function handle(QuotaService $quota): int
    {
        $summary = $quota->summary();
        $alerted = $quota->checkAndAlert();
        $this->info(sprintf('Penggunaan: %.2f%%%s', $summary['peratus'], $alerted ? ' — amaran dihantar' : ''));

        return self::SUCCESS;
    }
}
