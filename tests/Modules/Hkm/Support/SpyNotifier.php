<?php

namespace Tests\Modules\Hkm\Support;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;

class SpyNotifier implements Notifier
{
    /** @var list<array{type: string, to: list<int>, data: array<string, mixed>, link: string|null}> */
    public array $sent = [];

    public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void
    {
        $ids = [];
        foreach ($recipients instanceof User ? [$recipients] : $recipients as $u) {
            $ids[] = $u->id;
        }
        $this->sent[] = ['type' => $type, 'to' => $ids, 'data' => $data, 'link' => $link];
    }
}
