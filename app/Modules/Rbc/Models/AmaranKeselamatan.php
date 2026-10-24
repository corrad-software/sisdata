<?php

namespace App\Modules\Rbc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-RBC-22 suspicious-activity alert for system administrators.
 *
 * @property int $id
 * @property string $jenis
 * @property int|null $user_id
 * @property string|null $alamat_ip
 * @property array<string, mixed>|null $butiran
 * @property Carbon|null $disemak_pada
 * @property int|null $disemak_oleh
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class AmaranKeselamatan extends Model
{
    public const UPDATED_AT = null;

    public const GAGAL_BERULANG = 'gagal_berulang';

    public const PERANTI_BAHARU = 'peranti_baharu';

    public const LOKASI_BAHARU = 'lokasi_baharu';

    public const CAPAIAN_TANPA_KEBENARAN = 'capaian_tanpa_kebenaran';

    protected $table = 'amaran_keselamatan';

    protected $fillable = ['jenis', 'user_id', 'alamat_ip', 'butiran', 'disemak_pada', 'disemak_oleh'];

    protected function casts(): array
    {
        return ['butiran' => 'array', 'disemak_pada' => 'datetime', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
