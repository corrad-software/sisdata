<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SRS-PJK-18/21: one reconciliation run.
 *
 * @property int $id
 * @property string $sumber
 * @property int $bil_padan
 * @property int $bil_sisihan
 * @property string $status
 */
class RekonsiliasiLarian extends PjkModel
{
    protected $table = 'rekonsiliasi_larian';

    protected function casts(): array
    {
        return ['tarikh' => 'date:Y-m-d'];
    }

    /** @return HasMany<SisihanRekonsiliasi, $this> */
    public function sisihan(): HasMany
    {
        return $this->hasMany(SisihanRekonsiliasi::class, 'larian_id');
    }
}
