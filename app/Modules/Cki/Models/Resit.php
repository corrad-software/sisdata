<?php

namespace App\Modules\Cki\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-CKI-13: an official receipt (electronic from the API, or uploaded
 * manually). Never changed or deleted (DB trigger) — only linked once.
 *
 * @property int $id
 * @property string|null $id_transaksi
 * @property string|null $id_bil
 * @property string $no_resit
 * @property string|null $no_bil_rujukan
 * @property string|null $kod_agensi
 * @property string $amaun
 * @property Carbon $tarikh_resit
 * @property string $sumber
 * @property string|null $id_dokumen
 * @property string|null $kod_daerah
 * @property bool $sintetik
 * @property int|null $id_pengguna
 */
class Resit extends CkiModel
{
    protected $table = 'resit_cukai';

    protected $casts = ['tarikh_resit' => 'date', 'sintetik' => 'boolean'];
}
