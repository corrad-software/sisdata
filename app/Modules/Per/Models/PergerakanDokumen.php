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
 * SRS-PER-22: issue / return history of a physical original.
 *
 * @property int $id
 * @property int $id_dokumen_pemerolehan
 * @property string $jenis
 * @property Carbon $tarikh
 * @property int|null $id_pegawai
 * @property string|null $tujuan
 * @property-read User|null $pegawai
 */
class PergerakanDokumen extends Model implements Auditable, HasClassification
{
    use AuditedByPer;

    protected $table = 'pergerakan_dokumen_pemerolehan';

    protected $guarded = ['id'];

    protected $casts = ['tarikh' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
