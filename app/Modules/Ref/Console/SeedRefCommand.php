<?php

namespace App\Modules\Ref\Console;

use App\Modules\Ref\Database\seeders\RefSeeder;
use Illuminate\Console\Command;

/**
 * Loads / refreshes the ref_* tables from the committed data files.
 * Safe to run repeatedly (idempotent, versioned — SRS-DAT-05).
 */
class SeedRefCommand extends Command
{
    protected $signature = 'ref:seed {--tanpa-spatial : Only code lists, skip boundaries and POIs}';

    protected $description = 'Load reference data (negeri, daerah, mukim, PBT, code lists, boundaries, masjid/surau) into ref_* tables';

    public function handle(RefSeeder $seeder): int
    {
        $seeder->withSpatial = ! $this->option('tanpa-spatial');
        $seeder->setContainer($this->laravel)->setCommand($this);
        $seeder->__invoke();

        $rows = [];
        foreach ($seeder->stats as $table => $stats) {
            $rows[] = [$table, collect($stats)->map(fn ($n, $k) => "{$k}={$n}")->implode(' ')];
        }
        $this->table(['Jadual', 'Keputusan'], $rows);

        return self::SUCCESS;
    }
}
