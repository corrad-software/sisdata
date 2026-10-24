<?php

namespace App\Modules\Adu\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS-ADU-11: routing rule. The most specific active rule (category +
 * district > district > category > catch-all, then lowest `keutamaan`) picks
 * a named officer or the least-loaded in-scope holder of a role.
 *
 * @property int $id
 * @property string|null $kategori
 * @property string|null $kod_daerah
 * @property int|null $id_pegawai
 * @property string|null $kod_peranan
 * @property string|null $bahagian
 * @property int $keutamaan
 * @property bool $aktif
 * @property-read User|null $pegawai
 */
class PeraturanPenugasan extends AduModel
{
    protected $table = 'peraturan_penugasan_aduan';

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'keutamaan' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
