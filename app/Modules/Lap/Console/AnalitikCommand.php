<?php

namespace App\Modules\Lap\Console;

use App\Modules\Adm\Contracts\JobProgress;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Lap\Services\Analitik;
use Illuminate\Console\Command;

/** SRS-LAP-15..19: recompute rule-based analytics and recommendations. */
class AnalitikCommand extends Command
{
    protected $signature = 'lap:analitik {--jenis=* : potensi_hasil | risiko_tunggakan | tanah_tidak_produktif | potensi_pembangunan}';

    protected $description = 'Kira semula analitik MOD-LAP (potensi hasil, risiko tunggakan, tanah tidak produktif, potensi pembangunan)';

    public function handle(Analitik $analitik, AuditLogger $audit, JobProgress $progress): int
    {
        $only = $this->option('jenis') ?: null;
        $counts = $analitik->run($only);
        $progress->processed(array_sum($counts));
        $audit->record('jana', 'lap', 'lap_hasil_analitik', null, meta: ['analitik' => $counts, 'versi_model' => Analitik::VERSI, 'sumber' => 'berjadual']);
        foreach ($counts as $k => $n) {
            $this->line("{$k}: {$n}");
        }

        return self::SUCCESS;
    }
}
