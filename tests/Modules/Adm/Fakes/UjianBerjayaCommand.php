<?php

namespace Tests\Modules\Adm\Fakes;

use App\Modules\Adm\Contracts\JobProgress;
use Illuminate\Console\Command;

/** A module command run by the ADM scheduler that reports records processed. */
class UjianBerjayaCommand extends Command
{
    protected $signature = 'adm:ujian-berjaya';

    public function handle(JobProgress $progress): int
    {
        $progress->processed(40);
        $progress->processed(2);
        $this->info('siap');

        return self::SUCCESS;
    }
}
