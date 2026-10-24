<?php

namespace App\Modules\Rbc\Models;

use App\Modules\Rbc\Services\RbcAccessControl;
use Illuminate\Database\Eloquent\Model;

/**
 * Role-level scope restriction (SRS-RBC-06/07).
 *
 * @property int $peranan_id
 * @property string $jenis_skop
 * @property string $nilai_skop
 */
class PerananSkop extends Model
{
    protected $table = 'peranan_skop';

    protected $fillable = ['peranan_id', 'jenis_skop', 'nilai_skop'];

    protected static function booted(): void
    {
        static::saved(fn () => RbcAccessControl::flushAll());
        static::deleted(fn () => RbcAccessControl::flushAll());
    }
}
