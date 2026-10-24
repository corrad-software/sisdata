<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-16: application to replace a temporary title with a permanent one.
 *
 * @property string $id
 * @property string $hak_milik_sementara_id
 * @property string $lot_id
 * @property string $no_permohonan
 * @property Carbon $tarikh_permohonan
 * @property string $pejabat_tanah
 * @property list<array{kod: string, label: string, lengkap?: bool, id_dokumen?: string|null}> $senarai_semak_dokumen
 * @property string $peringkat_semasa
 * @property string $status_kelulusan
 * @property string|null $jumlah_bayaran
 * @property Carbon|null $tarikh_bayaran
 * @property string|null $no_resit
 * @property Carbon|null $tarikh_terima_hak_milik_baharu
 * @property string|null $hak_milik_baharu_id
 * @property int|null $tempoh_proses_hari
 * @property string|null $catatan
 * @property int|null $id_pegawai
 * @property Carbon $created_at
 * @property-read HakMilik $hakMilikSementara
 * @property-read HakMilik|null $hakMilikBaharu
 */
class PermohonanHakMilikKekal extends Model implements Auditable, HasClassification
{
    use HasUuids;

    protected $table = 'permohonan_hak_milik_kekal';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tarikh_permohonan' => 'date:Y-m-d',
            'tarikh_bayaran' => 'date:Y-m-d',
            'tarikh_terima_hak_milik_baharu' => 'date:Y-m-d',
            'senarai_semak_dokumen' => 'array',
            'tempoh_proses_hari' => 'integer',
        ];
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
    public function hakMilikSementara(): BelongsTo
    {
        return $this->belongsTo(HakMilik::class, 'hak_milik_sementara_id');
    }

    /** @return BelongsTo<HakMilik, $this> */
    public function hakMilikBaharu(): BelongsTo
    {
        return $this->belongsTo(HakMilik::class, 'hak_milik_baharu_id');
    }

    /** @return HasMany<PermohonanPeringkat, $this> */
    public function peringkat(): HasMany
    {
        return $this->hasMany(PermohonanPeringkat::class, 'permohonan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }

    public function semakLengkap(): bool
    {
        foreach ($this->senarai_semak_dokumen as $item) {
            if (! ($item['lengkap'] ?? false)) {
                return false;
            }
        }

        return true;
    }
}
