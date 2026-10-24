<?php

namespace App\Modules\Wfe\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Approver group (SRS-WFE-05): any member may act on the group's task.
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property-read Collection<int, User> $ahli
 */
class Kumpulan extends Model implements Auditable
{
    use AuditedByWfe;

    protected $table = 'wf_kumpulan';

    protected $guarded = ['id'];

    /** @return BelongsToMany<User, $this> */
    public function ahli(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wf_kumpulan_ahli', 'id_kumpulan', 'id_pengguna');
    }
}
