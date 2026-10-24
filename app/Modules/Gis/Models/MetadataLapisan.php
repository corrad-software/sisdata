<?php

namespace App\Modules\Gis\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-GIS-29 metadata for a layer (kod_lapisan) or an imported dataset (id_import).
 *
 * @property int $id
 * @property string|null $kod_lapisan
 * @property string|null $id_import
 * @property string|null $sumber_data
 * @property string|null $pemilik_data
 * @property Carbon|null $tarikh_kemas_kini
 * @property string $status_pengesahan
 * @property string $sistem_unjuran
 * @property string|null $tahap_ketepatan
 * @property string|null $had_penggunaan
 * @property string|null $catatan
 */
class MetadataLapisan extends GisModel
{
    protected $table = 'gis_metadata_lapisan';

    public const STATUS = ['belum_disahkan', 'disahkan', 'ditolak'];

    public const FIELDS = ['sumber_data', 'pemilik_data', 'tarikh_kemas_kini', 'status_pengesahan', 'sistem_unjuran', 'tahap_ketepatan', 'had_penggunaan', 'catatan'];

    protected function casts(): array
    {
        return ['tarikh_kemas_kini' => 'date:Y-m-d'];
    }
}
