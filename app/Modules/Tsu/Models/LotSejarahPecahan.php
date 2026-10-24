<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TSU-21: two-way lineage original lot ↔ successor lot (append-only).
 *
 * @property int $id
 * @property string $id_lot_asal
 * @property string $id_lot_baharu
 * @property string $urusan_id
 * @property string $jenis_perubahan
 * @property Carbon $tarikh_berkuat_kuasa
 */
class LotSejarahPecahan extends TsuModel
{
    protected $table = 'lot_sejarah_pecahan';

    public const UPDATED_AT = null;

    protected $casts = ['tarikh_berkuat_kuasa' => 'date'];
}
