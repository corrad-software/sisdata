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
 * SRS-PER-05: a payment against a 5A notice, confirmed by Bahagian Kewangan.
 *
 * @property int $id
 * @property string $id_pemerolehan
 * @property string $jumlah_dibayar
 * @property Carbon $tarikh_bayaran
 * @property string $mod_bayaran
 * @property string $no_resit
 * @property string $status_pengesahan
 * @property int|null $id_pegawai_rekod
 * @property int|null $id_pegawai_pengesah
 * @property Carbon|null $tarikh_pengesahan
 * @property string|null $catatan
 * @property-read User|null $pengesah
 */
class BayaranNotis5a extends Model implements Auditable, HasClassification
{
    use AuditedByPer;

    public const MOD = ['eft', 'cek', 'tunai', 'grp', 'lain'];

    protected $table = 'bayaran_notis_5a';

    protected $guarded = ['id'];

    protected $casts = ['tarikh_bayaran' => 'date', 'tarikh_pengesahan' => 'datetime', 'jumlah_dibayar' => 'decimal:2'];

    /** @return BelongsTo<User, $this> */
    public function pengesah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_pengesah');
    }
}
