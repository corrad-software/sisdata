<?php

namespace App\Modules\Ref\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One load run into staging (SRS 15.1 #6: every row points to the run that
 * loaded it and its timestamp).
 *
 * @property string $id
 * @property string $jenis
 * @property string $sumber_sistem
 * @property array<string, mixed>|null $parameter
 * @property string $status
 * @property int $bilangan_diterima
 * @property int $bilangan_ditolak
 */
class MigrasiLarian extends Model
{
    use HasUuids;

    protected $table = 'migrasi_larian';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'parameter' => 'array',
            'mula_pada' => 'datetime',
            'tamat_pada' => 'datetime',
        ];
    }
}
