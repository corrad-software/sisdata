<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-TSU-14: one cost item of an urusan stage (DECIMAL, bcmath in code).
 *
 * @property int $id
 * @property string $urusan_id
 * @property int|null $peringkat_id
 * @property string $kategori_kos
 * @property string|null $keterangan
 * @property string $amaun
 * @property string $status_bayaran
 * @property string|null $no_invois
 * @property Carbon|null $tarikh_invois
 * @property Carbon|null $tarikh_akhir_bayaran
 * @property Carbon|null $tarikh_bayaran
 * @property string|null $no_resit
 * @property string|null $id_dokumen
 * @property string|null $rujukan_grp
 * @property int|null $id_pencipta
 * @property int|null $id_pengemaskini
 * @property Carbon|null $created_at
 * @property-read UrusanTanah $urusan
 * @property-read UrusanPeringkat|null $peringkat
 */
class KosUrusan extends TsuModel
{
    protected $table = 'kos_urusan';

    protected $casts = ['amaun' => 'decimal:2', 'tarikh_invois' => 'date', 'tarikh_akhir_bayaran' => 'date', 'tarikh_bayaran' => 'date'];

    /** @return BelongsTo<UrusanTanah, $this> */
    public function urusan(): BelongsTo
    {
        return $this->belongsTo(UrusanTanah::class, 'urusan_id')->withoutGlobalScope('access');
    }

    /** @return BelongsTo<UrusanPeringkat, $this> */
    public function peringkat(): BelongsTo
    {
        return $this->belongsTo(UrusanPeringkat::class, 'peringkat_id');
    }
}
