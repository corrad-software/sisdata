<?php

namespace Tests\Modules\Tsu\Support;

use App\Models\User;
use App\Modules\Hkm\Contracts\TitleChangeProposals;
use App\Modules\Hkm\Contracts\TitleRegistry;

/** MOD-HKM stand-in: titles per lot and a log of proposals MOD-TSU sends. */
class FakeTitles implements TitleChangeProposals, TitleRegistry
{
    /** @var array<string, list<array{id: string, no_hak_milik: string, jenis_hak_milik: string, status_semasa: string, tarikh_tamat_tempoh: ?string}>> */
    public array $titles = [];

    /** @var list<array<string, mixed>> */
    public array $proposals = [];

    public function propose(array $proposal, ?User $sender = null): string
    {
        $this->proposals[] = $proposal;

        return 'p-'.count($this->proposals);
    }

    public function registerFromAcquisition(string $lotId, array $data, User $actor, ?array $vault = null, ?string $sourceRef = null): string
    {
        return 't-new';
    }

    public function forLot(string $lotId, User $user): array
    {
        return $this->titles[$lotId] ?? [];
    }
}
