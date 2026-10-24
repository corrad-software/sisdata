<?php

namespace App\Modules\Pro\Console;

use App\Modules\Pro\Services\PengesanDuplikasi;
use App\Modules\Pro\Services\StagingImporter;
use Illuminate\Console\Command;

/**
 * php artisan pro:import-staging [--larian=<uuid>] [--had=500] [--cuba] [--tanpa-duplikasi]
 *
 * Loads MOD-REF's staging_lot_tanah (+ geometry) into lot_tanah / data_spatial
 * with lineage (SRS-DAT-08), then runs duplicate detection. Idempotent.
 */
class ImportStagingCommand extends Command
{
    protected $signature = 'pro:import-staging
        {--larian= : Only staging rows of this migrasi_larian id}
        {--had= : Maximum rows to read}
        {--cuba : Dry run — validate mapping only, write nothing}
        {--tanpa-duplikasi : Skip duplicate detection afterwards}';

    protected $description = 'Import staging lots (MOD-REF) into the master lot register with lineage';

    public function handle(StagingImporter $importer, PengesanDuplikasi $duplicates): int
    {
        $result = $importer->run($this->option('larian') ?: null, $this->option('had') !== null ? (int) $this->option('had') : null, (bool) $this->option('cuba'));

        $this->components->info("Larian {$result['larian']}: dibaca {$result['dibaca']}, berjaya {$result['berjaya']}, dilangkau {$result['dilangkau']}, ditolak ".count($result['ditolak']).", geometri {$result['geometri']}.");
        if ($result['ditolak'] !== []) {
            $this->table(['Kunci asal', 'Sebab'], array_slice(array_map(fn ($r) => [$r['kunci_asal'], $r['sebab']], $result['ditolak']), 0, 50));
        }

        if (! $this->option('cuba') && ! $this->option('tanpa-duplikasi') && $result['berjaya'] > 0) {
            $d = $duplicates->detect();
            $this->components->info("Semakan duplikasi: {$d['diperiksa']} pasangan calon, {$d['cadangan_baharu']} cadangan baharu.");
        }

        return self::SUCCESS;
    }
}
