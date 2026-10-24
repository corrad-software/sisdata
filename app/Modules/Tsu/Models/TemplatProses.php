<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-TSU-01/02: a versioned process template per urusan type.
 *
 * @property int $id
 * @property string $jenis_urusan
 * @property int $versi
 * @property string $nama
 * @property string|null $keterangan
 * @property string|null $aliran_kelulusan
 * @property bool $status_aktif
 * @property int|null $id_pencipta
 * @property Carbon|null $created_at
 * @property-read Collection<int, TemplatPeringkat> $peringkat
 */
class TemplatProses extends TsuModel
{
    protected $table = 'templat_proses';

    protected $casts = ['status_aktif' => 'boolean'];

    /** @return HasMany<TemplatPeringkat, $this> */
    public function peringkat(): HasMany
    {
        return $this->hasMany(TemplatPeringkat::class, 'templat_id')->orderBy('no_urutan');
    }
}
