<?php

namespace App\Modules\Per\Console;

use App\Modules\Per\Integration\MockWakafPortal;
use App\Modules\Per\Services\WakafIntake;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** SRS-PER-25: pull pending public waqf submissions from the portal adapter. */
class PortalWakafCommand extends Command
{
    protected $signature = 'per:portal-wakaf {--contoh=0 : (mock sahaja) baris gilir N permohonan SINTETIK dahulu}';

    protected $description = 'Tarik permohonan pendaftaran wakaf daripada Portal Hartanah Wakaf Selangor';

    public function handle(WakafIntake $intake): int
    {
        for ($i = 0, $n = (int) $this->option('contoh'); $i < $n; $i++) {
            MockWakafPortal::queue([
                'rujukan_portal' => 'PHWS-'.now()->format('Y').'-'.strtoupper(Str::random(6)),
                'nama_pewakaf' => 'Pewakaf Contoh '.($i + 1).' (SINTETIK)',
                'jenis_wakaf' => ['am', 'khas', 'irsod'][$i % 3],
                'tujuan_wakaf' => 'Tapak surau / pendidikan (SINTETIK)',
                'nilai_anggaran' => (string) (50000 * ($i + 1)),
            ]);
        }

        $this->info($intake->pull().' permohonan wakaf baharu diterima (status: menunggu semakan).');

        return self::SUCCESS;
    }
}
