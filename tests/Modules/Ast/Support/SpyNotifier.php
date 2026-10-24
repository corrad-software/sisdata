<?php

namespace Tests\Modules\Ast\Support;

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

    /** @return list<array{type: string, to: list<int>, data: array<string, mixed>, link: string|null}> */
    public function of(string $type): array
    {
        return array_values(array_filter($this->sent, fn ($s) => $s['type'] === $type));
    }
}
