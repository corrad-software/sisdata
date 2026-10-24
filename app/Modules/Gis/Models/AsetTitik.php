<?php

namespace App\Modules\Gis\Models;

use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Physical asset point (mandatory layer 10) until MOD-AST owns the asset register.
 * `geom` is set as HEXEWKB so the audit trail records the geometry before
 * and after every change (SRS-GIS-31); it is deliberately not $hidden
 * (hidden fields are masked by MOD-AUD).
 *
 * @property int $id
 * @property string|null $id_lot
 * @property string $nama
 * @property string $kategori
 * @property string $status_keadaan
 * @property string|null $kod_daerah
 * @property string|null $kod_mukim
 * @property string $klasifikasi
 * @property string|null $catatan
 * @property int|null $id_pengguna
 */
class AsetTitik extends GisModel
{
    use ScopedByAccess, SoftDeletes;

    protected $table = 'gis_aset_titik';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    public const KATEGORI = ['bangunan', 'premis', 'infrastruktur', 'lain'];

    public const KEADAAN = ['baik', 'perlu_perhatian', 'kritikal'];
}
