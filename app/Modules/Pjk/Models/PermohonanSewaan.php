<?php

namespace App\Modules\Pjk\Models;

use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-PJK-06: rental application from the public portal or the counter —
 * both enter the same contract approval flow.
 *
 * @property int $id
 * @property string $no_permohonan
 * @property string $sumber
 * @property string|null $rujukan_portal
 * @property int $premis_id
 * @property int|null $penyewa_id
 * @property array<string, mixed>|null $maklumat_pemohon
 * @property Carbon|null $tarikh_mula_dicadang
 * @property int $tempoh_bulan
 * @property string|null $kadar_dicadang
 * @property string|null $tujuan
 * @property string $status
 * @property string|null $kontrak_id
 * @property string|null $catatan
 * @property string $kod_daerah
 * @property string $klasifikasi
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property-read PremisAset $premis
 * @property-read Penyewa|null $penyewa
 */
class PermohonanSewaan extends PjkModel
{
    use ScopedByAccess;

    protected $table = 'permohonan_sewaan';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'klasifikasi' => 'klasifikasi'];

    protected function casts(): array
    {
        return ['maklumat_pemohon' => 'array', 'tarikh_mula_dicadang' => 'date:Y-m-d', 'sintetik' => 'boolean', 'tempoh_bulan' => 'integer'];
    }

    public function auditSensitive(): array
    {
        return ['maklumat_pemohon'];
    }

    /** @return BelongsTo<PremisAset, $this> */
    public function premis(): BelongsTo
    {
        return $this->belongsTo(PremisAset::class, 'premis_id');
    }

    /** @return BelongsTo<Penyewa, $this> */
    public function penyewa(): BelongsTo
    {
        return $this->belongsTo(Penyewa::class, 'penyewa_id');
    }
}
