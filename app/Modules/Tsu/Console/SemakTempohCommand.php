<?php

namespace App\Modules\Tsu\Console;

use App\Modules\Tsu\Services\Eskalasi;
use App\Modules\Tsu\Services\PtdService;
use App\Modules\Tsu\Services\UkurService;
use Illuminate\Console\Command;

/** Daily: stage escalation (SRS-TSU-03/23), survey lateness (SRS-TSU-11) and Land Office status sync for covered districts (SRS-TSU-22). */
class SemakTempohCommand extends Command
{
    protected $signature = 'tsu:semak-tempoh {--tanpa-ptd : Jangan segerak status Pejabat Tanah}';

    protected $description = 'Eskalasi peringkat urusan tanah yang lewat, semak kelewatan kerja ukur dan segerak status Pejabat Tanah (MOD-TSU)';

    public function handle(Eskalasi $eskalasi, UkurService $ukur, PtdService $ptd): int
    {
        $e = $eskalasi->run();
        $u = $ukur->checkAll();
        $p = $this->option('tanpa-ptd') ? 0 : $ptd->syncAll();
        $this->info("Eskalasi aras 1: {$e['aras_1']}, aras 2: {$e['aras_2']}; kerja ukur lewat baharu: {$u}; status PTD dikemas kini: {$p}.");

        return self::SUCCESS;
    }
}
