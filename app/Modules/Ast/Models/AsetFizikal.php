<?php

namespace App\Modules\Ast\Models;

use App\Models\User;
use App\Modules\Ast\Models\Concerns\SkopLot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-01/02/04: one physical asset on a MAIS lot (1 lot : N assets,
 * parent → sub-asset). Never deleted — disposal archives it (status_arkib).
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(User $user)
 *
 * @property string $id
 * @property string $no_rujukan_aset
 * @property string $qr_ref
 * @property string $lot_id
 * @property string|null $id_aset_induk
 * @property int $id_kategori
 * @property string $jenis_aset
 * @property string $nama_aset
 * @property string|null $keterangan
 * @property string|null $alamat
 * @property string|null $latitud
 * @property string|null $longitud
 * @property int|null $tahun_pembinaan
 * @property int|null $tahun_perolehan
 * @property Carbon|null $tarikh_perolehan
 * @property string|null $saiz_keluasan
 * @property string $unit_ukuran
 * @property string|null $kos_perolehan
 * @property string|null $nilai_semasa
 * @property Carbon|null $tarikh_nilai_semasa
 * @property string $status_kondisi
 * @property int|null $id_pegawai_bertanggungjawab
 * @property Carbon|null $tarikh_pemeriksaan_terakhir
 * @property string $status_aset
 * @property string $status_kelulusan
 * @property string|null $id_aliran
 * @property bool $status_arkib
 * @property Carbon|null $tarikh_arkib
 * @property string|null $sebab_arkib
 * @property int|null $id_premis_pjk
 * @property int|null $id_sumber_gis
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property int|null $id_pencipta
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read AsetKategori $kategori
 * @property-read User|null $pegawai
 * @property-read AsetFizikal|null $induk
 */
class AsetFizikal extends AstModel
{
    use HasUuids, SkopLot;

    protected $table = 'aset_fizikal';

    protected $casts = [
        'tarikh_perolehan' => 'date', 'tarikh_nilai_semasa' => 'date', 'tarikh_pemeriksaan_terakhir' => 'date', 'tarikh_arkib' => 'date',
        'status_arkib' => 'boolean', 'sintetik' => 'boolean', 'kos_perolehan' => 'decimal:2', 'nilai_semasa' => 'decimal:2',
        'saiz_keluasan' => 'decimal:2', 'latitud' => 'decimal:7', 'longitud' => 'decimal:7',
    ];

    /** @return BelongsTo<AsetKategori, $this> */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(AsetKategori::class, 'id_kategori');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_bertanggungjawab');
    }

    /** @return BelongsTo<AsetFizikal, $this> */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(AsetFizikal::class, 'id_aset_induk');
    }

    /** @return HasMany<AsetFizikal, $this> */
    public function subaset(): HasMany
    {
        return $this->hasMany(AsetFizikal::class, 'id_aset_induk');
    }

    /** @return HasMany<AsetTag, $this> */
    public function tag(): HasMany
    {
        return $this->hasMany(AsetTag::class, 'id_aset');
    }

    /** @return HasMany<AsetQr, $this> */
    public function qr(): HasMany
    {
        return $this->hasMany(AsetQr::class, 'id_aset');
    }

    public function aktif(): bool
    {
        return ! $this->status_arkib;
    }
}
