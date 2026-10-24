<?php

namespace App\Modules\Wfe\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $id_instans
 * @property int|null $id_peringkat
 * @property string $jenis kelulusan | susulan
 * @property string|null $tajuk
 * @property int $kitaran
 * @property int|null $id_pelulus
 * @property string $jenis_penerima
 * @property string|null $nilai_penerima
 * @property string $penerima_kunci
 * @property int|null $id_pelulus_asal
 * @property string $status
 * @property string|null $keputusan
 * @property Carbon|null $tarikh_akhir_sla
 * @property Carbon|null $ditangguh_hingga
 * @property int $aras_eskalasi
 * @property Carbon|null $amaran_sla_pada
 * @property int|null $diselesaikan_oleh
 * @property Carbon|null $diselesaikan_pada
 * @property Carbon $created_at
 * @property-read Instans $instans
 * @property-read Peringkat|null $peringkat
 * @property-read User|null $penyelesai
 */
class Tugasan extends Model implements Auditable
{
    use AuditedByWfe;

    public const MENUNGGU = 'menunggu';

    public const DITANGGUH = 'ditangguh';

    public const SELESAI = 'selesai';

    public const DIBATALKAN = 'dibatalkan';

    public const KELULUSAN = 'kelulusan';

    public const SUSULAN = 'susulan';

    protected $table = 'wf_tugasan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tarikh_akhir_sla' => 'datetime',
            'ditangguh_hingga' => 'datetime',
            'amaran_sla_pada' => 'datetime',
            'diselesaikan_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<Instans, $this> */
    public function instans(): BelongsTo
    {
        // A task assigned to the user is theirs to see regardless of the
        // monitoring district scope on instances.
        return $this->belongsTo(Instans::class, 'id_instans')->withoutGlobalScope('access');
    }

    /** @return BelongsTo<Peringkat, $this> */
    public function peringkat(): BelongsTo
    {
        return $this->belongsTo(Peringkat::class, 'id_peringkat');
    }

    /** @return BelongsTo<User, $this> */
    public function penyelesai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diselesaikan_oleh');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::MENUNGGU, self::DITANGGUH], true);
    }
}
