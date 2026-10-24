<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-03/04/05: physical location and status of one original document.
 *
 * @property int $id
 * @property int $peti_besi_id
 * @property string $hak_milik_id
 * @property string $lot_id
 * @property string $jenis_dokumen_asal
 * @property string|null $no_fail
 * @property string|null $lokasi_rak
 * @property string|null $no_kotak_pengikat
 * @property string|null $catatan_lokasi
 * @property string $status_dokumen_asal
 * @property Carbon $tarikh_masuk_simpanan
 * @property int|null $id_pegawai_terima
 * @property-read PetiBesi $petiBesi
 * @property-read HakMilik $hakMilik
 * @property-read PengeluaranDokumen|null $pengeluaranTerbuka
 */
class DaftarPetiBesi extends Model implements Auditable, HasClassification
{
    protected $table = 'daftar_peti_besi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_masuk_simpanan' => 'date:Y-m-d'];
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

    /** @return BelongsTo<PetiBesi, $this> */
    public function petiBesi(): BelongsTo
    {
        return $this->belongsTo(PetiBesi::class, 'peti_besi_id');
    }

    /** @return BelongsTo<HakMilik, $this> */
    public function hakMilik(): BelongsTo
    {
        return $this->belongsTo(HakMilik::class, 'hak_milik_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawaiTerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_terima');
    }

    /** @return HasMany<PengeluaranDokumen, $this> */
    public function pengeluaran(): HasMany
    {
        return $this->hasMany(PengeluaranDokumen::class, 'daftar_peti_besi_id');
    }

    /** @return HasOne<PengeluaranDokumen, $this> */
    public function pengeluaranTerbuka(): HasOne
    {
        return $this->hasOne(PengeluaranDokumen::class, 'daftar_peti_besi_id')->where('status_pengeluaran', PengeluaranDokumen::DIKELUARKAN);
    }
}
