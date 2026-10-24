<?php

namespace App\Modules\Adm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One execution of a scheduled job (SRS-ADM-08). ADM_JOB_LARIAN.
 *
 * @property int $id
 * @property int $id_tugasan
 * @property string $pencetus jadual | manual
 * @property int|null $id_pengguna
 * @property int $cubaan
 * @property Carbon|null $tarikh_mula
 * @property Carbon|null $tarikh_tamat
 * @property float|null $tempoh_saat
 * @property string $status beratur | berjalan | berjaya | gagal | dilangkau
 * @property int|null $bilangan_rekod
 * @property string|null $mesej_ralat
 * @property string|null $output
 * @property string|null $id_korelasi
 */
class TugasanLarian extends Model
{
    public const BERATUR = 'beratur';

    public const BERJALAN = 'berjalan';

    public const BERJAYA = 'berjaya';

    public const GAGAL = 'gagal';

    public const DILANGKAU = 'dilangkau';

    protected $table = 'adm_tugasan_larian';

    protected $fillable = ['id_tugasan', 'pencetus', 'id_pengguna', 'cubaan', 'tarikh_mula', 'tarikh_tamat', 'tempoh_saat', 'status', 'bilangan_rekod', 'mesej_ralat', 'output', 'id_korelasi'];

    protected function casts(): array
    {
        return [
            'tarikh_mula' => 'datetime',
            'tarikh_tamat' => 'datetime',
            'tempoh_saat' => 'float',
            'cubaan' => 'integer',
            'bilangan_rekod' => 'integer',
        ];
    }

    /** @return BelongsTo<Tugasan, $this> */
    public function tugasan(): BelongsTo
    {
        return $this->belongsTo(Tugasan::class, 'id_tugasan');
    }
}
