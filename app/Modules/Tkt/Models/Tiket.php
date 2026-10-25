<?php

namespace App\Modules\Tkt\Models;

use App\Models\User;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * SRS-TKT-04: one support ticket. `klasifikasi` (dalaman | sulit) is
 * recorded with each audit entry; a security issue is always sulit.
 *
 * @property string $id
 * @property string $nombor_rujukan
 * @property int|null $id_pelapor
 * @property string|null $emel_pelapor
 * @property string $saluran
 * @property string|null $rujukan_sumber
 * @property string $kategori
 * @property int $keterukan
 * @property string|null $kesan
 * @property string|null $kesegeraan
 * @property int|null $keterukan_dicadang
 * @property string|null $modul
 * @property string $tajuk
 * @property string $penerangan
 * @property string $status
 * @property string|null $status_sebelum_jeda
 * @property string $klasifikasi
 * @property bool $isu_keselamatan
 * @property int|null $id_pasukan
 * @property string $aras_sokongan
 * @property int|null $id_juruteknik
 * @property string|null $nama_vendor
 * @property string|null $rujukan_vendor
 * @property Carbon|null $tarikh_serah_vendor
 * @property int|null $id_sla_konfigurasi
 * @property string $waktu_perkhidmatan
 * @property Carbon $tarikh_buka
 * @property Carbon|null $tarikh_sasaran_respons
 * @property Carbon|null $tarikh_sasaran_selesai
 * @property Carbon|null $tarikh_respons_pertama
 * @property Carbon|null $tarikh_selesai
 * @property Carbon|null $tarikh_disahkan
 * @property Carbon|null $tarikh_tutup
 * @property Carbon|null $sla_jeda_mula
 * @property int $sla_jeda_minit
 * @property bool $respons_dilepasi
 * @property bool $selesai_dilepasi
 * @property string|null $sebab_lepas_sla
 * @property int $peringkat_eskalasi
 * @property string|null $punca_akar
 * @property string|null $penyelesaian
 * @property string|null $tindakan_pembetulan
 * @property int|null $masa_henti_minit
 * @property string|null $versi_sistem
 * @property string|null $id_tiket_induk
 * @property string|null $id_tiket_pendua_dicadang
 * @property float|null $skor_pendua
 * @property int $bilangan_buka_semula
 * @property Carbon|null $peringatan_pengesahan_pada
 * @property bool $ditutup_automatik
 * @property int|null $kepuasan_skor
 * @property string|null $kepuasan_ulasan
 * @property Carbon|null $kepuasan_pada
 * @property string|null $id_instans_aliran
 * @property string|null $status_kelulusan
 * @property int|null $id_artikel_kb
 * @property string|null $id_aduan_adu
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property-read User|null $pelapor
 * @property-read User|null $juruteknik
 * @property-read Pasukan|null $pasukan
 * @property-read Konteks|null $konteks
 * @property-read PautanAduan|null $pautanAduan
 */
class Tiket extends TktModel
{
    use HasUuids;

    protected $table = 'tkt_tiket';

    /** Mirrors the column defaults so a freshly created model is complete. */
    protected $attributes = [
        'status' => 'baharu',
        'klasifikasi' => 'dalaman',
        'isu_keselamatan' => false,
        'aras_sokongan' => 'L1',
        'waktu_perkhidmatan' => 'waktu_pejabat',
        'sla_jeda_minit' => 0,
        'respons_dilepasi' => false,
        'selesai_dilepasi' => false,
        'peringkat_eskalasi' => 0,
        'bilangan_buka_semula' => 0,
        'ditutup_automatik' => false,
        'sintetik' => false,
    ];

    protected function casts(): array
    {
        return [
            'keterukan' => 'integer',
            'keterukan_dicadang' => 'integer',
            'isu_keselamatan' => 'boolean',
            'respons_dilepasi' => 'boolean',
            'selesai_dilepasi' => 'boolean',
            'ditutup_automatik' => 'boolean',
            'sintetik' => 'boolean',
            'skor_pendua' => 'float',
            'sla_jeda_minit' => 'integer',
            'peringkat_eskalasi' => 'integer',
            'bilangan_buka_semula' => 'integer',
            'kepuasan_skor' => 'integer',
            'masa_henti_minit' => 'integer',
            'tarikh_serah_vendor' => 'datetime',
            'tarikh_buka' => 'datetime',
            'tarikh_sasaran_respons' => 'datetime',
            'tarikh_sasaran_selesai' => 'datetime',
            'tarikh_respons_pertama' => 'datetime',
            'tarikh_selesai' => 'datetime',
            'tarikh_disahkan' => 'datetime',
            'tarikh_tutup' => 'datetime',
            'sla_jeda_mula' => 'datetime',
            'peringatan_pengesahan_pada' => 'datetime',
            'kepuasan_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pelapor');
    }

