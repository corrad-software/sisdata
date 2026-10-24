<?php

namespace App\Modules\Ntf\Services\Channels;

use App\Models\User;
use App\Modules\Ntf\Models\Mesej;
use App\Modules\Ntf\Models\StatusPengguna;

/**
 * "Dalam sistem": writes the user's inbox item. Receipt (terima) is stamped
 * later, when the user's client first fetches it.
 */
class InAppChannel implements Channel
{
    public function deliver(Mesej $mesej, User $to): bool
    {
        StatusPengguna::query()->firstOrCreate(['mesej_id' => $mesej->id, 'pengguna_id' => $to->id]);

        return false;
    }
}
