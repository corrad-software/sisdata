<?php

namespace App\Support\Fallbacks;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Support\Facades\Log;

/** Stand-in for Notifier until its owning module binds the real one. See App\Support\Fallbacks. */
class FallbackNotifier implements Notifier
{
    public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void
    {
        Log::info('Notifier fallback: MOD-NTF not enabled, notification dropped', ['type' => $type]);
    }
}
