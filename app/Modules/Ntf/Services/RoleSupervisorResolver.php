<?php

namespace App\Modules\Ntf\Services;

use App\Models\User;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ntf\Contracts\SupervisorResolver;

/**
 * Fallback supervisor resolution: everyone holding the role configured in
 * `ntf.peranan_penyelia`. Replace with a hierarchy-aware binding when
 * MOD-RBC/WFE provide one.
 */
class RoleSupervisorResolver implements SupervisorResolver
{
    public function __construct(private RecipientDirectory $directory, private Settings $settings) {}

    public function supervisorsOf(User $user): array
    {
        $role = (string) $this->settings->get('ntf.peranan_penyelia');

        return array_values(array_filter(
            $this->directory->resolve(RecipientDirectory::PERANAN, $role),
            fn (User $candidate) => $candidate->id !== $user->id,
        ));
    }
}
