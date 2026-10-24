<?php

namespace App\Modules\Wfe\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only workflow history (SRS-WFE-16) — never updated or deleted.
 * Carries the WF_TINDAKAN columns (actor, role at the time, action,
 * justification, IP) as well as engine events (tasks, escalations…).
 *
 * @property int $id
 * @property string $id_instans
 * @property int|null $id_tugasan
 * @property int|null $id_peringkat
 * @property string|null $nama_peringkat
 * @property string $peristiwa
 * @property int|null $id_pengguna
 * @property int|null $id_bagi_pihak
 * @property list<string>|null $peranan_semasa
 * @property string|null $justifikasi
 * @property string|null $alamat_ip
 * @property Carbon|null $mfa_pada
 * @property array<string, mixed>|null $butiran
 * @property Carbon $tarikh_masa
 * @property-read User|null $pengguna
 * @property-read User|null $bagiPihak
 */
class Sejarah extends Model
{
    public $timestamps = false;

    protected $table = 'wf_sejarah';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'peranan_semasa' => 'array',
            'butiran' => 'array',
            'mfa_pada' => 'datetime',
            'tarikh_masa' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    /** @return BelongsTo<User, $this> */
    public function bagiPihak(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_bagi_pihak');
    }
}
