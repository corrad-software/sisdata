<?php

namespace App\Modules\Tkt\Contracts;

use App\Models\User;

/**
 * Opens a support ticket in MOD-TKT from another surface — the in-app help
 * button (SRS-TKT-01), the public portal MOD-PWS, or an automated monitor.
 * MOD-TKT owns triage, SLA, escalation to the vendor and closure.
 */
interface TicketIntake
{
    /**
     * @param  array{tajuk: string, keterangan: string, kategori?: string, keutamaan?: string, konteks?: array<string, mixed>, emel_pelapor?: string|null}  $data
     * @param  string  $channel  aplikasi | portal | emel | pemantauan
     * @return string ticket id
     */
    public function open(array $data, string $channel, ?User $reporter = null, ?string $sourceRef = null): string;
}
