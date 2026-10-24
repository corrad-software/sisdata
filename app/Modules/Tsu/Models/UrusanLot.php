<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $urusan_id
 * @property string $lot_id
 * @property string $peranan_lot
 * @property Carbon $tarikh_paut
 * @property-read UrusanTanah $urusan
 */
class UrusanLot extends TsuModel
{
    protected $table = 'urusan_lot';

    public $timestamps = false;

    protected $casts = ['tarikh_paut' => 'datetime'];

    /** @return BelongsTo<UrusanTanah, $this> */
    public function urusan(): BelongsTo
    {
        return $this->belongsTo(UrusanTanah::class, 'urusan_id');
    }
}
