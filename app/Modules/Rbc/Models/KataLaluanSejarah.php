<?php

namespace App\Modules\Rbc\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PENGGUNA_KATA_LALUAN_SEJARAH (SRS-RBC-12): previous password hashes.
 *
 * @property int $user_id
 * @property string $kata_laluan
 */
class KataLaluanSejarah extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'kata_laluan_sejarah';

    protected $fillable = ['user_id', 'kata_laluan'];

    protected $hidden = ['kata_laluan'];
}
