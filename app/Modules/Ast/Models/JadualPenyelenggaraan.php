<?php

namespace App\Modules\Ast\Models;

use App\Models\User;
use App\Modules\Ast\Models\Concerns\SkopLot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-15: recurring preventive-maintenance schedule (one row per asset x type; the due date rolls forward by kitaran_bulan when the work is done).
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property int $id
 * @property string $id_aset
 * @property string $jenis_penyelenggaraan
 * @property int $kitaran_bulan
 * @property Carbon $tarikh_jatuh_tempoh
 * @property int $tempoh_amaran_hari
 * @property string $status
 * @property int|null $id_pegawai_bertanggungjawab
 * @property bool $dijana_automatik
 * @property Carbon|null $tarikh_terakhir_dilaksana
 * @property Carbon|null $tarikh_eskalasi
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property bool $sintetik
 * @property-read User|null $pegawai
 * @property-read AsetFizikal|null $aset
 */
class JadualPenyelenggaraan extends AstModel
{
    use SkopLot;

    protected $table = 'jadual_penyelenggaraan';

    protected $casts = ['tarikh_jatuh_tempoh' => 'date', 'tarikh_terakhir_dilaksana' => 'date', 'tarikh_eskalasi' => 'date', 'dijana_automatik' => 'boolean', 'sintetik' => 'boolean'];

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_bertanggungjawab');
    }

    /** @return BelongsTo<AsetFizikal, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetFizikal::class, 'id_aset');
    }
}
