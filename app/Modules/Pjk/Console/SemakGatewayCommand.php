<?php

namespace App\Modules\Pjk\Console;

use App\Modules\Pjk\Services\GatewayService;
use Illuminate\Console\Command;

/** SRS-PJK-16: scheduled status query for gateway transactions without a callback. */
class SemakGatewayCommand extends Command
{
    protected $signature = 'pjk:semak-gateway';

    protected $description = 'MOD-PJK: semak status transaksi payment gateway yang belum menerima panggilan balik';

    public function handle(GatewayService $gateway): int
    {
        $r = $gateway->pollPending();
        $this->info("Disemak: {$r['disemak']} · diselesaikan: {$r['diselesaikan']} · tamat masa: {$r['tamat_masa']}");

        return self::SUCCESS;
    }
}
