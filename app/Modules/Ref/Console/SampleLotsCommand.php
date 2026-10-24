<?php

namespace App\Modules\Ref\Console;

use App\Modules\Ref\Services\SampleLotGenerator;
use Illuminate\Console\Command;

/**
 * php artisan ref:sample-lots --count=500 [--seed=…] [--daerah=1008] [--kotor=0.1]
 *
 * Writes SYNTHETIC lots + titles + owners into staging_* (never lot_tanah).
 */
class SampleLotsCommand extends Command
{
    protected $signature = 'ref:sample-lots
        {--count=500 : Number of lots}
        {--seed=20261025 : Random seed; the same seed regenerates (replaces) the same rows}
        {--daerah=* : Limit to district codes, e.g. --daerah=1008 --daerah=1009}
        {--kotor=0 : Share (0–1) of lots with a non-standard no_lot format, to exercise normalisation}';

    protected $description = 'Generate SYNTHETIC sample lots, titles and owners into the staging tables';

    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));
        $generator = new SampleLotGenerator((int) $this->option('seed'));

        $result = $generator->generate($count, array_values(array_map('strval', (array) $this->option('daerah'))), (float) $this->option('kotor'));

        $this->components->info("Data SINTETIK dijana (larian {$result['larian']->id}, fail_asal {$generator->sourceFile()}).");
        $this->table(['Lot', 'Hak milik', 'Pemilik', 'Calon poligon ditolak'], [[$result['lot'], $result['hak_milik'], $result['pemilik'], $result['ditolak']]]);

        return self::SUCCESS;
    }
}