    /** @return BelongsTo<User, $this> */
    public function juruteknik(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_juruteknik');
    }

    /** @return BelongsTo<Pasukan, $this> */
    public function pasukan(): BelongsTo
    {
        return $this->belongsTo(Pasukan::class, 'id_pasukan');
    }

    /** @return HasOne<Konteks, $this> */
    public function konteks(): HasOne
    {
        return $this->hasOne(Konteks::class, 'id_tiket');
    }

    /** @return HasMany<Kronologi, $this> */
    public function kronologi(): HasMany
    {
        return $this->hasMany(Kronologi::class, 'id_tiket');
    }

    /** @return HasMany<Lampiran, $this> */
    public function lampiran(): HasMany
    {
        return $this->hasMany(Lampiran::class, 'id_tiket');
    }

    /** @return HasMany<Eskalasi, $this> */
    public function eskalasi(): HasMany
    {
        return $this->hasMany(Eskalasi::class, 'id_tiket');
    }

    /** @return HasOne<PautanAduan, $this> */
    public function pautanAduan(): HasOne
    {
        return $this->hasOne(PautanAduan::class, 'id_tiket');
    }

    public function terbuka(): bool
    {
        return in_array($this->status, Lalai::TERBUKA, true);
    }

    public function terhad(): bool
    {
        return $this->klasifikasi === 'sulit' || $this->isu_keselamatan;
    }

    /** @param Builder<Tiket> $query */
    public function scopeTerbuka(Builder $query): void
    {
        $query->whereIn('tkt_tiket.status', Lalai::TERBUKA);
    }

    /**
     * SRS-TKT-20: reporters see their own tickets; support staff see the
     * tickets assigned to them and those of their teams / team modules;
     * `tkt.semua.pantau` (support management) sees every ticket. Classified
     * or security tickets need `tkt.sulit.lihat` unless you reported them.
     *
     * @param  Builder<Tiket>  $query
     */
    public function scopeBolehDilihat(Builder $query, User $user): void
    {
        $own = $user->can('tkt.tiket.lihat');
        $all = $user->can('tkt.semua.pantau');
        $support = $all || $user->can('tkt.sokongan.lihat');
        $sulit = $user->can('tkt.sulit.lihat');

        if (! $own && ! $support) {
            $query->whereRaw('1 = 0');

            return;
        }

        [$teams, $modules, $everyModule] = self::liputanPasukan($user);

        $query->where(function (Builder $w) use ($user, $own, $support, $all, $sulit, $teams, $modules, $everyModule) {
            if ($own) {
                $w->orWhere('tkt_tiket.id_pelapor', $user->id);
            }
            if ($support) {
                $w->orWhere(function (Builder $s) use ($user, $all, $sulit, $teams, $modules, $everyModule) {
                    if (! $all) {
                        $s->where(function (Builder $v) use ($user, $teams, $modules, $everyModule) {
                            $v->where('tkt_tiket.id_juruteknik', $user->id);
                            if ($teams !== []) {
                                $v->orWhereIn('tkt_tiket.id_pasukan', $teams);
                            }
                            if ($everyModule) {
                                $v->orWhereRaw('1 = 1');
                            } elseif ($modules !== []) {
                                $v->orWhereIn('tkt_tiket.modul', $modules);
                            }
                        });
                    }
                    if (! $sulit) {
                        $s->where('tkt_tiket.klasifikasi', '!=', 'sulit')->where('tkt_tiket.isu_keselamatan', false);
                    }
                });
            }
        });
    }

    /**
     * Teams the user is an active member of, the modules they cover and
     * whether one of them covers every module.
     *
     * @return array{0: list<int>, 1: list<string>, 2: bool}
     */
    public static function liputanPasukan(User $user): array
    {
        $teams = Pasukan::query()->where('aktif', true)
            ->whereIn('id', AhliPasukan::query()->where('id_pengguna', $user->id)->where('aktif', true)->select('id_pasukan'))
            ->get(['id', 'modul']);
        $modules = [];
        $every = false;
        foreach ($teams as $team) {
            if ($team->modul === null || $team->modul === []) {
                $every = true;
            } else {
                array_push($modules, ...$team->modul);
            }
        }

        return [$teams->pluck('id')->map(fn ($id) => (int) $id)->all(), array_values(array_unique($modules)), $every];
    }
}
