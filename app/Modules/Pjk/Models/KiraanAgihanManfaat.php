<?php

namespace App\Modules\Pjk\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS-PJK-24: auditable result of one benefit-distribution calculation,
 * linked to the policy version used.
 *
 * @property int $id
 * @property int $premis_id
 * @property string|null $kontrak_id
 * @property string $tempoh_perakaunan
 * @property string $hasil_kutipan
 * @property int $id_dasar_digunakan
 * @property int $versi_dasar
 * @property string $kategori_hartanah
 * @property string $status_perolehan
 * @property string $kadar_agihan
 * @property string $amaun_agihan
 * @property list<array{kod: string, nama: string, peratus: string, amaun: string}> $pecahan_penerima
 * @property string $kod_daerah
 * @property string $status_pengesahan
 * @property int|null $id_pengira
 * @property int|null $id_pengesah
 * @property-read PremisAset $premis
 */
class KiraanAgihanManfaat extends PjkModel
{
    protected $table = 'kiraan_agihan_manfaat';

    protected function casts(): array
    {
        return ['pecahan_penerima' => 'array', 'tarikh_pengesahan' => 'datetime'];
    }

    /** @return BelongsTo<PremisAset, $this> */
    public function premis(): BelongsTo
    {
        return $this->belongsTo(PremisAset::class, 'premis_id');
    }
}
