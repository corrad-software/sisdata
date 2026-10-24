<?php

namespace App\Modules\Rbc\Console;

use App\Modules\Rbc\Services\SecurityPolicy;
use App\Modules\Rbc\Services\SessionManager;
use Illuminate\Console\Command;

/** SRS-RBC-18: scheduled every minute so idle sessions are closed server-side. */
class ExpireIdleSessions extends Command
{
    protected $signature = 'rbc:tamat-sesi';

    protected $description = 'End sessions idle longer than the configured timeout';

    public function handle(SessionManager $sessions, SecurityPolicy $policy): int
    {
        $policy->flush();
        $this->info('Sesi ditamatkan: '.$sessions->expireIdle().'.');

        return self::SUCCESS;
    }
}
