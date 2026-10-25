<?php

namespace App\Modules\Lap\Console;

use App\Modules\Lap\Services\Jadual;
use Illuminate\Console\Command;

/** SRS-LAP-06: generate and distribute due scheduled reports. */
class JadualCommand extends Command
{
    protected $signature = 'lap:jadual';

    protected $description = 'Jana dan edar laporan berjadual yang telah tiba masanya (MOD-LAP)';

    public function handle(Jadual $jadual): int
    {
        $r = $jadual->runDue();
        $this->info("Jadual: {$r['jadual']}, dihantar: {$r['dihantar']}, ditolak: {$r['ditolak']}, gagal: {$r['gagal']}");

        return self::SUCCESS;
    }
}
