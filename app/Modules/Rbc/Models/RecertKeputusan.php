<?php

namespace App\Modules\Rbc\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * RECERT_KEPUTUSAN (SRS-RBC-27): one user × role assignment under review.
 *
 * @property int $id
 * @property int $kitaran_id
 * @property int $user_id
 * @property int $peranan_id
 * @property string $keputusan
 * @property string|null $catatan
 * @property int|null $disemak_oleh
 * @property Carbon|null $disemak_pada
 * @property-read User $user
 * @property-read Peranan $peranan
 * @property-read RecertKitaran $kitaran
 * @property-read User|null $penyemak
 */
class RecertKeputusan extends Model
{
    public const BELUM = 'belum';

    public const DISAHKAN = 'disahkan';

    public const DITARIK = 'ditarik';

    protected $table = 'recert_keputusan';

    protected $fillable = ['kitaran_id', 'user_id', 'peranan_id', 'keputusan', 'catatan', 'disemak_oleh', 'disemak_pada'];

    protected $attributes = ['keputusan' => self::BELUM];

    protected function casts(): array
    {
        return ['disemak_pada' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Peranan, $this> */
    public function peranan(): BelongsTo
    {
        return $this->belongsTo(Peranan::class, 'peranan_id');
    }

    /** @return BelongsTo<RecertKitaran, $this> */
    public function kitaran(): BelongsTo
    {
        return $this->belongsTo(RecertKitaran::class, 'kitaran_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penyemak(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disemak_oleh');
    }
}
