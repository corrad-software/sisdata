<?php

namespace App\Modules\Adm\Contracts;

use App\Models\User;

/**
 * Optional on a SearchProvider: resolves one record id to its frontend path
 * for drill-down from dashboards and search results (SRS-ADM-22). Return null
 * when the record does not exist OR the user may not open it — callers can't
 * tell the two apart, by design.
 */
interface DeepLinkProvider
{
    public function link(string $id, User $user): ?string;
}
