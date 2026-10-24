<?php

namespace App\Modules\Cki\Console;

use App\Modules\Cki\Services\Harian;
use Illuminate\Console\Command;

/** SRS-CKI-18: overdue status, penalties and out-of-coverage alerts. */
class HarianCommand extends Command
{
    protected $signature = 'cki:harian';

    protected $description = 'Kemas kini status tertunggak, kenakan denda dan jana amaran cukai';

    public function handle(Harian $harian): int
    {
        $r = $harian->jalankan();
        $this->info("Tertunggak baharu: {$r['tertunggak']}; denda: {$r['denda']}; obligasi luar liputan tanpa bil: {$r['luar_liputan']}");

        return self::SUCCESS;
    }
}
