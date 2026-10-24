<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-TSU-10..12: appointment of a licensed surveyor for an urusan.
 *
 * @property string $id
 * @property string $urusan_id
 * @property string|null $sebutharga_id
 * @property int $jurukur_id
 * @property string $no_surat_setuju_terima
 * @property Carbon|null $tarikh_surat_setuju_terima
 * @property string $nilai_kontrak
 * @property Carbon $tarikh_mula
 * @property int $tempoh_siap_hari
 * @property Carbon $tarikh_jangka_siap
 * @property string $skop_kerja
 * @property string $status
 * @property Carbon|null $tarikh_siap
 * @property int $peratus_siap_semasa
 * @property bool $lewat
 * @property string|null $id_dokumen_surat
 * @property int|null $id_pencipta
 * @property-read PanelJurukur $jurukur
 * @property-read UrusanTanah $urusan
 * @property-read Collection<int, KemajuanKerjaUkur> $kemajuan
 */
class LantikanJurukur extends TsuModel
{
    use HasUuids;

    protected $table = 'lantikan_jurukur';

    protected $casts = ['nilai_kontrak' => 'decimal:2', 'tarikh_surat_setuju_terima' => 'date', 'tarikh_mula' => 'date', 'tarikh_jangka_siap' => 'date', 'tarikh_siap' => 'date', 'lewat' => 'boolean'];

    /** @return BelongsTo<PanelJurukur, $this> */
    public function jurukur(): BelongsTo
    {
        return $this->belongsTo(PanelJurukur::class, 'jurukur_id');
    }

    /** @return BelongsTo<UrusanTanah, $this> */
    public function urusan(): BelongsTo
    {
        return $this->belongsTo(UrusanTanah::class, 'urusan_id')->withoutGlobalScope('access');
    }

    /** @return HasMany<KemajuanKerjaUkur, $this> */
    public function kemajuan(): HasMany
    {
        return $this->hasMany(KemajuanKerjaUkur::class, 'lantikan_id')->orderBy('id');
    }

    /** Expected % complete today on a straight line from start to target date. */
    public function peratusDijangka(?Carbon $on = null): int
    {
        $on ??= today();
        $total = max(1, $this->tempoh_siap_hari);
        $elapsed = max(0, (int) $this->tarikh_mula->diffInDays($on, false));

        return (int) min(100, floor($elapsed * 100 / $total));
    }
}
