<?php

namespace App\Modules\Wfe\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $id_tugasan
 * @property int $aras
 * @property list<int> $penerima
 * @property Carbon $created_at
 */
class EskalasiLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'wf_eskalasi_log';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['penerima' => 'array'];
    }
}
