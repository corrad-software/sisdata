<?php

namespace App\Modules\Dms\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * SRS-DMS-10: one logical document; file content lives in DokumenVersi.
 *
 * @property string $id
 * @property string $no_rujukan
 * @property int $kategori_id
 * @property string $modul_sumber
 * @property string|null $jenis_entiti_induk
 * @property string|null $id_rekod_induk
 * @property string|null $pautan_induk
 * @property string $tajuk
 * @property string $nama_fail
 * @property string $jenis_mime
 * @property string $sambungan
 * @property int $saiz_bait
 * @property string $cincangan_fail
 * @property string $klasifikasi_keselamatan
 * @property string $status
 * @property Carbon|null $tarikh_dokumen
 * @property Carbon|null $tarikh_luput
 * @property int|null $tahun
 * @property string|null $kod_daerah
 * @property string|null $kod_mukim
 * @property int|null $pemilik_proses_id
 * @property int|null $id_pencipta
 * @property int $versi_semasa
 * @property list<string> $kata_kunci
 * @property array<string, mixed> $metadata
 * @property string|null $teks_kandungan
 * @property string $status_indeks
 * @property string|null $kaedah_indeks
 * @property Carbon|null $amaran_luput_pada
 * @property Carbon|null $tarikh_arkib
 * @property Carbon|null $tarikh_lupus
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read DokumenKategori $kategori
 * @property-read DokumenVersi|null $versiSemasa
 */
class Dokumen extends Model implements Auditable
{
    use HasUuids, SoftDeletes;

    public const KLASIFIKASI = ['awam', 'dalaman', 'sulit', 'rahsia'];

    public const TERHAD = ['sulit', 'rahsia'];

    public const STATUS = ['aktif', 'tamat', 'diarkib', 'dilupus'];

    protected $table = 'dokumen';

    protected $guarded = ['id', 'carian'];

    protected $hidden = ['carian', 'teks_kandungan'];

    protected function casts(): array
    {
        return [
            'tarikh_dokumen' => 'date',
            'tarikh_luput' => 'date',
            'amaran_luput_pada' => 'datetime',
            'tarikh_arkib' => 'datetime',
            'tarikh_lupus' => 'datetime',
            'kata_kunci' => 'array',
            'metadata' => 'array',
            'saiz_bait' => 'integer',
            'versi_semasa' => 'integer',
            'tahun' => 'integer',
        ];
    }

    /** @return BelongsTo<DokumenKategori, $this> */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(DokumenKategori::class, 'kategori_id');
    }

    /** @return HasMany<DokumenVersi, $this> */
    public function versi(): HasMany
    {
        return $this->hasMany(DokumenVersi::class, 'dokumen_id')->orderByDesc('no_versi');
    }

    /** @return HasOne<DokumenVersi, $this> */
    public function versiSemasa(): HasOne
    {
        return $this->hasOne(DokumenVersi::class, 'dokumen_id')->ofMany('no_versi', 'max');
    }

    /** @return HasMany<DokumenKebenaran, $this> */
    public function kebenaran(): HasMany
    {
        return $this->hasMany(DokumenKebenaran::class, 'dokumen_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pencipta(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pencipta');
    }

    /** @return BelongsTo<User, $this> */
    public function pemilikProses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pemilik_proses_id');
    }

    public function isTerhad(): bool
    {
        return in_array($this->klasifikasi_keselamatan, self::TERHAD, true);
    }

    public function auditModule(): string
    {
        return 'dms';
    }

    public function auditExcept(): array
    {
        return ['carian', 'teks_kandungan', 'status_indeks', 'kaedah_indeks', 'updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
