<?php

namespace App\Modules\Ptn\Console;

use App\Modules\Ptn\Services\RisikoService;
use Illuminate\Console\Command;

/** `php artisan ptn:kira-risiko` — scheduled risk recomputation (SRS-PTN-12). */
class KiraRisikoCommand extends Command
{
    protected $signature = 'ptn:kira-risiko';

    protected $description = 'Kira semula skor risiko semua lot (6 kategori, MOD-PTN)';

    public function handle(RisikoService $risiko): int
    {
        $r = $risiko->kira();
        $this->components->info("{$r['lot']} lot dikira; {$r['berubah']} bertukar kod warna.");

        return self::SUCCESS;
    }
}
