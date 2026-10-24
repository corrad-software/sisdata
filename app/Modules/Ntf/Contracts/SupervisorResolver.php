<?php

namespace App\Modules\Ntf\Contracts;

use App\Models\User;

/**
 * Who a user's unread Kritikal notifications escalate to (SRS-NTF-18). The
 * default falls back to every active user holding the role in parameter
 * `ntf.peranan_penyelia`; MOD-RBC / MOD-WFE can bind a real role-hierarchy
 * implementation.
 */
interface SupervisorResolver
{
    /**
     * @return list<User> never contains $user itself
     */
    public function supervisorsOf(User $user): array;
}
