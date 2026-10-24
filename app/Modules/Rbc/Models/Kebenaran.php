<?php

namespace App\Modules\Rbc\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted permission catalogue entry (mirror of PermissionRegistry::all()).
 *
 * @property string $kunci
 * @property string $modul
 * @property string $fungsi
 * @property string $tindakan
 * @property string $label
 */
class Kebenaran extends Model
{
    protected $table = 'kebenaran';

    protected $primaryKey = 'kunci';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['kunci', 'modul', 'fungsi', 'tindakan', 'label'];
}
