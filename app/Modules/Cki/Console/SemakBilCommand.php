<?php

namespace App\Modules\Cki\Console;

use App\Modules\Cki\Models\Liputan;
use App\Modules\Cki\Services\SemakanService;
use Illuminate\Console\Command;

/** SRS-CKI-05/08: daily bill & status enquiry via the central API; hourly probe of agencies in fallback mode. */
class SemakBilCommand extends Command
{
    protected $signature = 'cki:semak-bil {--agensi= : Kod agensi sahaja} {--tahun= : Tahun taksiran} {--hanya-sandaran : Hanya agensi dalam mod sandaran (semak pemulihan)}';

    protected $description = 'Semak bil dan status cukai melalui API berpusat bagi semua lot dalam liputan';

    public function handle(SemakanService $semakan): int
    {
        $tahun = $this->option('tahun') ? (int) $this->option('tahun') : null;
        if ($this->option('hanya-sandaran')) {
            $codes = Liputan::query()->where('mod_semasa', 'sandaran')->pluck('kod_agensi');
            foreach ($codes as $code) {
                $r = $semakan->jalankan((string) $code, $tahun);
                $this->line($code.': '.json_encode($r));
            }

            return self::SUCCESS;
        }
        $r = $semakan->jalankan($this->option('agensi') ? (string) $this->option('agensi') : null, $tahun);
        $this->info(sprintf('%d agensi, %d obligasi: %d baharu, %d dikemas kini, %d konflik, %d tanpa perubahan; gagal: %s',
            $r['agensi'], $r['obligasi'], $r['baharu'], $r['dikemas_kini'], $r['konflik'], $r['tanpa_ubah'], implode(', ', $r['gagal']) ?: 'tiada'));

        return self::SUCCESS;
    }
}
