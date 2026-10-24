<?php

namespace App\Modules\Rbc\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user's role assignments changed (admin, SSO claim mapping or
 * recertification). Pivot changes are invisible to model observers, so
 * MOD-AUD should listen to this to record "perubahan peranan".
 *
 * $source: manual | sso | recert
 */
class UserRolesChanged
{
    use Dispatchable;

    /**
     * @param  list<string>  $added  role codes
     * @param  list<string>  $removed  role codes
     */
    public function __construct(public User $user, public array $added, public array $removed, public string $source, public ?User $actor = null) {}
}
