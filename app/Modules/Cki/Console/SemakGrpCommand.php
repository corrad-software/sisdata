<?php

namespace App\Modules\Cki\Console;

use App\Modules\Cki\Models\Transaksi;
use App\Modules\Cki\Services\BayaranService;
use Illuminate\Console\Command;

/** SRS-CKI-11: receive payment status of requests sent to GRP MAIS. */
class SemakGrpCommand extends Command
{
    protected $signature = 'cki:semak-grp';

    protected $description = 'Semak status permohonan bayaran cukai di GRP MAIS';

    public function handle(BayaranService $bayaran): int
    {
        $n = 0;
        foreach (Transaksi::query()->withoutGlobalScope('access')->where('status_transaksi', 'dihantar_grp')->get() as $t) {
            if ($bayaran->semakGrp($t)->status_transaksi !== 'dihantar_grp') {
                $n++;
            }
        }
        $this->info("{$n} transaksi dikemas kini.");

        return self::SUCCESS;
    }
}
