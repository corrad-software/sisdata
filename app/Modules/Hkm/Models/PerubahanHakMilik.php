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
 * SRS-HKM-19: a requested change to a title (details, status, owner,
 * encumbrance or heirs). Runs WFE flow `hkm.perubahan_hak_milik`; applied as a
 * new version only on WorkflowCompleted(lulus).
 *
 * @property string $id
 * @property string $hak_milik_id
 * @property string $jenis_perubahan
 * @property array<string, mixed> $data_cadangan
 * @property array<string, mixed>|null $data_semasa
 * @property string $justifikasi
 * @property string|null $id_dokumen_rujukan
 * @property string|null $no_rujukan_dokumen
 * @property string $status
 * @property string|null $id_instans_aliran
 * @property int|null $id_pemohon
 * @property string|null $cadangan_perubahan_id
 * @property int|null $diputuskan_oleh
 * @property Carbon|null $tarikh_keputusan
 * @property string|null $catatan_keputusan
 * @property Carbon $created_at
 * @property-read HakMilik $hakMilik
 * @property-read User|null $pemohon
 */
class PerubahanHakMilik extends Model implements Auditable, HasClassification
{
    use HasUuids;

    public const MENUNGGU = 'menunggu_kelulusan';

    public const DILULUSKAN = 'diluluskan';

    protected $table = 'perubahan_hak_milik';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data_cadangan' => 'array', 'data_semasa' => 'array', 'tarikh_keputusan' => 'datetime'];
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
    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pemohon');
    }
}
