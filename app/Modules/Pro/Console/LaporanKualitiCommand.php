<?php

namespace App\Modules\Pro\Console;

use App\Modules\Pro\Services\LaporanKualiti;
use App\Modules\Pro\Services\PenghalaKualiti;
use Illuminate\Console\Command;

/** php artisan pro:laporan-kualiti — scheduled data-quality run; routes problem records to stewards (SRS-PRO-12, SRS-PRO-24). */
class LaporanKualitiCommand extends Command
{
    protected $signature = 'pro:laporan-kualiti';

    protected $description = 'Build the data-quality report and route problem records to data stewards';

    public function handle(LaporanKualiti $report, PenghalaKualiti $router): int
    {
        $created = $report->routeAll($router);
        $this->table(['Kategori', 'Tugasan baharu'], array_map(fn ($k, $v) => [$k, $v], array_keys($created), $created));

        return self::SUCCESS;
    }
}
