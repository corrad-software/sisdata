<?php

namespace App\Modules\Pro\Console;

use App\Modules\Pro\Services\PengesanDuplikasi;
use Illuminate\Console\Command;

/** php artisan pro:kesan-duplikasi — re-run duplicate detection over the register (SRS-PRO-20). */
class KesanDuplikasiCommand extends Command
{
    protected $signature = 'pro:kesan-duplikasi';

    protected $description = 'Detect possible duplicate lots and queue suggestions for stewards';

    public function handle(PengesanDuplikasi $detector): int
    {
        $r = $detector->detect();
        $this->components->info("{$r['diperiksa']} pasangan calon, {$r['cadangan_baharu']} cadangan baharu.");

        return self::SUCCESS;
    }
}
