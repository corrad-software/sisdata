<?php

namespace App\Modules\Per\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use App\Modules\Per\Models\Concerns\AuditedByPer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Local record of each approval layer decided in MOD-WFE (SRS-PER-29).
 * The decision itself is WFE's; this is the case's own history.
 *
 * @property int $id
 * @property string $id_pemerolehan
 * @property string|null $id_aliran_kerja
 * @property string $aras_kelulusan
 * @property int|null $id_pegawai_pelulus
 * @property string|null $jawatan_pelulus
 * @property string $tindakan
 * @property Carbon $tarikh_masa
 * @property string|null $justifikasi
 * @property-read User|null $pelulus
 */
class Kelulusan extends Model implements Auditable, HasClassification
{
    use AuditedByPer;

    protected $table = 'kelulusan_pemerolehan';

    protected $guarded = ['id'];

    protected $casts = ['tarikh_masa' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function pelulus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_pelulus');
    }
}
