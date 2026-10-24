<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SRS-PJK-01 tenant. Personal / company data — classified Sulit; identity
 * numbers, phone and e-mail are masked in the audit trail and in lists.
 *
 * @property int $id
 * @property string $jenis_entiti
 * @property string $nama
 * @property string|null $no_pengenalan
 * @property string|null $no_pendaftaran_syarikat
 * @property string|null $alamat
 * @property string|null $poskod
 * @property string|null $telefon
 * @property string|null $emel
 * @property string $klasifikasi
 * @property bool $sintetik
 * @property int|null $id_pencipta
 */
class Penyewa extends PjkModel
{
    use SoftDeletes;

    protected $table = 'penyewa';

    protected function casts(): array
    {
        return ['sintetik' => 'boolean'];
    }

    public function auditSensitive(): array
    {
        return ['no_pengenalan', 'no_pendaftaran_syarikat', 'telefon', 'emel', 'alamat'];
    }

    /** @return HasMany<Kontrak, $this> */
    public function kontrak(): HasMany
    {
        return $this->hasMany(Kontrak::class, 'penyewa_id');
    }
}
