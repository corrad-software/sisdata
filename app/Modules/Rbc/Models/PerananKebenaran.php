<?php

namespace App\Modules\Rbc\Models;

use App\Modules\Rbc\Services\RbcAccessControl;
use Illuminate\Database\Eloquent\Model;

/**
 * PERANAN_KEBENARAN: presence of a row = the role is granted the permission.
 *
 * @property int $peranan_id
 * @property string $kebenaran
 * @property string $modul
 * @property string $fungsi
 * @property string $tindakan
 */
class PerananKebenaran extends Model
{
    protected $table = 'peranan_kebenaran';

    protected $fillable = ['peranan_id', 'kebenaran', 'modul', 'fungsi', 'tindakan'];

    protected static function booted(): void
    {
        static::saved(fn () => RbcAccessControl::flushAll());
        static::deleted(fn () => RbcAccessControl::flushAll());
    }

    /** @return array{modul: string, fungsi: string, tindakan: string} */
    public static function split(string $key): array
    {
        $parts = explode('.', $key, 3) + ['', '', ''];

        return ['modul' => $parts[0], 'fungsi' => $parts[1], 'tindakan' => $parts[2]];
    }
}
