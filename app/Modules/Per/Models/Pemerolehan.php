<?php

namespace App\Modules\Per\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Per\Models\Concerns\AuditedByPer;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An acquisition case (SRS-PER-01). Classified Sulit; scoped by district and
 * bahagian (SRS Modul 4 "kawalan akses mengikut bahagian dan saluran").
 *
 * @property string $id
 * @property string $no_kes
 * @property int $id_saluran
 * @property string $tajuk
 * @property string|null $lot_id
 * @property string|null $no_lot_dijangka
 * @property string|null $kod_mukim
 * @property string|null $kod_daerah
 * @property Carbon $tarikh_mula_kes
 * @property Carbon|null $tarikh_pemerolehan
 * @property string|null $peringkat_semasa
 * @property string $status_pemerolehan
 * @property string $status_kelengkapan_dokumen
 * @property string $kod_bahagian
 * @property int|null $id_pegawai_bertanggungjawab
 * @property int|null $id_pendaftar
 * @property string|null $nilai_pasaran
 * @property string|null $nilai_pemerolehan
 * @property string|null $sumber_dana
 * @property string|null $rujukan_penilaian_jpph
 * @property array<string, mixed> $butiran_saluran
 * @property array<string, mixed>|null $butiran_peribadi
 * @property array<string, mixed>|null $butiran_hak_milik
 * @property string|null $id_aliran_kerja
 * @property string|null $id_hak_milik_dijana
 * @property Carbon|null $tarikh_selesai
 * @property int|null $tempoh_keseluruhan_hari
 * @property int $tempoh_sasaran_hari
 * @property string $sumber_rekod
 * @property string|null $rujukan_luaran
 * @property string|null $catatan
 * @property string $klasifikasi
 * @property int $versi_rekod
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Saluran $saluran
 * @property-read User|null $pegawai
 * @property-read User|null $pendaftar
 * @property-read Collection<int, PeringkatPemerolehan> $peringkat
 * @property-read Collection<int, DokumenPemerolehan> $dokumen
 * @property-read Collection<int, PihakBerkaitan> $pihak
 * @property-read Collection<int, Kelulusan> $kelulusan
 * @property-read Collection<int, BayaranNotis5a> $bayaran
 */
class Pemerolehan extends Model implements Auditable
{
    use AuditedByPer, HasUuids, ScopedByAccess, SoftDeletes;

    public const MENUNGGU_SEMAKAN = 'permohonan_awam_menunggu_semakan';

    public const DALAM_PROSES = 'dalam_proses';

    public const MENUNGGU_KELULUSAN = 'menunggu_kelulusan';

    public const DILULUSKAN = 'diluluskan';

    public const DITOLAK = 'ditolak';

    public const SELESAI = 'selesai';

    public const DIBATALKAN = 'dibatalkan';

    public const STATUS = [self::MENUNGGU_SEMAKAN, self::DALAM_PROSES, self::MENUNGGU_KELULUSAN, self::DILULUSKAN, self::DITOLAK, self::SELESAI, self::DIBATALKAN];

    public const AKTIF = [self::MENUNGGU_SEMAKAN, self::DALAM_PROSES, self::MENUNGGU_KELULUSAN, self::DILULUSKAN, self::DITOLAK];

    protected $table = 'pemerolehan';

    protected $guarded = ['id'];

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'bahagian' => 'kod_bahagian', 'klasifikasi' => 'klasifikasi'];

    /** @var list<string> */
    protected array $sensitive = ['butiran_peribadi'];

    protected $casts = [
        'tarikh_mula_kes' => 'date',
        'tarikh_pemerolehan' => 'date',
        'tarikh_selesai' => 'date',
        'butiran_saluran' => 'array',
        'butiran_peribadi' => 'encrypted:array',
        'butiran_hak_milik' => 'array',
        'nilai_pasaran' => 'decimal:2',
        'nilai_pemerolehan' => 'decimal:2',
        'tempoh_keseluruhan_hari' => 'integer',
        'tempoh_sasaran_hari' => 'integer',
        'versi_rekod' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $p) {
            $p->versi_rekod = (int) $p->versi_rekod + 1;
        });
    }

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at', 'versi_rekod'];
    }

    /**
     * Records $user may see — for code paths that act for a user other than
     * the authenticated one (profile sections, search, reminders).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->withoutGlobalScope('access');
        $access = app(AccessControl::class);
        foreach ($this->accessScopeColumns() as $type => $column) {
            $allowed = $access->scope($user, $type);
            if ($allowed !== null) {
                $query->whereIn($this->qualifyColumn($column), $allowed);
            }
        }

        return $query;
    }

    /** @return BelongsTo<Saluran, $this> */
    public function saluran(): BelongsTo
    {
        return $this->belongsTo(Saluran::class, 'id_saluran');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_bertanggungjawab');
    }

    /** @return BelongsTo<User, $this> */
    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pendaftar');
    }

    /** @return HasMany<PeringkatPemerolehan, $this> */
    public function peringkat(): HasMany
    {
        return $this->hasMany(PeringkatPemerolehan::class, 'id_pemerolehan')->orderBy('susunan');
    }

    /** @return HasMany<DokumenPemerolehan, $this> */
    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenPemerolehan::class, 'id_pemerolehan')->orderBy('id');
    }

    /** @return HasMany<PihakBerkaitan, $this> */
    public function pihak(): HasMany
    {
        return $this->hasMany(PihakBerkaitan::class, 'id_pemerolehan')->orderBy('id');
    }

    /** @return HasMany<Kelulusan, $this> */
    public function kelulusan(): HasMany
    {
        return $this->hasMany(Kelulusan::class, 'id_pemerolehan')->orderBy('tarikh_masa')->orderBy('id');
    }

    /** @return HasMany<BayaranNotis5a, $this> */
    public function bayaran(): HasMany
    {
        return $this->hasMany(BayaranNotis5a::class, 'id_pemerolehan')->orderBy('tarikh_bayaran')->orderBy('id');
    }

    public function kodSaluran(): string
    {
        return $this->saluran->kod_saluran;
    }

    public function peringkatSemasa(): ?PeringkatPemerolehan
    {
        return $this->peringkat->firstWhere('status_peringkat', PeringkatPemerolehan::SEMASA);
    }

    /** All channel fields (public + personal) as one map. */
    public function butiran(): array
    {
        return array_merge($this->butiran_saluran ?? [], $this->butiran_peribadi ?? []);
    }

    /** The value used for value-based approval layers and reports (SRS-PER-24/29). */
    public function nilaiUntukKelulusan(): float
    {
        $b = $this->butiran_saluran ?? [];
        foreach ([$this->nilai_pemerolehan, $b['harga_belian'] ?? null, $b['jumlah_premium'] ?? null, $b['nilai_wakaf'] ?? null, $b['nilai_harta_pusaka'] ?? null, $this->nilai_pasaran] as $v) {
            if ($v !== null && $v !== '') {
                return (float) $v;
            }
        }

        return 0.0;
    }

    public function pautan(): string
    {
        return '/admin/per/kes/'.$this->id;
    }
}
