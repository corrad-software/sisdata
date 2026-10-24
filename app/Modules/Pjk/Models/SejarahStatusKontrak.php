<?php

namespace App\Modules\Pjk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SRS-PJK-02: every contract status transition, with date and officer (append-only). */
class SejarahStatusKontrak extends PjkModel
{
    public const UPDATED_AT = null;

    protected $table = 'sejarah_status_kontrak';

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
