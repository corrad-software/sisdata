<?php

namespace App\Modules\Rbc\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * SRS-RBC-22: suspicious login / access activity. Dispatched by MOD-RBC
 * (also stored in amaran_keselamatan); MOD-NTF alerts Pentadbir Sistem,
 * MOD-AUD may record it.
 *
 * $type: gagal_berulang | peranti_baharu | lokasi_baharu | capaian_tanpa_kebenaran
 */
class SuspiciousActivityDetected
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(public string $type, public ?User $user, public ?string $ip, public array $details = []) {}
}
