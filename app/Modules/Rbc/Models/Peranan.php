<?php

namespace App\Modules\Rbc\Models;

use App\Models\User;
use App\Modules\Rbc\Services\RbcAccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PERANAN — a dynamic role (SRS-RBC-06). A new role holds nothing until
 * permissions are granted explicitly (SRS-RBC-09).
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string|null $kategori_rfp
 * @property string|null $keterangan
 * @property string $status
 * @property bool $boleh_padam
 */
class Peranan extends Model
{
    public const AKTIF = 'aktif';

    public const TIDAK_AKTIF = 'tidak_aktif';

    protected $table = 'peranan';

    protected $fillable = ['kod', 'nama', 'kategori_rfp', 'keterangan', 'status', 'boleh_padam'];

    protected $attributes = ['status' => self::AKTIF, 'boleh_padam' => true];

    protected static function booted(): void
    {
        static::saved(fn () => RbcAccessControl::flushAll());
        static::deleted(fn () => RbcAccessControl::flushAll());
    }

    protected function casts(): array
    {
        return ['boleh_padam' => 'boolean'];
    }

    /** @return HasMany<PerananKebenaran, $this> */
    public function kebenaran(): HasMany
    {
        return $this->hasMany(PerananKebenaran::class, 'peranan_id');
    }

    /** @return HasMany<PerananSkop, $this> */
    public function skop(): HasMany
    {
        return $this->hasMany(PerananSkop::class, 'peranan_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function pengguna(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pengguna_peranan', 'peranan_id', 'user_id')
            ->withPivot('sumber')
            ->withTimestamps();
    }

    /** @param Builder<Peranan> $query */
    public function scopeAktif(Builder $query): void
    {
        $query->where('status', self::AKTIF);
    }

    /** @return list<string> */
    public function kebenaranKeys(): array
    {
        return array_values($this->kebenaran()->orderBy('kebenaran')->pluck('kebenaran')->all());
    }
}
