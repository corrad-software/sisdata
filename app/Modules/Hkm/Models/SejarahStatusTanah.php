<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-09: one land/title status change. Append-only (DB trigger) — the
 * model also refuses updates/deletes so the intent is visible in code.
 *
 * @property int $id
 * @property string $lot_id
 * @property string|null $hak_milik_id
 * @property string|null $status_sebelum
 * @property string $status_selepas
 * @property Carbon $tarikh_berkuat_kuasa
 * @property string $sebab_perubahan
 * @property string|null $id_dokumen_rujukan
 * @property string|null $no_rujukan_dokumen
 * @property int|null $id_pegawai
 * @property int|null $id_pelulus
 * @property string|null $id_perubahan
 * @property Carbon $tarikh_masa_rekod
 * @property-read User|null $pegawai
 * @property-read User|null $pelulus
 */
class SejarahStatusTanah extends Model
{
    use Concerns\AppendOnly;

    public $timestamps = false;

    protected $table = 'sejarah_status_tanah';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_berkuat_kuasa' => 'date:Y-m-d', 'tarikh_masa_rekod' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }

    /** @return BelongsTo<User, $this> */
    public function pelulus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pelulus');
    }
}
