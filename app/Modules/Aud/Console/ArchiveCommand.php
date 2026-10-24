<?php

namespace App\Modules\Aud\Console;

use App\Modules\Aud\Services\AuditArchiver;
use Illuminate\Console\Command;

/** SRS-AUD-20: scheduled archiving of records past the online retention period. */
class ArchiveCommand extends Command
{
    protected $signature = 'aud:arkib';

    protected $description = 'Arkibkan rekod audit yang melepasi tempoh dalam talian (SRS-AUD-20)';

    public function handle(AuditArchiver $archiver): int
    {
        $result = $archiver->run();
        $this->info("{$result['dipindah']} rekod diarkibkan (lebih lama daripada {$result['bulan']} bulan, had {$result['had']}).");

        return self::SUCCESS;
    }
}
