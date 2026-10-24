<?php

namespace App\Modules\Ast\Console;

use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Services\PenyelenggaraanService;
use Illuminate\Console\Command;

/** `php artisan ast:jana-jadual` — create missing preventive schedules from each asset's category cycle (e.g. after a category change). */
class JanaJadualCommand extends Command
{
    protected $signature = 'ast:jana-jadual';

    protected $description = 'Jana jadual penyelenggaraan berkala yang belum wujud mengikut kitaran kategori aset (MOD-AST)';

    public function handle(PenyelenggaraanService $service): int
    {
        $n = 0;
        AsetFizikal::query()->withoutGlobalScope('access')->where('status_arkib', false)->orderBy('id')
            ->chunkById(500, function ($rows) use ($service, &$n) {
                foreach ($rows as $a) {
                    $n += $service->janaJadualUntuk($a);
                }
            });
        $this->components->info("{$n} jadual penyelenggaraan dijana.");

        return self::SUCCESS;
    }
}
