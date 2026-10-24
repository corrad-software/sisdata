<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PJK-05/07/10: a renewal, rate review, term amendment or termination
 * request; applied only when WFE completes `pjk.pindaan_kontrak` with lulus.
 *
 * @property int $id
 * @property string $kontrak_id
 * @property string $jenis_pindaan
 * @property array<string, mixed> $data_cadangan
 * @property array<string, mixed> $data_semasa
 * @property string $justifikasi
 * @property array<string, mixed>|null $semakan_kadar
 * @property string|null $id_dokumen_selepas
 * @property string $status
 * @property int|null $id_pemohon
 * @property int|null $id_pelulus
 * @property Carbon|null $tarikh_keputusan
 * @property string|null $ulasan
 * @property string|null $id_instans_aliran
 * @property string|null $id_kontrak_baharu
 * @property Carbon $created_at
 * @property-read Kontrak $kontrak
 */
class PindaanKontrak extends PjkModel
{
    public const SUBJECT = 'pindaan_kontrak';

    protected $table = 'pindaan_kontrak';

    protected function casts(): array
    {
        return ['data_cadangan' => 'array', 'data_semasa' => 'array', 'semakan_kadar' => 'array', 'tarikh_keputusan' => 'datetime'];
    }

    /** @return BelongsTo<Kontrak, $this> */
    public function kontrak(): BelongsTo
    {
        return $this->belongsTo(Kontrak::class, 'kontrak_id');
    }
}
