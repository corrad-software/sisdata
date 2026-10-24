<?php

namespace App\Modules\Ntf\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's inbox item (SRS-NTF-11..13). Deleting is logical (dipadam_pada);
 * the delivery log in ntf_penghantaran is untouched.
 *
 * @property int $id
 * @property int $mesej_id
 * @property int $pengguna_id
 * @property Carbon|null $dibaca_pada
 * @property Carbon|null $diarkib_pada
 * @property Carbon|null $dipadam_pada
 * @property Carbon|null $dieskalasi_pada
 * @property Carbon $created_at
 * @property-read Mesej $mesej
 * @property-read User $pengguna
 */
class StatusPengguna extends Model implements Auditable
{
    protected $table = 'ntf_status_pengguna';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'dibaca_pada' => 'datetime',
            'diarkib_pada' => 'datetime',
            'dipadam_pada' => 'datetime',
            'dieskalasi_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<Mesej, $this> */
    public function mesej(): BelongsTo
    {
        return $this->belongsTo(Mesej::class, 'mesej_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    public function auditModule(): string
    {
        return 'ntf';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
