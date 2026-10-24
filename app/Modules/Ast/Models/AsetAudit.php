<?php

namespace App\Modules\Ast\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-23: append-only trail of value / condition / responsible-officer changes (DB trigger forbids UPDATE/DELETE). MOD-AUD records the same changes centrally.
 *
 * @property int $id
 * @property string $id_aset
 * @property string $medan_diubah
 * @property string|null $nilai_sebelum
 * @property string|null $nilai_selepas
 * @property int|null $id_pengguna
 * @property string|null $sebab
 * @property Carbon $cap_masa
 * @property-read User|null $pengguna
 */
class AsetAudit extends AstModel
{
    protected $table = 'aset_audit';

    public $timestamps = false;

    protected $casts = ['cap_masa' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
