<?php

namespace App\Modules\Kes\Models;

use App\Models\User;
use App\Modules\Kes\Services\Rujukan;
use App\Modules\Kes\Support\Akses;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * SRS-KES-02: one court case / legal matter. Rahsia (SRS-KES-14); scoped by
 * daerah/mukim (of the primary lot) and klasifikasi through ScopedByAccess,
 * plus the legal-panel rule in Support\Akses (external counsel see only the
 * cases assigned to their firm). Never deleted — closure is archiving
 * (status_arkib), enforced by a DB trigger.
 *
 * @property string $id
 * @property string|null $no_rujukan
 * @property string $no_kes
 * @property string|null $tajuk
 * @property string|null $jenis
 * @property string|null $mahkamah
 * @property string|null $peringkat_mahkamah
 * @property Carbon|null $tarikh_pemfailan
 * @property string|null $ringkasan_tuntutan
 * @property string|null $nilai_tuntutan
 * @property string|null $peranan_mais
 * @property string $status
 * @property Carbon|null $tarikh_status
 * @property string|null $kos_guaman
 * @property string $klasifikasi
 * @property int|null $id_pegawai_bertanggungjawab
 * @property int|null $id_pencipta
 * @property bool $status_arkib
 * @property Carbon|null $tarikh_arkib
 * @property string|null $sebab_arkib
 * @property string|null $kod_daerah
 * @property string|null $kod_mukim
 * @property Carbon|null $tarikh_prosiding_berikutnya
 * @property Carbon|null $tarikh_keputusan
 * @property string $sumber
 * @property string|null $rujukan_sumber
 * @property string|null $id_instans_aliran
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $pegawai
 */
class KesMahkamah extends KesModel
{
    use HasUuids, ScopedByAccess, SoftDeletes;

    protected $table = 'kes_mahkamah';

    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected function casts(): array
    {
        return [
            'tarikh_pemfailan' => 'date:Y-m-d',
            'tarikh_status' => 'datetime',
            'tarikh_arkib' => 'datetime',
            'tarikh_prosiding_berikutnya' => 'date:Y-m-d',
            'tarikh_keputusan' => 'date:Y-m-d',
            'status_arkib' => 'boolean',
            'sintetik' => 'boolean',
        ];
    }

    /** Explicit-user variant of the access scope (KPIs, reminders, profile sections may run without auth()). */
    public function scopeBolehDilihat(Builder $query, User $user): Builder
    {
        return app(Akses::class)->hadkan($query->withoutGlobalScope('access'), $user);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNotIn('kes_mahkamah.status', app(Rujukan::class)->statusTerminal());
    }

    public function terminal(): bool
    {
        return in_array($this->status, app(Rujukan::class)->statusTerminal(), true);
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_bertanggungjawab');
    }

    /** @return HasMany<Pihak, $this> */
    public function pihak(): HasMany
    {
        return $this->hasMany(Pihak::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<Peguam, $this> */
    public function peguam(): HasMany
    {
        return $this->hasMany(Peguam::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<Prosiding, $this> */
    public function prosiding(): HasMany
    {
        return $this->hasMany(Prosiding::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<TarikhPenting, $this> */
    public function tarikhPenting(): HasMany
    {
        return $this->hasMany(TarikhPenting::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<KosGuaman, $this> */
    public function kos(): HasMany
    {
        return $this->hasMany(KosGuaman::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<Keputusan, $this> */
    public function keputusan(): HasMany
    {
        return $this->hasMany(Keputusan::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<TindakanSusulan, $this> */
    public function tindakan(): HasMany
    {
        return $this->hasMany(TindakanSusulan::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<Rayuan, $this> */
    public function rayuan(): HasMany
    {
        return $this->hasMany(Rayuan::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<Penyelesaian, $this> */
    public function penyelesaian(): HasMany
    {
        return $this->hasMany(Penyelesaian::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<DokumenKes, $this> */
    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenKes::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<StatusSejarah, $this> */
    public function sejarahStatus(): HasMany
    {
        return $this->hasMany(StatusSejarah::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<PegawaiSejarah, $this> */
    public function sejarahPegawai(): HasMany
    {
        return $this->hasMany(PegawaiSejarah::class, 'kes_mahkamah_id');
    }

    /** @return HasMany<Kronologi, $this> */
    public function kronologi(): HasMany
    {
        return $this->hasMany(Kronologi::class, 'kes_mahkamah_id');
    }
}
