<?php

namespace App\Modules\Rbc\Models;

use App\Modules\Rbc\Services\RbcAccessControl;
use Illuminate\Database\Eloquent\Model;

/**
 * PENGGUNA_SKOP (SRS-RBC-07): a user's record-level scope values.
 *
 * @property int $user_id
 * @property string $jenis_skop
 * @property string $nilai_skop
 * @property string $sumber
 */
class PenggunaSkop extends Model
{
    protected $table = 'pengguna_skop';

    protected $fillable = ['user_id', 'jenis_skop', 'nilai_skop', 'sumber'];

    protected $attributes = ['sumber' => 'manual'];

    protected static function booted(): void
    {
        static::saved(fn () => RbcAccessControl::flushAll());
        static::deleted(fn () => RbcAccessControl::flushAll());
    }
}
