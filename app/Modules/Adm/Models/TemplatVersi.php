<?php

namespace App\Modules\Adm\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $id_templat
 * @property int $versi
 * @property string $format_output
 * @property string $kandungan
 * @property list<string>|null $medan
 */
class TemplatVersi extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'adm_templat_versi';

    protected $fillable = ['id_templat', 'versi', 'format_output', 'kandungan', 'medan', 'dicipta_oleh'];

    protected function casts(): array
    {
        return ['medan' => 'array'];
    }
}
