<?php

namespace App\Modules\Wfe\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $id_definisi
 * @property string $kod
 * @property int $no_urutan
 * @property string $nama_peringkat
 * @property string $mod_kelulusan
 * @property int|null $kuorum
 * @property int|null $sla_nilai
 * @property string $sla_unit
 * @property bool $perlu_mfa
 * @property bool $perlu_justifikasi
 * @property-read Definisi $definisi
 * @property-read Collection<int, Pelulus> $pelulus
 * @property-read Collection<int, Eskalasi> $eskalasi
 */
class Peringkat extends Model implements Auditable
{
    use AuditedByWfe;

    public const SEMUA = 'semua';

    public const MANA_MANA = 'mana_mana';

    public const KUORUM = 'kuorum';

    protected $table = 'wf_peringkat';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['perlu_mfa' => 'boolean', 'perlu_justifikasi' => 'boolean'];
    }

    /** @return BelongsTo<Definisi, $this> */
    public function definisi(): BelongsTo
    {
        return $this->belongsTo(Definisi::class, 'id_definisi');
    }

    /** @return HasMany<Pelulus, $this> */
    public function pelulus(): HasMany
    {
        return $this->hasMany(Pelulus::class, 'id_peringkat')->orderBy('id');
    }

    /** @return HasMany<Eskalasi, $this> */
    public function eskalasi(): HasMany
    {
        return $this->hasMany(Eskalasi::class, 'id_peringkat')->orderBy('aras');
    }

    /** Approvals needed to complete the stage. */
    public function approvalsNeeded(): int
    {
        $slots = max(1, $this->pelulus->count());

        return match ($this->mod_kelulusan) {
            self::SEMUA => $slots,
            self::KUORUM => max(1, min($slots, (int) $this->kuorum)),
            default => 1,
        };
    }
}
