<?php

namespace App\Modules\Adm\Jobs;

use App\Modules\Adm\Services\Tugasan\PelaksanaTugasan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** SRS-ADM-06: "run now" from the UI — queued, same runner/lock/history as scheduled runs. */
class JalankanTugasan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Retries are handled by the runner per job config, not by the queue. */
    public int $tries = 1;

    public function __construct(public int $jobId, public int $runId, public ?int $userId) {}

    public function handle(PelaksanaTugasan $runner): void
    {
        $runner->run($this->jobId, 'manual', $this->userId, $this->runId);
    }
}
