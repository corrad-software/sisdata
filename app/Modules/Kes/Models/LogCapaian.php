<?php

namespace App\Modules\Kes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-KES-13: case access log — every view, download, print and export with
 * IP and timestamp (append-only; DB trigger). Not itself model-audited: the
 * same events are recorded explicitly in MOD-AUD by Services\LogCapaianKes.
 *
 * @property int $id
 * @property string|null $kes_mahkamah_id
 * @property string|null $id_dokumen
 * @property int|null $id_pengguna
 * @property string $jenis_tindakan
 * @property Carbon $cap_masa
 * @property string|null $alamat_ip
 * @property string|null $ejen_pengguna
 * @property string|null $keterangan
 * @property-read User|null $pengguna
 */
class LogCapaian extends Model
{
    protected $table = 'kes_log_capaian';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['cap_masa' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
