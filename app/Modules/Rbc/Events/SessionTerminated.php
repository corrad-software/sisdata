<?php

namespace App\Modules\Rbc\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A SISDATA session ended server-side (SRS-RBC-17..19).
 *
 * $method: log_keluar | tamat_masa | paksa | status_akaun | had_serentak | ikatan_gagal
 */
class SessionTerminated
{
    use Dispatchable;

    public function __construct(public User $user, public string $sessionRef, public string $method, public ?User $actor = null) {}
}
