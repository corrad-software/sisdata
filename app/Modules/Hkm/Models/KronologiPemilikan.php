<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-10: one owner change of a title, oldest first by susunan_kronologi.
 * Append-only (DB trigger).
 *
 * @property int $id
 * @property string $hak_milik_id
 * @property string $lot_id
 * @property string|null $pemilik_sebelum
 * @property string $pemilik_selepas
 * @property Carbon|null $tarikh_pertukaran
 * @property string $sebab_pertukaran
 * @property string|null $id_pemerolehan
 * @property string|null $id_dokumen_rujukan
 * @property int $susunan_kronologi
 * @property string $sumber
 * @property int|null $id_pegawai
 * @property string|null $id_perubahan
 * @property Carbon $created_at
 * @property-read User|null $pegawai
 */
class KronologiPemilikan extends Model
{
    use Concerns\AppendOnly;

    public const UPDATED_AT = null;

    protected $table = 'kronologi_pemilikan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_pertukaran' => 'date:Y-m-d', 'susunan_kronologi' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
