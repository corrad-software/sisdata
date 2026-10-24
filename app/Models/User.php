<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Models\PenggunaSkop;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Services\RoleService;
use App\Modules\Rbc\Support\BaseRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * PENGGUNA (SRS Modul 18). Authorisation comes ONLY from roles/permissions
 * via App\Modules\Rbc\Contracts\AccessControl (`$user->can('x.y.z')`).
 *
 * Transition: the legacy `role` ('admin'/'user') and `is_active` columns are
 * kept in sync but never consulted for access — setting role='admin' grants
 * the Pentadbir Sistem role (and 'user' removes it); is_active mirrors
 * status_akaun === 'aktif'.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string|null $oidc_sub
 * @property string $role
 * @property bool $is_active
 * @property string $status_akaun
 * @property string $jenis_pengesahan
 * @property string|null $no_pekerja
 * @property string|null $bahagian
 * @property string|null $unit
 * @property string|null $jawatan
 * @property Carbon|null $tarikh_mula_capaian
 * @property Carbon|null $tarikh_tamat_capaian
 * @property Carbon|null $tarikh_log_masuk_akhir
 * @property Carbon|null $tarikh_diaktifkan_semula
 * @property bool $mfa_didaftar
 * @property Carbon|null $kata_laluan_ditukar_pada
 * @property bool $wajib_tukar_kata_laluan
 * @property int $cubaan_gagal
 * @property Carbon|null $cubaan_gagal_pertama
 * @property Carbon|null $dikunci_hingga
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'status_akaun',
        'jenis_pengesahan',
        'no_pekerja',
        'bahagian',
        'unit',
        'jawatan',
        'tarikh_mula_capaian',
        'tarikh_tamat_capaian',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'mfa_didaftar' => 'boolean',
            'wajib_tukar_kata_laluan' => 'boolean',
            'cubaan_gagal' => 'integer',
            'tarikh_mula_capaian' => 'date',
            'tarikh_tamat_capaian' => 'date',
            'tarikh_log_masuk_akhir' => 'datetime',
            'tarikh_diaktifkan_semula' => 'datetime',
            'kata_laluan_ditukar_pada' => 'datetime',
            'cubaan_gagal_pertama' => 'datetime',
            'dikunci_hingga' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep the legacy is_active flag and status_akaun consistent. Status
        // changes with logging/session termination go through
        // App\Modules\Rbc\Services\AccountStatusService.
        static::saving(function (User $user) {
            if (! $user->exists && $user->getAttribute('status_akaun') === null) {
                $user->status_akaun = ($user->getAttribute('is_active') ?? true) ? AccountStatus::AKTIF : AccountStatus::NYAHAKTIF;
            }

            if ($user->isDirty('status_akaun')) {
                $user->is_active = $user->status_akaun === AccountStatus::AKTIF;
            } elseif ($user->isDirty('is_active')) {
                $user->status_akaun = $user->is_active ? AccountStatus::AKTIF : AccountStatus::NYAHAKTIF;
            }
        });

        static::saved(function (User $user) {
            if (! $user->wasChanged('role') && ! ($user->wasRecentlyCreated && $user->role === 'admin')) {
                return;
            }

            $roles = app(RoleService::class);
            if ($user->role === 'admin') {
                $roles->assignRoleByCode($user, BaseRoles::PENTADBIR_SISTEM);
            } else {
                $roles->removeRoleByCode($user, BaseRoles::PENTADBIR_SISTEM);
            }
        });
    }

    /** @return BelongsToMany<Peranan, $this> */
    public function peranan(): BelongsToMany
    {
        return $this->belongsToMany(Peranan::class, 'pengguna_peranan', 'user_id', 'peranan_id')
            ->withPivot('sumber')
            ->withTimestamps();
    }

    /** @return HasMany<PenggunaSkop, $this> */
    public function skop(): HasMany
    {
        return $this->hasMany(PenggunaSkop::class);
    }

    public function isActiveAccount(): bool
    {
        return ($this->status_akaun ?? AccountStatus::AKTIF) === AccountStatus::AKTIF;
    }
}
