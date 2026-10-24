<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS-PJK-18: one discrepancy for Finance to act on.
 *
 * @property int $id
 * @property int $larian_id
 * @property string $jenis
 * @property string $rujukan
 * @property string|null $kontrak_id
 * @property string|null $amaun_sistem
 * @property string|null $amaun_luar
 * @property string $status_tindakan
 * @property string|null $catatan
 * @property-read RekonsiliasiLarian $larian
 */
class SisihanRekonsiliasi extends PjkModel
{
    protected $table = 'sisihan_rekonsiliasi';

    /** @return BelongsTo<RekonsiliasiLarian, $this> */
    public function larian(): BelongsTo
    {
        return $this->belongsTo(RekonsiliasiLarian::class, 'larian_id');
    }
}
