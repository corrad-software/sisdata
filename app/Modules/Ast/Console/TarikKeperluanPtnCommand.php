<?php

namespace App\Modules\Ast\Console;

use App\Modules\Ast\Services\PenyelenggaraanService;
use Illuminate\Console\Command;

/** `php artisan ast:tarik-keperluan-ptn` — MOD-PTN maintenance needs → MOD-AST work orders (Ptn\Contracts\MaintenanceNeeds). */
class TarikKeperluanPtnCommand extends Command
{
    protected $signature = 'ast:tarik-keperluan-ptn';

    protected $description = 'Tarik keperluan penyelenggaraan daripada Pemantauan Tanah menjadi kerja penyelenggaraan aset (MOD-AST)';

    public function handle(PenyelenggaraanService $service): int
    {
        $r = $service->tarikKeperluanPtn();
        $this->components->info("{$r['dicipta']} kerja diwujudkan; {$r['perlu_dipadankan']} keperluan menunggu padanan aset.");

        return self::SUCCESS;
    }
}
