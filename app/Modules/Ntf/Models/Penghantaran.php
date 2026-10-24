<?php

namespace App\Modules\Ntf\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One delivery attempt record: recipient × channel (SRS-NTF-15/16).
 *
 * @property int $id
 * @property int $mesej_id
 * @property int|null $penerima_id
 * @property string $saluran
 * @property string $status
 * @property int $bilangan_cubaan
 * @property string|null $kod_ralat
 * @property string|null $mesej_ralat
 * @property bool $ringkasan
 * @property Carbon|null $tarikh_beratur
 * @property Carbon|null $tarikh_hantar
 * @property Carbon|null $tarikh_terima
 * @property Carbon|null $tarikh_baca
 * @property Carbon|null $tarikh_gagal
 * @property Carbon $created_at
 * @property-read Mesej $mesej
 * @property-read User|null $penerima
 */
class Penghantaran extends Model implements Auditable
{
    public const BERATUR = 'beratur';

    public const DITAHAN = 'ditahan';

    public const DIHANTAR = 'dihantar';

    public const DITERIMA = 'diterima';

    public const DIBACA = 'dibaca';

    public const GAGAL = 'gagal';

    public const GAGAL_MUKTAMAD = 'gagal_muktamad';

    /** Statuses that count as delivered for success rates. */
    public const BERJAYA = [self::DIHANTAR, self::DITERIMA, self::DIBACA];

    protected $table = 'ntf_penghantaran';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ringkasan' => 'boolean',
            'bilangan_cubaan' => 'integer',
            'tarikh_beratur' => 'datetime',
            'tarikh_hantar' => 'datetime',
            'tarikh_terima' => 'datetime',
            'tarikh_baca' => 'datetime',
            'tarikh_gagal' => 'datetime',
        ];
    }

    /** @return BelongsTo<Mesej, $this> */
    public function mesej(): BelongsTo
    {
        return $this->belongsTo(Mesej::class, 'mesej_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penerima_id');
    }

    public function selesai(): bool
    {
        return in_array($this->status, [...self::BERJAYA, self::GAGAL_MUKTAMAD], true);
    }

    public function auditModule(): string
    {
        return 'ntf';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
