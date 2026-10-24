<?php

namespace App\Modules\Dsh\Console;

use App\Modules\Dsh\Services\Ringkasan;
use Illuminate\Console\Command;

/** Scheduled KPI snapshot + e-mail digest for every due subscription (via MOD-NTF). */
class RingkasanBerkalaCommand extends Command
{
    protected $signature = 'dsh:ringkasan-berkala';

    protected $description = 'Jana snapshot KPI dan hantar ringkasan dashboard berkala kepada pelanggan';

    public function handle(Ringkasan $ringkasan): int
    {
        $sent = $ringkasan->sendDue();
        $this->info("Ringkasan dihantar: {$sent}");

        return self::SUCCESS;
    }
}
