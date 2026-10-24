<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-03: a vault (peti besi / bilik kebal).
 *
 * @property int $id
 * @property string $kod_peti_besi
 * @property string $nama
 * @property string $lokasi_bangunan
 * @property string|null $tingkat
 * @property string|null $bilik
 * @property int|null $id_pegawai_penjaga
 * @property int|null $kapasiti
 * @property string $status_peti_besi
 * @property Carbon|null $tarikh_pemeriksaan_akhir
 * @property string|null $catatan
 * @property-read User|null $penjaga
 */
class PetiBesi extends Model implements Auditable
{
    protected $table = 'peti_besi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_pemeriksaan_akhir' => 'date:Y-m-d', 'kapasiti' => 'integer'];
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

    /** @return BelongsTo<User, $this> */
    public function penjaga(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_penjaga');
    }

    /** @return HasMany<DaftarPetiBesi, $this> */
    public function daftar(): HasMany
    {
        return $this->hasMany(DaftarPetiBesi::class, 'peti_besi_id');
    }

    /** "Bangunan, Tingkat, Bilik" in one line. */
    public function lokasi(): string
    {
        return implode(', ', array_filter([$this->lokasi_bangunan, $this->tingkat ? 'Tingkat '.$this->tingkat : null, $this->bilik]));
    }
}
