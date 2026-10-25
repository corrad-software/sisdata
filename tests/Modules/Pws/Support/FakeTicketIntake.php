<?php

namespace Tests\Modules\Pws\Support;

use App\Models\User;
use App\Modules\Tkt\Contracts\TicketIntake;
use RuntimeException;

class FakeTicketIntake implements TicketIntake
{
    /** @var list<array{data: array<string, mixed>, channel: string, reporter: User|null, ref: string|null}> */
    public array $opened = [];

    public bool $enabled = true;

    public function open(array $data, string $channel, ?User $reporter = null, ?string $sourceRef = null): string
    {
        if (! $this->enabled) {
            throw new RuntimeException('MOD-TKT is not enabled: cannot open a ticket.');
        }
        $this->opened[] = ['data' => $data, 'channel' => $channel, 'reporter' => $reporter, 'ref' => $sourceRef];

        return 'TKT-2026-'.str_pad((string) count($this->opened), 5, '0', STR_PAD_LEFT);
    }
}
