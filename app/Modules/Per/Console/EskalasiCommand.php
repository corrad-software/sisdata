<?php

namespace App\Modules\Per\Console;

use App\Modules\Per\Services\Escalator;
use Illuminate\Console\Command;

/** SRS-PER-26: scheduled daily. */
class EskalasiCommand extends Command
{
    protected $signature = 'per:eskalasi';

    protected $description = 'Eskalasi kes pemerolehan yang melebihi tempoh sasaran peringkat';

    public function handle(Escalator $escalator): int
    {
        $this->info($escalator->run().' peringkat kes dieskalasi.');

        return self::SUCCESS;
    }
}
