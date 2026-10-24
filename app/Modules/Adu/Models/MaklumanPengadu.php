<?php

namespace App\Modules\Adu\Models;

/**
 * SRS-ADU-05: one notice sent (or skipped) to a reporter.
 *
 * @property int $id
 * @property string $aduan_id
 * @property string $peristiwa
 * @property string $saluran
 * @property string $status
 * @property string|null $ralat
 */
class MaklumanPengadu extends AduModel
{
    protected $table = 'makluman_pengadu';
}
