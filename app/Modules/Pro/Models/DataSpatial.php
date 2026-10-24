<?php

namespace App\Modules\Pro\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PRO-07: one geometry version of a lot. Exactly one row per lot is
 * `semasa`; older rows are kept for history. The geometry column is written
 * with SQL (ST_Transform …) and read with ST_AsGeoJSON.
 *
 * @property int $id
 * @property string $lot_id
 * @property int $srid_asal
 * @property string|null $keluasan_dikira
 * @property string $sumber_data
 * @property string|null $rujukan_fail_sumber
 * @property Carbon|null $tarikh_perolehan_data
 * @property string $status_pengesahan
 * @property int|null $id_pegawai_pengesah
 * @property Carbon|null $tarikh_pengesahan
 * @property string|null $catatan_pertindihan
 * @property bool $semasa
 * @property int $versi
 * @property string|null $format_asal
 * @property string|null $id_dokumen_sumber
 * @property string|null $peratus_beza_keluasan
 * @property int|null $id_pemuat_naik
 * @property string|null $catatan_pengesahan
 * @property string|null $sumber_sistem
 * @property string|null $kunci_asal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DataSpatial extends ProModel
{
    protected $table = 'data_spatial';

    public const STATUS = ['belum_disahkan', 'disahkan_juruukur', 'disahkan_ptg', 'bertindih'];

    public const DISAHKAN = ['disahkan_juruukur', 'disahkan_ptg'];

    public const SUMBER = ['jupem', 'ptg', 'pbt', 'juruukur', 'lakaran', 'satelit', 'sintetik'];

    /** SRIDs accepted on upload (all in spatial_ref_sys): WGS84, GDM2000, GDM2000 Peninsula RSO, GDM2000 Selangor Grid (Cassini-Soldner), Kertau RSO (m), Kertau 1968. */
    public const SRID = [4326, 4742, 3375, 3380, 3168, 4245];

    protected $hidden = ['geometri'];

    protected function casts(): array
    {
        return [
            'semasa' => 'boolean',
            'tarikh_perolehan_data' => 'date',
            'tarikh_pengesahan' => 'datetime',
            'versi' => 'integer',
            'srid_asal' => 'integer',
        ];
    }

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at', 'geometri'];
    }

    /** @return BelongsTo<LotTanah, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(LotTanah::class, 'lot_id');
    }

    public function disahkan(): bool
    {
        return in_array($this->status_pengesahan, self::DISAHKAN, true);
    }
}
