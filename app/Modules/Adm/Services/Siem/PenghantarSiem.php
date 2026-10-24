<?php

namespace App\Modules\Adm\Services\Siem;

/**
 * SRS-ADM-27 adapter: ships one security event to the SIEM. MAIS's SIEM
 * product/format isn't known yet; events are ECS-style JSON (file) or CEF over
 * syslog — swap in the agreed adapter without touching callers.
 */
interface PenghantarSiem
{
    /** @param array<string, mixed> $event already redacted */
    public function hantar(array $event): void;
}
