<?php

namespace App\Modules\Adm\Console;

use App\Modules\Adm\Contracts\JobProgress;
use App\Modules\Adm\Services\Kesihatan\PemantauKesihatan;
use App\Modules\Adm\Services\Kesihatan\Semakan;
use Illuminate\Console\Command;

/** SRS-ADM-16/17: scheduled health snapshot + threshold alerts to Bahagian ICT. */
class SemakKesihatan extends Command
{
    protected $signature = 'adm:semak-kesihatan';

    protected $description = 'Check every service and integration, store a snapshot, alert ICT on thresholds';

    public function handle(PemantauKesihatan $monitor, JobProgress $progress): int
    {
        $results = $monitor->checkAndAlert();
        $progress->processed(count($results));

        foreach ($results as $r) {
            $this->line(sprintf('%-16s %-7s %5dms %s', $r['kod'], $r['status'], $r['masa_ms'], (string) $r['mesej']));
        }

        $down = count(array_filter($results, fn ($r) => $r['status'] === Semakan::GAGAL));
        $this->info("Down: {$down}");

        // A failing dependency is reported through ICT alerts; the check job itself succeeded.
        return self::SUCCESS;
    }
}
