<?php

namespace Tests\Modules\Lap\Support;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;

class RecordingNotifier implements Notifier
{
    /** @var list<array{type: string, recipients: list<int>, data: array<string, mixed>, link: string|null}> */
    public array $sent = [];

    public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void
    {
        $ids = [];
        foreach ($recipients instanceof User ? [$recipients] : $recipients as $u) {
            $ids[] = $u->id;
        }
        $this->sent[] = ['type' => $type, 'recipients' => $ids, 'data' => $data, 'link' => $link];
    }
}
