<?php

namespace App\Modules\Kes\Console;

use App\Modules\Kes\Services\Eskalasi;
use Illuminate\Console\Command;

/** SRS-KES-11: daily escalation of cases with no next proceeding recorded. */
class SemakEskalasiCommand extends Command
{
    protected $signature = 'kes:semak-eskalasi';

    protected $description = 'Eskalasi kes tanpa tarikh prosiding berikutnya kepada Ketua Bahagian Perundangan (MOD-KES)';

    public function handle(Eskalasi $eskalasi): int
    {
        $n = $eskalasi->jalankan();
        $this->info("{$n} kes dieskalasikan.");

        return self::SUCCESS;
    }
}
