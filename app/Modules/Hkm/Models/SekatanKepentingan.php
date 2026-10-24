<?php

namespace App\Modules\Hkm\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-13: caveat, charge, lien, restriction in interest… on a title.
 *
 * @property int $id
 * @property string $hak_milik_id
 * @property string $jenis_sekatan
 * @property string|null $no_rujukan_sekatan
 * @property string|null $pihak_terlibat
 * @property string|null $keterangan
 * @property Carbon|null $tarikh_masuk
 * @property Carbon|null $tarikh_lupus
 * @property string $status_sekatan
 * @property string|null $id_dokumen_rujukan
 * @property string|null $id_kes_mahkamah
 */
class SekatanKepentingan extends Model implements Auditable, HasClassification
{
    protected $table = 'sekatan_kepentingan';

    protected $guarded = ['id'];

    public const MEDAN = ['jenis_sekatan', 'no_rujukan_sekatan', 'pihak_terlibat', 'keterangan', 'tarikh_masuk', 'tarikh_lupus', 'status_sekatan', 'id_dokumen_rujukan', 'id_kes_mahkamah'];

    protected function casts(): array
    {
        return ['tarikh_masuk' => 'date:Y-m-d', 'tarikh_lupus' => 'date:Y-m-d'];
    }

    public function auditModule(): string
    {
        return 'hkm';
    }

    public function auditExcept(): array
    {
        return ['updated_at', 'created_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }

    public function auditClassification(): string
    {
        return 'sulit';
    }

    /** @return BelongsTo<HakMilik, $this> */
    public function hakMilik(): BelongsTo
    {
        return $this->belongsTo(HakMilik::class, 'hak_milik_id');
    }
}
