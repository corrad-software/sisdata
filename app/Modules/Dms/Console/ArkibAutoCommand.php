<?php

namespace App\Modules\Dms\Console;

use App\Modules\Dms\Services\Lifecycle\RetentionService;
use Illuminate\Console\Command;

/** SRS-DMS-22 — scheduled daily. */
class ArkibAutoCommand extends Command
{
    protected $signature = 'dms:arkib-auto';

    protected $description = 'Arkibkan dokumen yang tempoh retensi aktifnya telah tamat';

    public function handle(RetentionService $retention): int
    {
        $count = $retention->archiveDue();
        $this->info("Dokumen diarkibkan: {$count}");

        return self::SUCCESS;
    }
}
