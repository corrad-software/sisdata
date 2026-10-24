<?php

namespace App\Modules\Pjk\Console;

use App\Modules\Pjk\Services\RekonsiliasiService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** SRS-PJK-18/21: daily reconciliation (default: yesterday). */
class RekonsiliasiCommand extends Command
{
    protected $signature = 'pjk:rekonsiliasi {--sumber=semua : gateway | grp | esewa | semua} {--tarikh= : Y-m-d, lalai semalam}';

    protected $description = 'MOD-PJK: rekonsiliasi bayaran dengan payment gateway, GRP MAIS dan e-Sewa';

    public function handle(RekonsiliasiService $service): int
    {
        $date = $this->option('tarikh') ? CarbonImmutable::parse((string) $this->option('tarikh')) : CarbonImmutable::yesterday();
        $source = (string) $this->option('sumber');
        $sources = $source === 'semua' ? RekonsiliasiService::SUMBER : [$source];
        foreach ($sources as $s) {
            if (! in_array($s, RekonsiliasiService::SUMBER, true)) {
                $this->error("Sumber tidak sah: {$s}");

                return self::FAILURE;
            }
            $run = $service->run($s, $date);
            $this->line(sprintf('%-8s %s  padan=%d  sisihan=%d  %s', $s, $date->format('Y-m-d'), $run->bil_padan, $run->bil_sisihan, $run->status));
        }

        return self::SUCCESS;
    }
}
