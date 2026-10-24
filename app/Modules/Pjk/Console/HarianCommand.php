<?php

namespace App\Modules\Pjk\Console;

use App\Modules\Pjk\Services\Harian;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Daily lease batch: statuses, due charges, penalties, arrears notices. */
class HarianCommand extends Command
{
    protected $signature = 'pjk:harian {--tarikh= : Tarikh proses (Y-m-d), lalai hari ini}';

    protected $description = 'MOD-PJK: kemas kini status kontrak, keluarkan caj, penalti dan notis tunggakan';

    public function handle(Harian $harian): int
    {
        $today = $this->option('tarikh') ? Carbon::parse((string) $this->option('tarikh')) : Carbon::today();
        foreach ($harian->run($today) as $key => $count) {
            $this->line(str_pad($key, 20).$count);
        }

        return self::SUCCESS;
    }
}
