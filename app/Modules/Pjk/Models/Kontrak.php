<?php

namespace App\Modules\Pjk\Models;

use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * SRS-PJK-01/02: a lease / rental agreement — MOD-PRO's `kontrak_pajakan`
 * skeleton, extended by MOD-PJK. Sulit; scoped by daerah/mukim/klasifikasi.
 * Column `status` is the SRS "status_kontrak", `deposit` is "amaun_deposit"
 * and `terma` is "terma_khas" (skeleton names kept for compatibility).
 *
 * @property string $id
 * @property string $lot_id
 * @property string|null $no_kontrak
 * @property int|null $premis_id
 * @property int|null $penyewa_id
 * @property string|null $penyewa
 * @property string $jenis_perjanjian
 * @property string|null $kategori_hartanah
 * @property Carbon|null $tarikh_mula
 * @property Carbon|null $tarikh_tamat
 * @property string|null $kadar
 * @property string|null $deposit
 * @property string|null $terma
 * @property string $kitaran_caj
 * @property int $hari_tempoh_bayar
 * @property string|null $nilai_kontrak
 * @property string $status
 * @property string $klasifikasi
 * @property string|null $kod_daerah
 * @property string|null $kod_mukim
 * @property string|null $bahagian
 * @property Carbon|null $tarikh_status
 * @property int $no_versi
 * @property int|null $id_versi_semasa
 * @property string|null $id_kontrak_asal
 * @property string|null $id_dokumen_perjanjian
 * @property string|null $id_instans_aliran
 * @property string $sumber
 * @property int|null $permohonan_id
 * @property Carbon|null $tarikh_penamatan
 * @property string|null $sebab_penamatan
 * @property int|null $id_pendaftar
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property-read Penyewa|null $penyewaRekod
 * @property-read PremisAset|null $premis
 */
class Kontrak extends PjkModel
{
    use HasUuids, ScopedByAccess, SoftDeletes;

    public const SUBJECT = 'kontrak_pajakan';

    protected $table = 'kontrak_pajakan';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    /** Snapshot fields for versions (SRS-PJK-10). */
    public const MEDAN_VERSI = ['no_kontrak', 'premis_id', 'penyewa_id', 'jenis_perjanjian', 'kategori_hartanah', 'tarikh_mula', 'tarikh_tamat', 'kadar', 'deposit', 'terma', 'kitaran_caj', 'hari_tempoh_bayar', 'status'];

    protected function casts(): array
    {
        return [
            'tarikh_mula' => 'date:Y-m-d',
            'tarikh_tamat' => 'date:Y-m-d',
            'tarikh_status' => 'date:Y-m-d',
            'tarikh_penamatan' => 'date:Y-m-d',
            'hari_tempoh_bayar' => 'integer',
            'no_versi' => 'integer',
            'sintetik' => 'boolean',
        ];
    }

    /** @return BelongsTo<Penyewa, $this> */
    public function penyewaRekod(): BelongsTo
    {
        return $this->belongsTo(Penyewa::class, 'penyewa_id');
    }

    /** @return BelongsTo<PremisAset, $this> */
    public function premis(): BelongsTo
    {
        return $this->belongsTo(PremisAset::class, 'premis_id');
    }

    /** @return HasMany<JadualCaj, $this> */
    public function caj(): HasMany
    {
        return $this->hasMany(JadualCaj::class, 'kontrak_id');
    }

    /** @return HasMany<LejarTransaksi, $this> */
    public function lejar(): HasMany
    {
        return $this->hasMany(LejarTransaksi::class, 'kontrak_id');
    }

    /** @return HasMany<DepositCagaran, $this> */
    public function depositRekod(): HasMany
    {
        return $this->hasMany(DepositCagaran::class, 'kontrak_id');
    }

    /** @return HasMany<SejarahStatusKontrak, $this> */
    public function sejarahStatus(): HasMany
    {
        return $this->hasMany(SejarahStatusKontrak::class, 'kontrak_id');
    }

    /** @return HasMany<VersiKontrak, $this> */
    public function versi(): HasMany
    {
        return $this->hasMany(VersiKontrak::class, 'kontrak_id');
    }

    /** @return HasMany<PindaanKontrak, $this> */
    public function pindaan(): HasMany
    {
        return $this->hasMany(PindaanKontrak::class, 'kontrak_id');
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $out = [];
        foreach (self::MEDAN_VERSI as $f) {
            $v = $this->getAttribute($f);
            $out[$f] = $v instanceof Carbon ? $v->format('Y-m-d') : $v;
        }

        return $out;
    }

    public function berkuatKuasa(): bool
    {
        return in_array($this->status, ['aktif', 'hampir_tamat'], true);
    }
}
