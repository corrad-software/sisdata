<?php

namespace App\Modules\Adu\Models;

use App\Models\User;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SRS-ADU-01: one complaint/issue in the single queue, whatever the channel.
 * Dalaman classification (§4.1); scoped by daerah/mukim/klasifikasi
 * (SRS-RBC-07). Reporter identity is NOT here — it lives encrypted in
 * Pengadu (SRS-ADU-20/21).
 *
 * @property string $id
 * @property string|null $no_rujukan_aduan
 * @property string $saluran_penerimaan
 * @property Carbon|null $tarikh_terima
 * @property Carbon|null $tarikh_aduan
 * @property string|null $lot_id
 * @property string|null $kategori
 * @property string|null $subkategori
 * @property string|null $kategori_dicadang
 * @property string|null $keterangan
 * @property string|null $lokasi_teks
 * @property string|null $kod_daerah
 * @property string|null $kod_mukim
 * @property string $status
 * @property string $tahap_keutamaan
 * @property bool $status_tanpa_nama
 * @property bool $status_bukan_tanah_mais
 * @property string|null $kaedah_padanan_lot
 * @property string|null $jawapan_automatik
 * @property array<int, array{nama: string, jenis: string}>|null $agensi_dicadang
 * @property bool $jawapan_dihantar
 * @property string|null $id_aduan_induk
 * @property string|null $id_aduan_pendua_dicadang
 * @property int|null $skor_pendua
 * @property int|null $id_pegawai
 * @property int|null $id_penyelia
 * @property int|null $id_pencipta
 * @property string|null $tindakan_susulan
 * @property string $klasifikasi
 * @property string|null $id_aduan_sistem_luaran
 * @property string|null $rujukan_sumber
 * @property string|null $id_instans_aliran
 * @property int $bilangan_buka_semula
 * @property Carbon|null $tarikh_tutup
 * @property string|null $bahagian_dirujuk
 * @property bool $sintetik
 * @property float|null $lat
 * @property float|null $lng
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $pegawai
 * @property-read User|null $penyelia
 * @property-read Pengadu|null $pengadu
 * @property-read JejakSla|null $slaSemasa
 * @property-read Collection<int, KronologiAduan> $kronologi
 * @property-read Collection<int, JejakSla> $jejakSla
 * @property-read Collection<int, EskalasiAduan> $eskalasi
 * @property-read Collection<int, LawatanTapak> $lawatan
 * @property-read Collection<int, ResolusiAduan> $resolusi
 * @property-read Collection<int, RujukanAduan> $rujukan
 * @property-read Collection<int, DokumenAduan> $dokumen
 */
class Aduan extends AduModel
{
    use HasUuids, ScopedByAccess, SoftDeletes;

    protected $table = 'aduan';

    protected $hidden = ['koordinat'];

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected function casts(): array
    {
        return [
            'tarikh_terima' => 'datetime',
            'tarikh_aduan' => 'date:Y-m-d',
            'tarikh_tutup' => 'datetime',
            'status_tanpa_nama' => 'boolean',
            'status_bukan_tanah_mais' => 'boolean',
            'jawapan_dihantar' => 'boolean',
            'sintetik' => 'boolean',
            'agensi_dicadang' => 'array',
            'bilangan_buka_semula' => 'integer',
            'skor_pendua' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // lat/lng are computed columns (scopeDenganKoordinat), never stored.
        static::saving(function (Aduan $aduan) {
            foreach (['lat', 'lng'] as $virtual) {
                if (array_key_exists($virtual, $aduan->getAttributes())) {
                    $aduan->offsetUnset($virtual);
                }
            }
        });
    }

    public function auditExcept(): array
    {
        return ['updated_at', 'koordinat', 'lat', 'lng'];
    }

    /** Select lat/lng from the PostGIS point alongside the row. */
    public function scopeDenganKoordinat(Builder $query): Builder
    {
        return $query->select('aduan.*')->selectRaw('ST_Y(aduan.koordinat) AS lat, ST_X(aduan.koordinat) AS lng');
    }

    /**
     * Records $user may see — the same rule as the ScopedByAccess global
     * scope, but for an explicit user (GIS layers, KPIs, profile sections
     * and search get the user as a parameter, not from the session).
     */
    public function scopeBolehDilihat(Builder $query, User $user): Builder
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

    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->whereIn('aduan.status', Lalai::STATUS_TERBUKA);
    }

    public function setKoordinat(?float $lat, ?float $lng): void
    {
        $this->setAttribute('koordinat', $lat === null || $lng === null
            ? null
            : DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lng, $lat)));
    }

    /** @return array{lat: float, lng: float}|null */
    public function latLng(): ?array
    {
        if ($this->getAttribute('lat') !== null && $this->getAttribute('lng') !== null) {
            return ['lat' => (float) $this->getAttribute('lat'), 'lng' => (float) $this->getAttribute('lng')];
        }
        $row = DB::selectOne('SELECT ST_Y(koordinat) AS lat, ST_X(koordinat) AS lng FROM aduan WHERE id = ? AND koordinat IS NOT NULL', [$this->id]);

        return $row ? ['lat' => (float) $row->lat, 'lng' => (float) $row->lng] : null;
    }

    public function terbuka(): bool
    {
        return in_array($this->status, Lalai::STATUS_TERBUKA, true);
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }

    /** @return BelongsTo<User, $this> */
    public function penyelia(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_penyelia');
    }

    /** @return HasOne<Pengadu, $this> */
    public function pengadu(): HasOne
    {
        return $this->hasOne(Pengadu::class, 'aduan_id');
    }

    /** @return HasMany<KronologiAduan, $this> */
    public function kronologi(): HasMany
    {
        return $this->hasMany(KronologiAduan::class, 'aduan_id')->orderBy('tarikh_masa')->orderBy('id');
    }

    /** @return HasMany<JejakSla, $this> */
    public function jejakSla(): HasMany
    {
        return $this->hasMany(JejakSla::class, 'aduan_id')->orderBy('kitaran');
    }

    /** @return HasOne<JejakSla, $this> */
    public function slaSemasa(): HasOne
    {
        return $this->hasOne(JejakSla::class, 'aduan_id')->where('semasa', true);
    }

    /** @return HasMany<EskalasiAduan, $this> */
    public function eskalasi(): HasMany
    {
        return $this->hasMany(EskalasiAduan::class, 'aduan_id')->orderBy('tarikh_eskalasi');
    }

    /** @return HasMany<LawatanTapak, $this> */
    public function lawatan(): HasMany
    {
        return $this->hasMany(LawatanTapak::class, 'aduan_id')->orderBy('tarikh_jadual');
    }

    /** @return HasMany<ResolusiAduan, $this> */
    public function resolusi(): HasMany
    {
        return $this->hasMany(ResolusiAduan::class, 'aduan_id')->orderBy('id');
    }

    /** @return HasMany<RujukanAduan, $this> */
    public function rujukan(): HasMany
    {
        return $this->hasMany(RujukanAduan::class, 'aduan_id')->orderBy('id');
    }

    /** @return HasMany<DokumenAduan, $this> */
    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenAduan::class, 'aduan_id')->orderBy('id');
    }
}
