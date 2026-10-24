<?php

namespace App\Modules\Adu\Models;

use App\Models\User;
use App\Modules\Adu\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-ADU-18: one immutable timeline entry. Corrections are new entries of
 * type "pembetulan" (DB trigger rejects UPDATE/DELETE/TRUNCATE).
 *
 * @property int $id
 * @property string $aduan_id
 * @property string|null $no_rujukan_aduan
 * @property Carbon $tarikh_masa
 * @property string $jenis_tindakan
 * @property string|null $keterangan_tindakan
 * @property int|null $id_pegawai
 * @property string|null $status_sebelum
 * @property string|null $status_selepas
 * @property string|null $id_dokumen_berkaitan
 * @property bool $komunikasi_pengadu
 * @property array<string, mixed>|null $data
 * @property-read User|null $pegawai
 */
class KronologiAduan extends AduModel
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $table = 'kronologi_aduan';

    protected function casts(): array
    {
        return ['tarikh_masa' => 'datetime', 'komunikasi_pengadu' => 'boolean', 'data' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
