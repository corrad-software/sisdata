<?php

namespace App\Modules\Cki\Console;

use App\Modules\Cki\Services\RekonsiliasiService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** SRS-CKI-20 / SRS-INT-08: daily reconciliation (default: the last 30 days). */
class RekonsiliasiCommand extends Command
{
    protected $signature = 'cki:rekonsiliasi {--dari=} {--hingga=}';

    protected $description = 'Rekonsiliasi bayaran cukai antara sistem, resit dan GRP MAIS';

    public function handle(RekonsiliasiService $service): int
    {
        $hingga = $this->option('hingga') ? CarbonImmutable::parse((string) $this->option('hingga')) : CarbonImmutable::today();
        $dari = $this->option('dari') ? CarbonImmutable::parse((string) $this->option('dari')) : $hingga->subDays(30);
        try {
            $run = $service->jalankan($dari, $hingga);
        } catch (ValidationException $e) {
            $this->error(implode(' ', array_merge(...array_values($e->errors()))));

            return self::FAILURE;
        }
        $this->info("Larian #{$run->id}: {$run->bil_padan} padan, {$run->bil_sisihan} sisihan.");

        return self::SUCCESS;
    }
}
