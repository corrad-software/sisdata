<?php

namespace App\Modules\Per\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use App\Modules\Per\Models\Concerns\AuditedByPer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $kod_saluran
 * @property string $nama_saluran
 * @property string $singkatan
 * @property string $rujukan_lampiran_h
 * @property string $ikon
 * @property string $warna
 * @property string $bahagian_bertanggungjawab
 * @property int $tempoh_sasaran_hari
 * @property string|null $keterangan
 * @property int $susunan
 * @property bool $status_aktif
 * @property-read Collection<int, PeringkatSaluran> $peringkat
 * @property-read Collection<int, SemakDokumen> $senaraiSemak
 */
class Saluran extends Model implements Auditable, HasClassification
{
    use AuditedByPer;

    protected $table = 'saluran_pemerolehan';

    protected $guarded = ['id'];

    protected $casts = ['status_aktif' => 'boolean', 'tempoh_sasaran_hari' => 'integer', 'susunan' => 'integer'];

    public function auditClassification(): string
    {
        return 'dalaman';
    }

    /** @return HasMany<PeringkatSaluran, $this> */
    public function peringkat(): HasMany
    {
        return $this->hasMany(PeringkatSaluran::class, 'id_saluran')->orderBy('susunan');
    }

    /** @return HasMany<SemakDokumen, $this> */
    public function senaraiSemak(): HasMany
    {
        return $this->hasMany(SemakDokumen::class, 'id_saluran')->orderBy('susunan_paparan');
    }

    public static function byCode(string $code): self
    {
        return static::query()->where('kod_saluran', $code)->firstOrFail();
    }
}
