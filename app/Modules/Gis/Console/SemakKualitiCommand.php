<?php

namespace App\Modules\Gis\Console;

use App\Modules\Gis\Services\KualitiSpatial;
use Illuminate\Console\Command;

/** Scheduled spatial QA over all lot geometries (SRS-GIS-27/28). */
class SemakKualitiCommand extends Command
{
    protected $signature = 'gis:semak-kualiti {--semakan=* : Hadkan kepada jenis semakan tertentu}';

    protected $description = 'Semak kualiti data spatial lot (geometri tidak sah, pertindihan, jurang, luar sempadan)';

    public function handle(KualitiSpatial $qa): int
    {
        $checks = $this->option('semakan');
        $result = $qa->run(null, $checks === [] ? null : array_values(array_map('strval', (array) $checks)));
        foreach ($result['dikesan'] as $check => $n) {
            $this->line(sprintf('%-20s %d', $check, $n));
        }
        $this->info("Ditutup: {$result['ditutup']} · Terbuka: {$result['jumlah_terbuka']}");

        return self::SUCCESS;
    }
}
