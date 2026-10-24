<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-01/02: one title (or TOL / reserve record) of a lot. Classified at
 * least Sulit (SRS §4.2) and scoped by daerah/mukim/klasifikasi (SRS-RBC-07).
 * Changes after registration go through PerubahanHakMilik + WFE (SRS-HKM-19).
 *
 * @property string $id
 * @property string $lot_id
 * @property string $no_hak_milik
 * @property string $jenis_hak_milik
 * @property string|null $kategori_tanah
 * @property string|null $syarat_nyata
 * @property string|null $keluasan
 * @property string $unit_keluasan
 * @property string $kod_mukim
 * @property string $kod_daerah
 * @property string $pemilik_berdaftar
 * @property Carbon|null $tarikh_pendaftaran
 * @property Carbon|null $tarikh_tamat_tempoh
 * @property int|null $tempoh_pajakan_tahun
 * @property string|null $pejabat_pendaftaran
 * @property string|null $no_perserahan
 * @property Carbon|null $tarikh_perserahan
 * @property string $status_semasa
 * @property string|null $id_hak_milik_digantikan
 * @property bool $disahkan
 * @property int|null $disahkan_oleh
 * @property Carbon|null $disahkan_pada
 * @property string $klasifikasi
 * @property int $versi_rekod
 * @property int|null $id_pendaftar
 * @property string|null $sumber_sistem
 * @property string|null $kunci_asal
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read HakMilik|null $digantikan
 * @property-read Collection<int, HakMilik> $pengganti
 * @property-read Collection<int, DaftarPetiBesi> $daftarPetiBesi
 * @property-read Collection<int, SekatanKepentingan> $sekatan
 * @property-read Collection<int, WarisPemilikBersama> $waris
 */
class HakMilik extends Model implements Auditable
{
    use HasUuids, ScopedByAccess, SoftDeletes;

    protected $table = 'hak_milik';

    protected $guarded = ['id'];

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    /** Fields snapshotted into versions (SRS-DAT-04). */
    public const MEDAN_VERSI = [
        'no_hak_milik', 'jenis_hak_milik', 'kategori_tanah', 'syarat_nyata', 'keluasan', 'unit_keluasan',
        'kod_mukim', 'kod_daerah', 'pemilik_berdaftar', 'tarikh_pendaftaran', 'tarikh_tamat_tempoh',
        'tempoh_pajakan_tahun', 'pejabat_pendaftaran', 'no_perserahan', 'tarikh_perserahan', 'status_semasa',
        'id_hak_milik_digantikan', 'disahkan',
    ];

    protected function casts(): array
    {
        return [
            'tarikh_pendaftaran' => 'date:Y-m-d',
            'tarikh_tamat_tempoh' => 'date:Y-m-d',
            'tarikh_perserahan' => 'date:Y-m-d',
            'disahkan' => 'boolean',
            'disahkan_pada' => 'datetime',
            'versi_rekod' => 'integer',
            'tempoh_pajakan_tahun' => 'integer',
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

    /** @return BelongsTo<HakMilik, $this> */
    public function digantikan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_hak_milik_digantikan');
    }

    /** @return HasMany<HakMilik, $this> */
    public function pengganti(): HasMany
    {
        return $this->hasMany(self::class, 'id_hak_milik_digantikan');
    }

    /** @return BelongsTo<User, $this> */
    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pendaftar');
    }

    /** @return HasMany<DaftarPetiBesi, $this> */
    public function daftarPetiBesi(): HasMany
    {
        return $this->hasMany(DaftarPetiBesi::class, 'hak_milik_id');
    }

    /** @return HasMany<SekatanKepentingan, $this> */
    public function sekatan(): HasMany
    {
        return $this->hasMany(SekatanKepentingan::class, 'hak_milik_id');
    }

    /** @return HasMany<WarisPemilikBersama, $this> */
    public function waris(): HasMany
    {
        return $this->hasMany(WarisPemilikBersama::class, 'hak_milik_id');
    }

    /** @return HasMany<SejarahStatusTanah, $this> */
    public function sejarahStatus(): HasMany
    {
        return $this->hasMany(SejarahStatusTanah::class, 'hak_milik_id');
    }

    /** @return HasMany<KronologiPemilikan, $this> */
    public function kronologi(): HasMany
    {
        return $this->hasMany(KronologiPemilikan::class, 'hak_milik_id');
    }

    /** @return HasMany<HakMilikVersi, $this> */
    public function versi(): HasMany
    {
        return $this->hasMany(HakMilikVersi::class, 'hak_milik_id');
    }

    /** @return HasMany<UrusanPendaftaranPt, $this> */
    public function urusan(): HasMany
    {
        return $this->hasMany(UrusanPendaftaranPt::class, 'hak_milik_id');
    }

    /** @return HasMany<PerubahanHakMilik, $this> */
    public function perubahan(): HasMany
    {
        return $this->hasMany(PerubahanHakMilik::class, 'hak_milik_id');
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $data = [];
        foreach (self::MEDAN_VERSI as $field) {
            $value = $this->getAttribute($field);
            $data[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        return $data;
    }
}
