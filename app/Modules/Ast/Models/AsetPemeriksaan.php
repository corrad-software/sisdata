<?php

namespace App\Modules\Ast\Models;

use App\Models\User;
use App\Modules\Ast\Models\Concerns\SkopLot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-20/21: inspection (field PWA offline-synced, manual or after a QR scan).
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property string $id
 * @property string $id_aset
 * @property Carbon $tarikh_pemeriksaan
 * @property int|null $id_pegawai
 * @property string $kaedah
 * @property string $status_kondisi_dilapor
 * @property string|null $isu_dikenalpasti
 * @property string|null $tindakan_disyorkan
 * @property list<array<string, mixed>> $senarai_semak
 * @property string|null $latitud
 * @property string|null $longitud
 * @property string|null $ketepatan_gps_m
 * @property string|null $jarak_dari_aset_m
 * @property Carbon|null $masa_mula
 * @property Carbon|null $masa_tamat
 * @property string $status_penyegerakan
 * @property string|null $id_luar_talian
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property-read User|null $pegawai
 * @property-read AsetFizikal|null $aset
 */
class AsetPemeriksaan extends AstModel
{
    use HasUuids, SkopLot;

    protected $table = 'aset_pemeriksaan';

    protected $casts = ['tarikh_pemeriksaan' => 'date', 'masa_mula' => 'datetime', 'masa_tamat' => 'datetime', 'senarai_semak' => 'array', 'sintetik' => 'boolean'];

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }

    /** @return BelongsTo<AsetFizikal, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetFizikal::class, 'id_aset');
    }
}
