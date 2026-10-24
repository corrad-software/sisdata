<?php

namespace App\Modules\Dms\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SRS-DMS-05/11/21: category with its own allowed types, size limit and
 * retention policy — all admin-editable.
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string|null $keterangan
 * @property list<string>|null $jenis_fail_dibenarkan
 * @property int|null $had_saiz_mb
 * @property int|null $tempoh_retensi_bulan
 * @property int|null $tempoh_arkib_bulan
 * @property string $tindakan_selepas_arkib
 * @property int|null $tempoh_amaran_luput_hari
 * @property string $klasifikasi_lalai
 * @property bool $aktif
 */
class DokumenKategori extends Model implements Auditable
{
    public const TINDAKAN = ['lupus', 'semak', 'kekal'];

    protected $table = 'dokumen_kategori';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jenis_fail_dibenarkan' => 'array',
            'aktif' => 'boolean',
            'had_saiz_mb' => 'integer',
            'tempoh_retensi_bulan' => 'integer',
            'tempoh_arkib_bulan' => 'integer',
            'tempoh_amaran_luput_hari' => 'integer',
        ];
    }

    /** @return HasMany<DokumenMedanMetadata, $this> */
    public function medan(): HasMany
    {
        return $this->hasMany(DokumenMedanMetadata::class, 'kategori_id');
    }

    public function auditModule(): string
    {
        return 'dms';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
