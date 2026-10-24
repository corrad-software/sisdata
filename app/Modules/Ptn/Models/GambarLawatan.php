<?php

namespace App\Modules\Ptn\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PTN-06/07: a geotagged site photo. The file lives in MOD-DMS
 * (laluan_simpanan = DMS document id: encrypted object storage, short-lived
 * URLs). Geotag/timestamp/source are immutable (database trigger); photos
 * outside the lot boundary are flagged for supervisor review.
 *
 * @property int $id
 * @property string $id_lawatan
 * @property string|null $laluan_simpanan
 * @property Carbon|null $tarikh_masa
 * @property string|null $arah_kamera
 * @property string|null $ketepatan_m
 * @property string $sumber_gambar
 * @property string $status_pengesahan_lingkungan
 * @property string|null $jarak_dari_lot_m
 * @property bool $perlu_semakan
 * @property string|null $keputusan_semakan
 * @property string|null $catatan_semakan
 * @property int|null $id_penyemak
 * @property Carbon|null $tarikh_semakan
 * @property string|null $tag_rekod_tanah
 * @property string|null $tag_lapisan_gis
 * @property string|null $kod_item
 * @property int|null $id_tindakan
 * @property string $jenis
 * @property string|null $kapsyen
 * @property string|null $nama_fail
 * @property string|null $imej_kecil
 * @property string|null $id_luar_talian
 * @property Carbon $created_at
 * @property float|null $lat
 * @property float|null $lng
 * @property-read RekodLawatan|null $lawatan
 * @property-read User|null $penyemak
 */
class GambarLawatan extends PtnModel
{
    protected $table = 'gambar_lawatan';

    protected $casts = ['tarikh_masa' => 'datetime', 'tarikh_semakan' => 'datetime', 'perlu_semakan' => 'boolean'];

    protected $hidden = ['imej_kecil'];

    public function auditExcept(): array
    {
        return ['updated_at', 'imej_kecil'];
    }

    /** @param Builder<GambarLawatan> $query */
    public function scopeDenganKoordinat(Builder $query): void
    {
        $query->addSelect(['gambar_lawatan.*'])
            ->selectRaw('ST_Y(gambar_lawatan.koordinat_gambar) AS lat, ST_X(gambar_lawatan.koordinat_gambar) AS lng');
    }

    /** @return BelongsTo<RekodLawatan, $this> */
    public function lawatan(): BelongsTo
    {
        return $this->belongsTo(RekodLawatan::class, 'id_lawatan');
    }

    /** @return BelongsTo<User, $this> */
    public function penyemak(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_penyemak');
    }
}
