<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TSU-22: Land Office status history (API or structured manual; append-only).
 *
 * @property int $id
 * @property string $urusan_id
 * @property string $sumber
 * @property string $status_ptd
 * @property Carbon $tarikh_status
 * @property string|null $catatan
 * @property string|null $rujukan_ptd
 * @property Carbon|null $tarikh_susulan
 * @property int|null $id_pengguna
 * @property Carbon $created_at
 */
class KemaskiniStatusPtd extends TsuModel
{
    protected $table = 'kemaskini_status_ptd';

    public const UPDATED_AT = null;

    protected $casts = ['tarikh_status' => 'date', 'tarikh_susulan' => 'date'];
}
