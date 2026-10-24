<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-TSU-08/09: a survey quotation invitation to panel surveyors.
 *
 * @property string $id
 * @property string $urusan_id
 * @property string $no_jemputan
 * @property Carbon $tarikh_jemputan
 * @property Carbon $tarikh_tutup
 * @property string $skop_kerja
 * @property string $status
 * @property string|null $id_dokumen_jemputan
 * @property int|null $id_tawaran_disyorkan
 * @property string|null $justifikasi_pemilihan
 * @property int|null $id_penilai
 * @property string|null $id_aliran_kerja
 * @property int|null $id_pelulus
 * @property Carbon|null $tarikh_kelulusan
 * @property int|null $id_pencipta
 * @property-read UrusanTanah $urusan
 * @property-read Collection<int, SebutHargaTawaran> $tawaran
 */
class SebutHargaUkur extends TsuModel
{
    use HasUuids;

    protected $table = 'sebutharga_ukur';

    protected $casts = ['tarikh_jemputan' => 'date', 'tarikh_tutup' => 'datetime', 'tarikh_kelulusan' => 'datetime'];

    public function ditutup(): bool
    {
        return $this->tarikh_tutup->lte(now());
    }

    /** @return BelongsTo<UrusanTanah, $this> */
    public function urusan(): BelongsTo
    {
        return $this->belongsTo(UrusanTanah::class, 'urusan_id')->withoutGlobalScope('access');
    }

    /** @return HasMany<SebutHargaTawaran, $this> */
    public function tawaran(): HasMany
    {
        return $this->hasMany(SebutHargaTawaran::class, 'sebutharga_id')->orderBy('id');
    }
}
