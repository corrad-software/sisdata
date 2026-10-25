<?php

namespace App\Modules\Kba\Console;

use App\Modules\Adm\Contracts\JobProgress;
use App\Modules\Kba\Services\Analitik;
use Illuminate\Console\Command;

/** SRS-KBA-18: refresh the monthly roll-up in kba_analitik. */
class AnalitikCommand extends Command
{
    protected $signature = 'kba:analitik {tempoh? : YYYY-MM (default: bulan semasa)}';

    protected $description = 'Kemas kini analitik bulanan Pangkalan Pengetahuan';

    public function handle(Analitik $analitik, JobProgress $progress): int
    {
        $tempoh = (string) ($this->argument('tempoh') ?? now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $tempoh)) {
            $this->error('Tempoh mesti YYYY-MM.');

            return self::FAILURE;
        }
        $n = $analitik->gulung($tempoh);
        $progress->processed($n);
        $this->info("{$n} soalan dinormalkan dikemas kini bagi {$tempoh}.");

        return self::SUCCESS;
    }
}
