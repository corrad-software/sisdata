<?php

namespace App\Modules\Rbc\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched by MOD-RBC; MOD-AUD records it, MOD-NTF may alert on it. */
class UserLoggedOut
{
    use Dispatchable;

    public function __construct(public User $user) {}
}
