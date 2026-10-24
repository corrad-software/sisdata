<?php

namespace Tests\Modules\Dms\Support;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;

class SpyNotifier implements Notifier
{
    /** @var list<array{type: string, recipients: list<int>, data: array<string, mixed>, link: string|null, dedupe: string|null}> */
    public array $sent = [];

    public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void
    {
        $ids = [];
        foreach ($recipients instanceof User ? [$recipients] : $recipients as $user) {
            $ids[] = $user->id;
        }
        $this->sent[] = ['type' => $type, 'recipients' => $ids, 'data' => $data, 'link' => $link, 'dedupe' => $dedupeKey];
    }

    /** @return list<array{type: string, recipients: list<int>, data: array<string, mixed>, link: string|null, dedupe: string|null}> */
    public function of(string $type): array
    {
        return array_values(array_filter($this->sent, fn ($n) => $n['type'] === $type));
    }
}
