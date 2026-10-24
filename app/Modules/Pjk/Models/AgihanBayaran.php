<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS-PJK-20: how much of a payment went to which charge.
 *
 * @property int $id
 * @property int $bayaran_id
 * @property int $caj_id
 * @property string $amaun
 * @property bool $dibalikkan
 * @property-read JadualCaj $caj
 */
class AgihanBayaran extends PjkModel
{
    public const UPDATED_AT = null;

    protected $table = 'agihan_bayaran';

    protected function casts(): array
    {
        return ['dibalikkan' => 'boolean'];
    }

    /** @return BelongsTo<JadualCaj, $this> */
    public function caj(): BelongsTo
    {
        return $this->belongsTo(JadualCaj::class, 'caj_id');
    }
}
