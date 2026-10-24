<?php

namespace App\Modules\Ptn\Models;

use App\Models\User;
use App\Modules\Ptn\Models\Concerns\UntukPengguna;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * SRS-PTN-03: one site visit and its result. Once status_pengesahan is
 * "disahkan" the row (and its checklist and photos) is locked by a database
 * trigger — SRS-PTN-10.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property string $id
 * @property string $no_rujukan
 * @property string|null $id_jadual
 * @property string $lot_id
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property int|null $id_templat
 * @property int|null $versi_templat
 * @property Carbon $tarikh_lawatan
 * @property Carbon|null $masa_mula
 * @property Carbon|null $masa_tamat
 * @property int|null $id_pegawai
 * @property string|null $ketepatan_gps_m
 * @property string|null $jarak_dari_lot_m
 * @property bool|null $dalam_lingkungan
 * @property string|null $keadaan_tapak
 * @property string|null $ringkasan_penemuan
 * @property string|null $isu_pencerobohan
 * @property string|null $isu_penyelenggaraan
 * @property string|null $cadangan_tindakan
 * @property string $status_lawatan
 * @property string $status_pengesahan
 * @property int|null $id_pegawai_pengesah
 * @property Carbon|null $tarikh_disahkan
 * @property string|null $sebab_konflik
 * @property array{penyelenggaraan?: list<array<string, mixed>>, cadangan?: list<array<string, mixed>>} $data_tambahan
 * @property string|null $id_luar_talian
 * @property string $sumber
 * @property Carbon|null $disegerak_pada
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property float|null $lat
 * @property float|null $lng
 * @property-read User|null $pegawai
 * @property-read User|null $pengesah
 * @property-read JadualPemantauan|null $jadual
 * @property-read TemplatSenaraiSemak|null $templat
 * @property-read Collection<int, ItemSenaraiSemak> $item
 * @property-read Collection<int, GambarLawatan> $gambar
 * @property-read Collection<int, IsuPemantauan> $isu
 * @property-read Collection<int, LaporanLawatan> $laporan
 * @property-read Collection<int, CadanganKemaskiniLot> $cadangan
 * @property-read Collection<int, KeperluanPenyelenggaraan> $penyelenggaraan
 */
class RekodLawatan extends PtnModel
{
    use HasUuids, ScopedByAccess, UntukPengguna;

    protected $table = 'rekod_lawatan';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = [
        'tarikh_lawatan' => 'date', 'masa_mula' => 'datetime', 'masa_tamat' => 'datetime', 'tarikh_disahkan' => 'datetime',
        'disegerak_pada' => 'datetime', 'dalam_lingkungan' => 'boolean', 'sintetik' => 'boolean', 'data_tambahan' => 'array',
    ];

    /** @param Builder<RekodLawatan> $query */
    public function scopeDenganKoordinat(Builder $query): void
    {
        $query->addSelect(['rekod_lawatan.*'])
            ->selectRaw('ST_Y(rekod_lawatan.koordinat_lawatan) AS lat, ST_X(rekod_lawatan.koordinat_lawatan) AS lng');
    }

    public function dikunci(): bool
    {
        return $this->status_pengesahan === 'disahkan';
    }

    public function bolehDiubah(): bool
    {
        return in_array($this->status_lawatan, ['draf', 'dikembalikan', 'konflik'], true);
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }

    /** @return BelongsTo<User, $this> */
    public function pengesah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_pengesah');
    }

    /** @return BelongsTo<JadualPemantauan, $this> */
    public function jadual(): BelongsTo
    {
        return $this->belongsTo(JadualPemantauan::class, 'id_jadual');
    }

    /** @return BelongsTo<TemplatSenaraiSemak, $this> */
    public function templat(): BelongsTo
    {
        return $this->belongsTo(TemplatSenaraiSemak::class, 'id_templat');
    }

    /** @return HasMany<ItemSenaraiSemak, $this> */
    public function item(): HasMany
    {
        return $this->hasMany(ItemSenaraiSemak::class, 'id_lawatan')->orderBy('id');
    }

    /** @return HasMany<GambarLawatan, $this> */
    public function gambar(): HasMany
    {
        return $this->hasMany(GambarLawatan::class, 'id_lawatan')->orderBy('id');
    }

    /** @return HasMany<IsuPemantauan, $this> */
    public function isu(): HasMany
    {
        return $this->hasMany(IsuPemantauan::class, 'id_lawatan');
    }

    /** @return HasMany<LaporanLawatan, $this> */
    public function laporan(): HasMany
    {
        return $this->hasMany(LaporanLawatan::class, 'id_lawatan')->orderBy('tarikh_jana');
    }

    /** @return HasMany<CadanganKemaskiniLot, $this> */
    public function cadangan(): HasMany
    {
        return $this->hasMany(CadanganKemaskiniLot::class, 'id_lawatan');
    }

    /** @return HasMany<KeperluanPenyelenggaraan, $this> */
    public function penyelenggaraan(): HasMany
    {
        return $this->hasMany(KeperluanPenyelenggaraan::class, 'id_lawatan');
    }
}
