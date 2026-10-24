<?php

namespace App\Modules\Dms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SRS-DMS-22: archive event (DOKUMEN_ARKIB).
 *
 * @property int $id
 * @property string $dokumen_id
 * @property Carbon $tarikh_arkib
 * @property Carbon|null $tarikh_layak_lupus
 * @property string $sebab retensi_auto | manual
 * @property int|null $diarkib_oleh
 */
class DokumenArkib extends Model
{
    protected $table = 'dokumen_arkib';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_arkib' => 'datetime', 'tarikh_layak_lupus' => 'date'];
    }
}
