<?php

namespace App\Modules\Ptn\Console;

use App\Modules\Ptn\Services\Penjadualan;
use Illuminate\Console\Command;

/** `php artisan ptn:jana-jadual --tahun=2027` — annual risk-based schedule (SRS-PTN-02). Scheduled every 1 December for the next year. */
class JanaJadualCommand extends Command
{
    protected $signature = 'ptn:jana-jadual {--tahun= : Tahun (lalai: tahun hadapan)} {--daerah= : Kod daerah} {--semula : Jana semula jadual automatik yang belum diagih}';

    protected $description = 'Jana jadual pemantauan tahunan automatik mengikut keutamaan risiko lot (MOD-PTN)';

    public function handle(Penjadualan $p): int
    {
        $tahun = (int) ($this->option('tahun') ?: now()->addYear()->year);
        $r = $p->janaTahunan($tahun, null, $this->option('daerah') ?: null, (bool) $this->option('semula'));
        $this->components->info("Tahun {$r['tahun']}: {$r['dijana']} jadual dijana untuk {$r['lot']} lot ({$r['dilangkau']} sedia ada, {$r['dipadam']} dijana semula).");

        return self::SUCCESS;
    }
}
