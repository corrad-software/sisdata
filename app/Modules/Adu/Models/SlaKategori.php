<?php

namespace App\Modules\Adu\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-ADU-12/14: configurable SLA (response + resolution) and escalation
 * path for a category / subcategory, effective from a date.
 *
 * @property int $id
 * @property string $kategori
 * @property string|null $subkategori
 * @property int $tempoh_respons
 * @property int $tempoh_penyelesaian
 * @property string $unit_masa hari_bekerja | jam_bekerja
 * @property list<array{peringkat: int, sasaran: string, peratus: int, penerima: list<string>}> $laluan_eskalasi
 * @property Carbon $tarikh_berkuat_kuasa
 * @property bool $aktif
 */
class SlaKategori extends AduModel
{
    protected $table = 'sla_kategori_aduan';

    protected function casts(): array
    {
        return ['laluan_eskalasi' => 'array', 'tarikh_berkuat_kuasa' => 'date:Y-m-d', 'aktif' => 'boolean', 'tempoh_respons' => 'integer', 'tempoh_penyelesaian' => 'integer'];
    }
}
