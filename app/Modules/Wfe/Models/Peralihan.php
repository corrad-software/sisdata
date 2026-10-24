<?php

namespace App\Modules\Wfe\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A transition (graph edge) between "mula", stages and "tamat". Conditions
 * (WF_SYARAT) decide whether it is taken; the first matching edge by
 * keutamaan wins, an edge without conditions is the default.
 *
 * @property int $id
 * @property int $id_definisi
 * @property string $dari
 * @property string $ke
 * @property int $keutamaan
 * @property string $logik
 * @property string|null $label
 * @property-read Collection<int, Syarat> $syarat
 */
class Peralihan extends Model implements Auditable
{
    use AuditedByWfe;

    public const MULA = 'mula';

    public const TAMAT = 'tamat';

    protected $table = 'wf_peralihan';

    protected $guarded = ['id'];

    /** @return HasMany<Syarat, $this> */
    public function syarat(): HasMany
    {
        return $this->hasMany(Syarat::class, 'id_peralihan')->orderBy('id');
    }
}
