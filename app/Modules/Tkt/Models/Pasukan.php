<?php

namespace App\Modules\Tkt\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Support team / queue: L1 MAIS helpdesk, L2 vendor (or BTM after the
 * warranty — SRS-TKT-21). `modul` null = every module.
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string $aras
 * @property string $jenis
 * @property string|null $nama_vendor
 * @property list<string>|null $modul
 * @property bool $aktif
 */
class Pasukan extends TktModel
{
    protected $table = 'tkt_pasukan';

    protected function casts(): array
    {
        return ['modul' => 'array', 'aktif' => 'boolean'];
    }

    /** @return HasMany<AhliPasukan, $this> */
    public function ahli(): HasMany
    {
        return $this->hasMany(AhliPasukan::class, 'id_pasukan');
    }

    public function liputi(?string $modul): bool
    {
        return $this->modul === null || $this->modul === [] || ($modul !== null && in_array($modul, $this->modul, true));
    }
}
