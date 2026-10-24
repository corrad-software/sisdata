<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One invited surveyor and their offer. The price is encrypted at rest and
 * never shown before the closing date (security control "nilai sebut harga
 * disimpan terkawal sebelum tarikh tutup").
 *
 * @property int $id
 * @property string $sebutharga_id
 * @property int $jurukur_id
 * @property string $status
 * @property string|null $harga_tawaran
 * @property int|null $tempoh_siap_hari
 * @property int|null $pengalaman_tahun
 * @property Carbon|null $tarikh_terima
 * @property string|null $markah
 * @property string|null $catatan_penilaian
 * @property bool $indikator_disyorkan
 * @property-read PanelJurukur $jurukur
 * @property-read SebutHargaUkur $sebutharga
 */
class SebutHargaTawaran extends TsuModel
{
    protected $table = 'sebutharga_tawaran';

    protected $casts = ['harga_tawaran' => 'encrypted', 'tarikh_terima' => 'datetime', 'markah' => 'decimal:2', 'indikator_disyorkan' => 'boolean'];

    public function auditSensitive(): array
    {
        return ['harga_tawaran'];
    }

    /** @return BelongsTo<PanelJurukur, $this> */
    public function jurukur(): BelongsTo
    {
        return $this->belongsTo(PanelJurukur::class, 'jurukur_id');
    }

    /** @return BelongsTo<SebutHargaUkur, $this> */
    public function sebutharga(): BelongsTo
    {
        return $this->belongsTo(SebutHargaUkur::class, 'sebutharga_id');
    }
}
