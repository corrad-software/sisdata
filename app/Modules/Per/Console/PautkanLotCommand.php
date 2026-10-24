<?php

namespace App\Modules\Per\Console;

use App\Modules\Per\Services\LotLinker;
use Illuminate\Console\Command;

/** SRS-PER-20: scheduled hourly — re-link expected-lot cases to newly registered lots. */
class PautkanLotCommand extends Command
{
    protected $signature = 'per:pautkan-lot';

    protected $description = 'Pautkan kes lot dijangka kepada rekod lot sebenar yang telah didaftarkan';

    public function handle(LotLinker $linker): int
    {
        $this->info($linker->relinkAll().' kes dipautkan kepada lot berdaftar.');

        return self::SUCCESS;
    }
}
