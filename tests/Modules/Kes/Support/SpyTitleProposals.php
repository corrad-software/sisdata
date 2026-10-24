<?php

namespace Tests\Modules\Kes\Support;

use App\Models\User;
use App\Modules\Hkm\Contracts\TitleChangeProposals;

class SpyTitleProposals implements TitleChangeProposals
{
    /** @var list<array<string, mixed>> */
    public array $proposals = [];

    public function propose(array $proposal, ?User $sender = null): string
    {
        $this->proposals[] = $proposal;

        return 'cadangan-'.count($this->proposals);
    }
}
