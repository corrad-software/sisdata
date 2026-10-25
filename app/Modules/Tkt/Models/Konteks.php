<?php

namespace App\Modules\Tkt\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TKT-02: context captured when the ticket was opened (already masked).
 *
 * @property int $id
 * @property string $id_tiket
 * @property string|null $skrin
 * @property string|null $modul
 * @property string|null $kod_fungsi
 * @property string|null $url
 * @property list<string>|null $peranan_pengguna
 * @property int|null $id_pengguna
 * @property string|null $pelayar
 * @property string|null $versi_sistem
 * @property string|null $versi_ui
 * @property string|null $rujukan_rekod
 * @property list<string>|null $id_permintaan
 * @property string|null $id_permintaan_cipta
 * @property string|null $saiz_skrin
 * @property string|null $zon_masa
 * @property Carbon $cap_masa_tangkapan
 */
class Konteks extends TktModel
{
    protected $table = 'tkt_konteks';

    protected function casts(): array
    {
        return ['peranan_pengguna' => 'array', 'id_permintaan' => 'array', 'cap_masa_tangkapan' => 'datetime'];
    }
}
