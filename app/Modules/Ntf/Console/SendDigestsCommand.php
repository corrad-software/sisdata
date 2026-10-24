<?php

namespace App\Modules\Ntf\Console;

use App\Modules\Ntf\Services\Scheduler;
use Illuminate\Console\Command;

class SendDigestsCommand extends Command
{
    protected $signature = 'ntf:ringkasan';

    protected $description = 'Send the periodic digest of held Biasa/Maklumat e-mail notifications (SRS-NTF-20)';

    public function handle(Scheduler $scheduler): int
    {
        $this->info('Ringkasan beratur untuk '.$scheduler->sendDigests().' pengguna.');

        return self::SUCCESS;
    }
}
