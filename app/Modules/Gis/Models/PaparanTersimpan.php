<?php

namespace App\Modules\Gis\Models;

/**
 * Saved map view / bookmark.
 *
 * @property int $id
 * @property string $nama
 * @property int $id_pengguna
 * @property list<string> $lapisan
 * @property list<float> $pusat
 * @property string $zum
 * @property string $peta_asas
 * @property bool $dikongsi
 */
class PaparanTersimpan extends GisModel
{
    protected $table = 'gis_paparan_tersimpan';

    protected function casts(): array
    {
        return ['lapisan' => 'array', 'pusat' => 'array', 'dikongsi' => 'bool'];
    }
}
