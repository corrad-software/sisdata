<?php

namespace App\Modules\Pws\Console;

use App\Modules\Pws\Services\PermohonanService;
use Illuminate\Console\Command;

/**
 * SRS-PWS-22: retry portal submissions that have not reached SISDATA's
 * owning module yet (PER for wakaf, PJK for sewaan — both idempotent on the
 * PHWS reference). Gives up after 10 attempts and notifies (pws.penghantaran_gagal).
 */
class CubaSemulaCommand extends Command
{
    protected $signature = 'pws:cuba-semula';

    protected $description = 'Cuba semula penghantaran permohonan Portal Hartanah Wakaf Selangor yang gagal';

    public function handle(PermohonanService $service): int
    {
        $r = $service->retryFailed();
        $this->info("Dihantar semula: {$r['wakaf']} wakaf, {$r['sewaan']} sewaan; masih gagal: {$r['gagal']}.");

        return self::SUCCESS;
    }
}
