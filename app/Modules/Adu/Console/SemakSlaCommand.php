<?php

namespace App\Modules\Adu\Console;

use App\Modules\Adu\Services\SlaAduan;
use Illuminate\Console\Command;

/** SRS-ADU-14: update SLA states and fire the escalation path (scheduled every 15 min). */
class SemakSlaCommand extends Command
{
    protected $signature = 'adu:semak-sla';

    protected $description = 'Semak SLA aduan terbuka dan jalankan eskalasi berperingkat (MOD-ADU)';

    public function handle(SlaAduan $sla): int
    {
        $out = $sla->semak();
        $this->info("Aduan disemak: {$out['disemak']}; eskalasi dicetuskan: {$out['eskalasi']}");

        return self::SUCCESS;
    }
}
