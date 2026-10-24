<?php

namespace App\Modules\Adu\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-ADU-12/13: SLA clock of one complaint cycle (a reopen starts a new
 * cycle). Targets and recorded actual times are immutable (DB trigger).
 *
 * @property int $id
 * @property string $aduan_id
 * @property string|null $no_rujukan_aduan
 * @property int|null $id_sla
 * @property int $kitaran
 * @property Carbon $masa_mula
 * @property Carbon $masa_sasaran_respons
 * @property Carbon $masa_sasaran_selesai
 * @property Carbon|null $masa_respons_sebenar
 * @property Carbon|null $masa_selesai_sebenar
 * @property string $status_sla
 * @property int $bilangan_eskalasi
 * @property bool $semasa
 * @property-read SlaKategori|null $sla
 */
class JejakSla extends AduModel
{
    protected $table = 'jejak_sla_aduan';

    protected function casts(): array
    {
        return [
            'masa_mula' => 'datetime',
            'masa_sasaran_respons' => 'datetime',
            'masa_sasaran_selesai' => 'datetime',
            'masa_respons_sebenar' => 'datetime',
            'masa_selesai_sebenar' => 'datetime',
            'semasa' => 'boolean',
            'kitaran' => 'integer',
            'bilangan_eskalasi' => 'integer',
        ];
    }

    /** @return BelongsTo<SlaKategori, $this> */
    public function sla(): BelongsTo
    {
        return $this->belongsTo(SlaKategori::class, 'id_sla');
    }
}
