<?php

namespace App\Modules\Cki\Console;

use App\Modules\Cki\Services\LiputanService;
use Illuminate\Console\Command;

/** SRS-CKI-06: seed the coverage register from MOD-REF (one row per PTD daerah and per PBT). Idempotent. */
class LiputanLalaiCommand extends Command
{
    protected $signature = 'cki:liputan-lalai';

    protected $description = 'Benihkan daftar liputan integrasi daripada rujukan daerah dan PBT';

    public function handle(LiputanService $liputan): int
    {
        $this->info($liputan->lalai().' agensi ditambah.');

        return self::SUCCESS;
    }
}
