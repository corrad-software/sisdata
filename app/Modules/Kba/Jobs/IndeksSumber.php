<?php

namespace App\Modules\Kba\Jobs;

use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\PengurusSumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Re-index one source (admin "indeks semula", SRS-KBA-02). Queued — embedding a long manual takes a while. */
class IndeksSumber implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 1800;

    public function __construct(public int $idSumber, public bool $paksa = true) {}

    public function handle(PengurusSumber $pengurus): void
    {
        $sumber = SumberDokumen::query()->find($this->idSumber);
        if ($sumber !== null && $sumber->aktif) {
            $pengurus->indeksSemula($sumber, $this->paksa);
        }
    }
}
