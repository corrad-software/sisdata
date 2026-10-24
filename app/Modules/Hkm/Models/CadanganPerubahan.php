<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-18: a change proposed by an external integration. Stays a draft —
 * never touches hak_milik — until an officer confirms it, which turns it into
 * a PerubahanHakMilik going through the normal two-stage approval.
 *
 * @property string $id
 * @property string|null $hak_milik_id
 * @property string $no_hak_milik
 * @property string|null $kod_mukim
 * @property string $sumber
 * @property string $rujukan_luaran
 * @property string $jenis_perubahan
 * @property array<string, mixed> $data_cadangan
 * @property string|null $keterangan
 * @property string $status
 * @property int|null $id_penghantar
 * @property int|null $id_penyemak
 * @property Carbon|null $tarikh_semakan
 * @property string|null $catatan_semakan
 * @property string|null $id_perubahan
 * @property Carbon $created_at
 * @property-read HakMilik|null $hakMilik
 * @property-read User|null $penyemak
 */
class CadanganPerubahan extends Model implements Auditable, HasClassification
{
    use HasUuids;

    public const DRAF = 'draf';

    protected $table = 'cadangan_perubahan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data_cadangan' => 'array', 'tarikh_semakan' => 'datetime'];
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

    /** @return BelongsTo<User, $this> */
    public function penyemak(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_penyemak');
    }
}
