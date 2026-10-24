<?php

namespace App\Modules\Wfe\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One approver slot of a stage (WF_PELULUS). Each slot becomes one task.
 *
 * @property int $id
 * @property int $id_peringkat
 * @property string $jenis peranan | jawatan | individu | kumpulan | penyelia_pemohon
 * @property string|null $nilai
 * @property string|null $label
 */
class Pelulus extends Model implements Auditable
{
    use AuditedByWfe;

    public const JENIS = ['peranan', 'jawatan', 'individu', 'kumpulan', 'penyelia_pemohon'];

    protected $table = 'wf_pelulus';

    protected $guarded = ['id'];

    /** @return BelongsTo<Peringkat, $this> */
    public function peringkat(): BelongsTo
    {
        return $this->belongsTo(Peringkat::class, 'id_peringkat');
    }
}
