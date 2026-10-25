<?php

namespace App\Modules\Tkt\Console;

use App\Modules\Tkt\Services\KalendarSokongan;
use App\Modules\Tkt\Services\LaporanBulananService;
use Illuminate\Console\Command;

/** SRS-TKT-19: generate + file the monthly support report (default: previous month). Scheduled on the 1st at 07:00. */
class LaporanBulananCommand extends Command
{
    protected $signature = 'tkt:laporan-bulanan {--bulan= : YYYY-MM (lalai: bulan lepas)}';

    protected $description = 'Jana laporan sokongan bulanan (PDF + Excel ke DMS) daripada data tiket (MOD-TKT)';

    public function handle(LaporanBulananService $service): int
    {
        $bulan = (string) ($this->option('bulan') ?: now()->setTimezone(KalendarSokongan::TZ)->subMonthNoOverflow()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $this->error('Format bulan mesti YYYY-MM.');

            return self::FAILURE;
        }
        $row = $service->jana($bulan);
        $this->info("Laporan {$bulan} dijana".($row->ralat ? " (amaran: {$row->ralat})" : '').'.');

        return self::SUCCESS;
    }
}
