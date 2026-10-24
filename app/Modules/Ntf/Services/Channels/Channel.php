<?php

namespace App\Modules\Ntf\Services\Channels;

use App\Models\User;
use App\Modules\Ntf\Models\Mesej;

/** One delivery channel (SRS-NTF-04). Throws on failure so the job retries. */
interface Channel
{
    /**
     * Transmit $mesej to $to.
     *
     * @return bool true when the channel itself confirms receipt (gateway accepted / inbox written)
     */
    public function deliver(Mesej $mesej, User $to): bool;
}
