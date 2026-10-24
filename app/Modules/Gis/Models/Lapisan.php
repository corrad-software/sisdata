<?php

namespace App\Modules\Gis\Models;

use Illuminate\Support\Carbon;

/**
 * Configuration of one map layer (SRS-GIS-12/32). Rows are created on demand
 * for every registered MapLayer; administrators change classification,
 * publication, order and default opacity.
 *
 * @property int $id
 * @property string $kod_lapisan
 * @property string $nama
 * @property string $kumpulan
 * @property string $jenis_geometri
 * @property string $klasifikasi_keselamatan
 * @property string $status_terbit
 * @property int $urutan_paparan
 * @property string $ketelusan_lalai
 * @property bool $papar_lalai
 * @property bool $terbit_ogc
 * @property bool $lapisan_awam
 * @property int|null $dikemas_kini_oleh
 * @property Carbon|null $updated_at
 */
class Lapisan extends GisModel
{
    protected $table = 'gis_lapisan';

    public const KLASIFIKASI = ['awam', 'dalaman', 'sulit', 'rahsia'];

    public const STATUS = ['terbit', 'draf', 'tidak_aktif'];

    protected function casts(): array
    {
        return ['papar_lalai' => 'bool', 'terbit_ogc' => 'bool', 'lapisan_awam' => 'bool', 'urutan_paparan' => 'int'];
    }
}
