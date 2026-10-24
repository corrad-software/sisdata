<?php

namespace App\Modules\Wfe\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $id_peralihan
 * @property string $medan
 * @property string $operator
 * @property mixed $nilai
 */
class Syarat extends Model implements Auditable
{
    use AuditedByWfe;

    protected $table = 'wf_syarat';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['nilai' => 'json'];
    }
}
