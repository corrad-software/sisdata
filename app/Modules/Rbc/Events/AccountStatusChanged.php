<?php

namespace App\Modules\Rbc\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched by MOD-RBC; MOD-AUD records it, MOD-NTF may alert on it. */
class AccountStatusChanged
{
    use Dispatchable;

    public function __construct(public User $user, public string $from, public string $to, public ?string $reason = null) {}
}
