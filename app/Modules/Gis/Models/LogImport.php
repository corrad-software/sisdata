<?php

namespace App\Modules\Gis\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;

/**
 * One spatial file import (SRS-GIS-22): validated feature by feature before
 * it can be accepted as a dataset layer.
 *
 * @property string $id
 * @property string $nama_set_data
 * @property string $nama_fail
 * @property string $format
 * @property int|null $srid_asal
 * @property string $kategori
 * @property int $bilangan_ciri
 * @property int $bilangan_sah
 * @property int $bilangan_ralat
 * @property string $status
 * @property list<string> $ralat_fail
 * @property list<string> $amaran
 * @property list<string> $medan
 * @property string|null $medan_no_lot
 * @property string|null $id_dokumen
 * @property string $klasifikasi
 * @property int|null $id_pengguna
 * @property Carbon|null $diterima_pada
 * @property Carbon|null $created_at
 */
class LogImport extends GisModel
{
    use HasUuids;

    protected $table = 'gis_log_import';

    public const FORMATS = ['geojson', 'kml', 'kmz', 'shp', 'dxf', 'gpkg'];

    public const KATEGORI = ['umum', 'lot', 'zoning', 'infrastruktur', 'risiko', 'sempadan'];

    protected function casts(): array
    {
        return ['ralat_fail' => 'array', 'amaran' => 'array', 'medan' => 'array', 'diterima_pada' => 'datetime', 'bilangan_ciri' => 'int', 'bilangan_sah' => 'int', 'bilangan_ralat' => 'int', 'srid_asal' => 'int'];
    }
}
