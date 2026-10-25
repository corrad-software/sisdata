<?php

namespace App\Modules\Pws\Console;

use App\Modules\Pws\Models\Permohonan;
use App\Modules\Pws\Models\SesiBayaran;
use App\Modules\Pws\Services\BayaranService;
use App\Modules\Pws\Services\PermohonanService;
use Illuminate\Console\Command;

/**
 * SRS-PWS-21 (iii): pull status changes made by officers in SISDATA for open
 * rental applications (MOD-PJK) and pending payments (server-side gateway
 * query), so the portal's change feed (/awam/permohonan/perubahan) is
 * current even when the applicant never checks. Waqf statuses are pushed by
 * MOD-PER through WakafPortal::acknowledge() and need no polling.
 */
class SegerakStatusCommand extends Command
{
    protected $signature = 'pws:segerak-status';

    protected $description = 'Segerak status permohonan sewaan dan bayaran Portal Hartanah Wakaf Selangor daripada SISDATA';

    public function handle(PermohonanService $apps, BayaranService $payments): int
    {
        $n = 0;
        Permohonan::query()->where('jenis', 'sewaan')->where('status_penghantaran', 'diterima')
            ->whereNotIn('status', ['lulus', 'tolak', 'dibatalkan'])->where('created_at', '>=', now()->subYear())
            ->orderBy('id')->chunk(100, function ($rows) use ($apps, &$n) {
                foreach ($rows as $row) {
                    $apps->refreshRental($row);
                    $n++;
                }
            });
        $p = 0;
        foreach (SesiBayaran::query()->whereNotIn('status', SesiBayaran::AKHIR)->where('created_at', '>=', now()->subDays(3))->limit(500)->get() as $s) {
            $payments->refresh($s);
            $p++;
        }
        $this->info("Status disemak: {$n} permohonan sewaan, {$p} bayaran.");

        return self::SUCCESS;
    }
}
