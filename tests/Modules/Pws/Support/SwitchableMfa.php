<?php

namespace Tests\Modules\Pws\Support;

use App\Models\User;
use App\Modules\Kes\Contracts\MfaAssurance;
use Illuminate\Http\Request;

class SwitchableMfa implements MfaAssurance
{
    public bool $ok = true;

    public function satisfied(User $user, ?Request $request = null): bool
    {
        return $this->ok;
    }
}
