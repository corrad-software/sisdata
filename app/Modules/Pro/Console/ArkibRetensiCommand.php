<?php

namespace App\Modules\Pro\Console;

use App\Modules\Pro\Services\PengurusArkib;
use Illuminate\Console\Command;

/** php artisan pro:arkib-retensi — automatic archive trigger: propose records past retention (SRS-PRO-23). */
class ArkibRetensiCommand extends Command
{
    protected $signature = 'pro:arkib-retensi';

    protected $description = 'Propose lots that reached their retention period for archive approval';

    public function handle(PengurusArkib $archive): int
    {
        $this->components->info($archive->proposeDue().' cadangan arkib baharu.');

        return self::SUCCESS;
    }
}
