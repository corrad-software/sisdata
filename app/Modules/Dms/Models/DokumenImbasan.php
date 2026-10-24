<?php

namespace App\Modules\Dms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SRS-DMS-07: one antivirus scan result (DOKUMEN_IMBASAN).
 *
 * @property int $id
 * @property string|null $dokumen_id
 * @property int|null $kuarantin_id
 * @property string $nama_fail
 * @property string $cincangan_fail
 * @property string $enjin
 * @property string|null $versi_tandatangan
 * @property string $keputusan bersih | dijangkiti | ralat
 * @property string|null $nama_ancaman
 * @property Carbon $tarikh_imbasan
 */
class DokumenImbasan extends Model
{
    protected $table = 'dokumen_imbasan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_imbasan' => 'datetime'];
    }
}
