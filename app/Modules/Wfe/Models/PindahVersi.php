<?php

namespace App\Modules\Wfe\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-WFE-12: request to move a running instance onto another version;
 * applied only after a second administrator approves.
 *
 * @property int $id
 * @property string $id_instans
 * @property int $id_definisi_asal
 * @property int $id_definisi_baru
 * @property string $sebab
 * @property string $status menunggu | lulus | tolak
 * @property int $diminta_oleh
 * @property int|null $diputuskan_oleh
 * @property Carbon|null $diputuskan_pada
 * @property Carbon $created_at
 * @property-read Instans $instans
 * @property-read Definisi $definisiBaru
 */
class PindahVersi extends Model implements Auditable
{
    use AuditedByWfe;

    protected $table = 'wf_pindah_versi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['diputuskan_pada' => 'datetime'];
    }

    /** @return BelongsTo<Instans, $this> */
    public function instans(): BelongsTo
    {
        return $this->belongsTo(Instans::class, 'id_instans')->withoutGlobalScope('access');
    }

    /** @return BelongsTo<Definisi, $this> */
    public function definisiBaru(): BelongsTo
    {
        return $this->belongsTo(Definisi::class, 'id_definisi_baru');
    }
}
