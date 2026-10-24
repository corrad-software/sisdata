<?php

namespace App\Modules\Gis\Models;

use Illuminate\Support\Carbon;

/**
 * A spatial QA finding (SRS-GIS-27/28).
 *
 * @property int $id
 * @property string|null $id_lot
 * @property string|null $id_lot_lain
 * @property string|null $kod_daerah
 * @property string|null $kod_mukim
 * @property string $jenis_ralat
 * @property string $kunci
 * @property string $perihal
 * @property string|null $nilai
 * @property string|null $cadangan
 * @property string $status_pembetulan
 * @property string|null $catatan
 * @property Carbon $dikesan_pada
 * @property Carbon|null $diselesaikan_pada
 */
class RalatKualiti extends GisModel
{
    protected $table = 'gis_ralat_kualiti';

    protected $hidden = ['geom'];

    public const JENIS = ['geometri_tidak_sah', 'geometri_kosong', 'tiada_geometri', 'luar_selangor', 'luar_mukim', 'pertindihan', 'jurang'];

    public const STATUS = ['baharu', 'dalam_tindakan', 'selesai', 'diabaikan'];

    protected function casts(): array
    {
        return ['dikesan_pada' => 'datetime', 'diselesaikan_pada' => 'datetime'];
    }

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at', 'geom', 'dikesan_pada'];
    }
}
