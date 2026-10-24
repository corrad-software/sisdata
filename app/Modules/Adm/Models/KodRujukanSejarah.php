<?php

namespace App\Modules\Adm\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $id_kod_rujukan
 * @property int $versi
 * @property array<string, mixed> $data
 * @property int|null $diubah_oleh
 */
class KodRujukanSejarah extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'adm_kod_rujukan_sejarah';

    protected $fillable = ['id_kod_rujukan', 'versi', 'data', 'diubah_oleh'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
