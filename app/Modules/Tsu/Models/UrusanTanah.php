<?php

namespace App\Modules\Tsu\Models;

use App\Models\User;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use App\Modules\Tsu\Models\Concerns\UntukPengguna;
use App\Modules\Tsu\Support\Lalai;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One Land Office matter (SRS §4.1 `permohonan_tanah`, SRS §10 `urusan_tanah`):
 * a typed, staged process on one or more lots (urusan_lot). Scoped by the
 * daerah / mukim / klasifikasi of the (first) lot. Never deleted; locked when closed.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(User $user)
 *
 * @property string $id
 * @property string $jenis_urusan
 * @property int $templat_id
 * @property string $no_rujukan_mais
 * @property string|null $no_rujukan_ptd
 * @property string|null $no_rujukan_etanah
 * @property string|null $pejabat_tanah
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string|null $bahagian_pemilik
 * @property string $klasifikasi
 * @property string $tajuk
 * @property string|null $keterangan
 * @property array<string, mixed> $butiran
 * @property string|null $kaedah_pelupusan
 * @property string|null $nilai_penilaian
 * @property string|null $justifikasi
 * @property int|null $peringkat_semasa
 * @property string $status
 * @property string|null $id_aliran_kerja
 * @property string|null $status_ptd
 * @property string|null $sumber_status_ptd
 * @property Carbon|null $tarikh_status_ptd
 * @property Carbon|null $tarikh_susulan_ptd
 * @property Carbon $tarikh_mula
 * @property Carbon $tarikh_sasaran_siap
 * @property Carbon|null $tarikh_siap
 * @property Carbon|null $tarikh_tutup
 * @property bool $dikunci
 * @property int|null $id_pegawai_bertanggungjawab
 * @property int|null $id_pendaftar
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property-read Collection<int, UrusanLot> $lot
 * @property-read Collection<int, UrusanPeringkat> $peringkat
 * @property-read Collection<int, KosUrusan> $kos
 * @property-read Collection<int, UrusanDokumen> $dokumen
 * @property-read Collection<int, UrusanKeputusan> $keputusan
 * @property-read User|null $pegawai
 * @property-read TemplatProses $templat
 */
class UrusanTanah extends TsuModel
{
    use HasUuids, ScopedByAccess, UntukPengguna;

    protected $table = 'urusan_tanah';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = [
        'butiran' => 'array', 'nilai_penilaian' => 'decimal:2', 'tarikh_status_ptd' => 'datetime', 'tarikh_susulan_ptd' => 'date',
        'tarikh_mula' => 'date', 'tarikh_sasaran_siap' => 'date', 'tarikh_siap' => 'date', 'tarikh_tutup' => 'datetime',
        'dikunci' => 'boolean', 'sintetik' => 'boolean',
    ];

    public function auditSensitive(): array
    {
        return ['nilai_penilaian'];
    }

    public function terbuka(): bool
    {
        return in_array($this->status, Lalai::URUSAN_TERBUKA, true);
    }

    public function jenisLabel(): string
    {
        return Lalai::JENIS_URUSAN[$this->jenis_urusan] ?? $this->jenis_urusan;
    }

    /** @return HasMany<UrusanLot, $this> */
    public function lot(): HasMany
    {
        return $this->hasMany(UrusanLot::class, 'urusan_id');
    }

    /** @return HasMany<UrusanPeringkat, $this> */
    public function peringkat(): HasMany
    {
        return $this->hasMany(UrusanPeringkat::class, 'urusan_id')->orderBy('no_urutan');
    }

    /** @return HasMany<KosUrusan, $this> */
    public function kos(): HasMany
    {
        return $this->hasMany(KosUrusan::class, 'urusan_id');
    }

    /** @return HasMany<UrusanDokumen, $this> */
    public function dokumen(): HasMany
    {
        return $this->hasMany(UrusanDokumen::class, 'urusan_id');
    }

    /** @return HasMany<UrusanKeputusan, $this> */
    public function keputusan(): HasMany
    {
        return $this->hasMany(UrusanKeputusan::class, 'urusan_id')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_bertanggungjawab');
    }

    /** @return BelongsTo<TemplatProses, $this> */
    public function templat(): BelongsTo
    {
        return $this->belongsTo(TemplatProses::class, 'templat_id');
    }

    public function peringkatAktif(): ?UrusanPeringkat
    {
        return $this->peringkat->first(fn (UrusanPeringkat $p) => $p->no_urutan === $this->peringkat_semasa);
    }
}
