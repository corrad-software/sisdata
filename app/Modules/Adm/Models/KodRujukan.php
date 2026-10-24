<?php

namespace App\Modules\Adm\Models;

use App\Modules\Adm\Models\Concerns\AuditedByAdm;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $jenis_kod
 * @property string $kod
 * @property string $keterangan
 * @property string|null $kod_induk
 * @property int $susunan
 * @property string $status aktif | tidak_aktif
 * @property Carbon $tarikh_mula
 * @property Carbon|null $tarikh_tamat
 * @property int $versi
 * @property string|null $justifikasi
 * @property int|null $dikemas_kini_oleh
 * @property Carbon|null $updated_at
 */
class KodRujukan extends Model implements Auditable
{
    use AuditedByAdm;

    public const AKTIF = 'aktif';

    public const TIDAK_AKTIF = 'tidak_aktif';

    /** Fields snapshotted into adm_kod_rujukan_sejarah on every change. */
    public const VERSIONED = ['kod', 'keterangan', 'kod_induk', 'susunan', 'status', 'tarikh_mula', 'tarikh_tamat'];

    protected $table = 'adm_kod_rujukan';

    protected $fillable = ['jenis_kod', 'kod', 'keterangan', 'kod_induk', 'susunan', 'status', 'tarikh_mula', 'tarikh_tamat', 'versi', 'justifikasi', 'dikemas_kini_oleh'];

    protected function casts(): array
    {
        return [
            'susunan' => 'integer',
            'versi' => 'integer',
            'tarikh_mula' => 'date:Y-m-d',
            'tarikh_tamat' => 'date:Y-m-d',
        ];
    }

    /** @return HasMany<KodRujukanSejarah, $this> */
    public function sejarah(): HasMany
    {
        return $this->hasMany(KodRujukanSejarah::class, 'id_kod_rujukan');
    }
}
