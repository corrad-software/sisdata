<?php

namespace App\Modules\Kba\Jobs;

use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\PengurusSumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * SRS-KBA-02: incremental indexing of one DMS document version. DMS extracts
 * text / OCR in its own queued job; while that is still "menunggu" this job
 * re-queues itself instead of indexing an empty document.
 */
class IndeksDokumenDms implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 1800;

    public function __construct(public string $documentId, public int $version) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600, 900, 1800];
    }

    public function handle(PengurusSumber $pengurus): void
    {
        $sumber = SumberDokumen::query()->where('kod', 'dms:'.$this->documentId)->first() ?? $pengurus->daftarDms($this->documentId);
        if ($sumber === null) {
            return;
        }
        $result = $pengurus->indeksDms($sumber, $this->version > 0 ? $this->version : null); // 0 = whatever is current
        if ($result['status'] === 'menunggu' && $this->attempts() < $this->tries && $this->job !== null) {
            $this->release($this->backoff()[min($this->attempts() - 1, 6)]);
        }
    }
}
