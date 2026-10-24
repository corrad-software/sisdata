<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-TSU-16/17: an imported survey result (WGS84 MultiPolygon) with the
 * computed vs documented area comparison and its verification.
 *
 * @property string $id
 * @property string $urusan_id
 * @property string|null $lantikan_id
 * @property string|null $lot_id
 * @property string|null $no_lot_cadangan
 * @property string $format_fail
 * @property int $sistem_koordinat
 * @property string $keluasan_dikira
 * @property string|null $keluasan_dokumen
 * @property string|null $sisihan_peratus
 * @property string $toleransi_peratus
 * @property bool|null $dalam_toleransi
 * @property string $status_pengesahan
 * @property string|null $catatan_pengesahan
 * @property int|null $id_pemuat_naik
 * @property int|null $id_pegawai_sah
 * @property Carbon|null $tarikh_sah
 * @property string|null $id_dokumen_fail
 * @property string|null $id_dokumen_pelan_akui
 * @property string|null $id_lot_dicipta
 * @property int|null $id_spatial_dicadang
 * @property Carbon $created_at
 * @property-read UrusanTanah $urusan
 */
class HasilUkur extends TsuModel
{
    use HasUuids;

    protected $table = 'hasil_ukur';

    protected $hidden = ['geometri'];

    protected $casts = ['keluasan_dikira' => 'decimal:4', 'keluasan_dokumen' => 'decimal:4', 'sisihan_peratus' => 'decimal:2', 'toleransi_peratus' => 'decimal:2', 'dalam_toleransi' => 'boolean', 'tarikh_sah' => 'datetime'];

    /** @return BelongsTo<UrusanTanah, $this> */
    public function urusan(): BelongsTo
    {
        return $this->belongsTo(UrusanTanah::class, 'urusan_id')->withoutGlobalScope('access');
    }
}
