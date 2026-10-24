<?php

namespace Tests\Modules\Kes\Support;

use App\Models\User;
use App\Modules\Kes\Contracts\MfaAssurance;
use Illuminate\Http\Request;

class FakeMfa implements MfaAssurance
{
    public bool $ok = true;

    public function satisfied(User $user, ?Request $request = null): bool
    {
        return $this->ok;
    }
}
