<?php

namespace App\Modules\Rbc\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * POLISI_KESELAMATAN: admin override of one security parameter.
 *
 * @property string $kunci
 * @property mixed $nilai
 * @property int|null $dikemas_kini_oleh
 */
class PolisiKeselamatan extends Model
{
    protected $table = 'polisi_keselamatan';

    protected $primaryKey = 'kunci';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['kunci', 'nilai', 'dikemas_kini_oleh'];

    protected function casts(): array
    {
        return ['nilai' => 'json'];
    }
}
