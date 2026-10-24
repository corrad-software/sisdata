<?php

namespace App\Modules\Adu\Models;

use App\Models\User;
use App\Modules\Adu\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-ADU-21: who saw which reporter-identity fields, when, why, from where.
 * Append-only (DB trigger); also mirrored to the central audit trail.
 *
 * @property int $id
 * @property string $aduan_id
 * @property string|null $no_rujukan_aduan
 * @property int|null $id_pegawai
 * @property Carbon $tarikh_masa
 * @property list<string> $medan_dicapai
 * @property string $tujuan_capaian
 * @property string|null $alamat_ip
 * @property string $asas_capaian
 * @property-read User|null $pegawai
 */
class AuditCapaianPengadu extends AduModel
{
    use AppendOnly;

    public $timestamps = false;

    protected $table = 'audit_capaian_pengadu';

    protected function casts(): array
    {
        return ['medan_dicapai' => 'array', 'tarikh_masa' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
