<?php

namespace App\Modules\Wfe\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A running (or finished) workflow for one record (WF_INSTANS). Scoped by
 * district for monitoring views (SRS-RBC-07): kod_daerah is copied from the
 * start context.
 *
 * @property string $id
 * @property int $id_definisi
 * @property int $no_versi_definisi
 * @property string $kod_aliran
 * @property string $modul
 * @property string $jenis_subjek
 * @property string $id_rekod
 * @property string|null $tajuk
 * @property array<string, mixed>|null $konteks
 * @property string|null $kod_daerah
 * @property string|null $pautan
 * @property int $id_pemohon
 * @property string $status
 * @property string|null $keadaan
 * @property int|null $peringkat_semasa
 * @property int $kitaran
 * @property list<int>|null $laluan
 * @property Carbon $tarikh_mula
 * @property Carbon|null $tarikh_tamat
 * @property string|null $ulasan_keputusan
 * @property-read Definisi $definisi
 * @property-read Peringkat|null $peringkat
 * @property-read User $pemohon
 * @property-read Collection<int, Tugasan> $tugasan
 * @property-read Collection<int, Sejarah> $sejarah
 */
class Instans extends Model implements Auditable
{
    use AuditedByWfe, HasUuids;

    public const BERJALAN = 'berjalan';

    public const LULUS = 'lulus';

    public const TOLAK = 'tolak';

    public const DITARIK_BALIK = 'ditarik_balik';

    public const DIBATALKAN = 'dibatalkan';

    public const MENUNGGU_PEMOHON = 'menunggu_pemohon';

    protected $table = 'wf_instans';

    protected $guarded = [];

    /**
     * Record-level district scope (SRS-RBC-07), same rule as RBC's
     * ScopedByAccess trait: queries made for a logged-in user only see the
     * districts AccessControl allows; console/queue code is unfiltered.
     * (Applied here directly because the shared trait does not pass
     * Larastan level 5 yet — reported to MOD-RBC.)
     */
    protected static function booted(): void
    {
        static::addGlobalScope('access', function (Builder $query) {
            $user = auth()->user();
            if (! $user instanceof User) {
                return;
            }
            /** @var AccessControl $access */
            $access = app(AccessControl::class);
            $allowed = $access->scope($user, AccessControl::SCOPE_DAERAH);
            if ($allowed !== null) {
                $query->whereIn($query->getModel()->qualifyColumn('kod_daerah'), $allowed);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'konteks' => 'array',
            'laluan' => 'array',
            'tarikh_mula' => 'datetime',
            'tarikh_tamat' => 'datetime',
        ];
    }

    /** @return BelongsTo<Definisi, $this> */
    public function definisi(): BelongsTo
    {
        return $this->belongsTo(Definisi::class, 'id_definisi');
    }

    /** @return BelongsTo<Peringkat, $this> */
    public function peringkat(): BelongsTo
    {
        return $this->belongsTo(Peringkat::class, 'peringkat_semasa');
    }

    /** @return BelongsTo<User, $this> */
    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pemohon');
    }

    /** @return HasMany<Tugasan, $this> */
    public function tugasan(): HasMany
    {
        return $this->hasMany(Tugasan::class, 'id_instans')->orderBy('id');
    }

    /** @return HasMany<Sejarah, $this> */
    public function sejarah(): HasMany
    {
        return $this->hasMany(Sejarah::class, 'id_instans')->orderBy('id');
    }
}
