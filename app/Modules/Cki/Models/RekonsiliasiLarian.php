<?php

namespace App\Modules\Cki\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $dari
 * @property Carbon $hingga
 * @property int $bil_padan
 * @property int $bil_sisihan
 * @property int|null $id_pengguna
 * @property Carbon $created_at
 */
class RekonsiliasiLarian extends CkiModel
{
    protected $table = 'rekonsiliasi_cukai_larian';

    protected $casts = ['dari' => 'date', 'hingga' => 'date'];
}
