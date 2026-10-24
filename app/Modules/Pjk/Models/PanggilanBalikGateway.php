<?php

namespace App\Modules\Pjk\Models;

/** SRS-PJK-16: every gateway callback received (signature result, idempotency key). */
class PanggilanBalikGateway extends PjkModel
{
    public const UPDATED_AT = null;

    protected $table = 'panggilan_balik_gateway';

    protected function casts(): array
    {
        return ['muatan' => 'array', 'tandatangan_sah' => 'boolean'];
    }
}
