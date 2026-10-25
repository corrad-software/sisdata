<?php

namespace App\Modules\Tkt\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS-TKT-10: auto-assignment rule — module / category / severity (null =
 * any) → team, optionally a named technician. Most specific match wins,
 * then `keutamaan`.
 *
 * @property int $id
 * @property string|null $modul
 * @property string|null $kategori
 * @property int|null $keterukan
 * @property int $id_pasukan
 * @property int|null $id_juruteknik
 * @property int $keutamaan
 * @property bool $aktif
 * @property-read Pasukan|null $pasukan
 */
class PeraturanPenugasan extends TktModel
{
    protected $table = 'tkt_peraturan_penugasan';

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'keterukan' => 'integer', 'keutamaan' => 'integer'];
    }

    /** @return BelongsTo<Pasukan, $this> */
    public function pasukan(): BelongsTo
    {
        return $this->belongsTo(Pasukan::class, 'id_pasukan');
    }
}
