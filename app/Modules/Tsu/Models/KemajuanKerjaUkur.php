<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TSU-11: progress report of a survey (append-only).
 *
 * @property int $id
 * @property string $lantikan_id
 * @property string $milestone
 * @property int $peratus_siap
 * @property int $peratus_dijangka
 * @property bool $lewat
 * @property Carbon $tarikh_lapor
 * @property string|null $catatan
 * @property int|null $id_pelapor
 * @property Carbon $created_at
 */
class KemajuanKerjaUkur extends TsuModel
{
    protected $table = 'kemajuan_kerja_ukur';

    public const UPDATED_AT = null;

    protected $casts = ['tarikh_lapor' => 'date', 'lewat' => 'boolean'];
}
