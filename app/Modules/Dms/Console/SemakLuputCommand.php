<?php

namespace App\Modules\Dms\Console;

use App\Modules\Dms\Services\Lifecycle\RetentionService;
use Illuminate\Console\Command;

/** SRS-DMS-15 — scheduled daily. */
class SemakLuputCommand extends Command
{
    protected $signature = 'dms:semak-luput';

    protected $description = 'Tandakan dokumen yang telah luput dan hantar peringatan sebelum tarikh luput';

    public function handle(RetentionService $retention): int
    {
        $result = $retention->processExpiry();
        $this->info("Tamat: {$result['tamat']}, peringatan dihantar: {$result['peringatan']}");

        return self::SUCCESS;
    }
}
