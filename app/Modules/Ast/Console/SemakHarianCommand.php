<?php

namespace App\Modules\Ast\Console;

use App\Modules\Ast\Services\PenyelenggaraanService;
use Illuminate\Console\Command;

/** `php artisan ast:semak-harian` — work orders for schedules inside their warning period, and escalation of overdue ones (SRS-AST-15/16). */
class SemakHarianCommand extends Command
{
    protected $signature = 'ast:semak-harian';

    protected $description = 'Jana kerja penyelenggaraan berjadual dan eskalasi penyelenggaraan tertunggak (MOD-AST)';

    public function handle(PenyelenggaraanService $service): int
    {
        $n = $service->janaKerjaJadual();
        $r = $service->eskalasiTertunggak();
        $this->components->info("{$n} kerja berjadual dijana; {$r['tertunggak']} jadual tertunggak, {$r['dimaklumkan']} eskalasi dihantar.");

        return self::SUCCESS;
    }
}
