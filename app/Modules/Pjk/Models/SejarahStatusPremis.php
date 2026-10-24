<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-PJK-03: premise status history (append-only).
 *
 * @property int $id
 * @property int $premis_id
 * @property string|null $status_sebelum
 * @property string $status_selepas
 * @property Carbon $tarikh
 * @property int|null $id_pegawai
 * @property string|null $sebab
 */
class SejarahStatusPremis extends PjkModel
{
    public const UPDATED_AT = null;

    protected $table = 'sejarah_status_premis';

    protected function casts(): array
    {
        return ['tarikh' => 'date:Y-m-d'];
    }
}
