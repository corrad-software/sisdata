<?php

namespace App\Modules\Ast\Models;

use App\Models\User;
use App\Modules\Ast\Models\Concerns\SkopLot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-AST-14/17: maintenance work order (preventive from a schedule, corrective, or from a MOD-PTN maintenance need); its cost feeds the lot cost chart.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property string $id
 * @property string $no_rujukan
 * @property string $id_aset
 * @property int|null $id_jadual
 * @property string $jenis_kerja
 * @property string $jenis_penyelenggaraan
 * @property string $keterangan
 * @property string $keutamaan
 * @property Carbon|null $tarikh_dijadual
 * @property Carbon|null $tarikh_mula
 * @property Carbon|null $tarikh_siap
 * @property string|null $kos_anggaran
 * @property string|null $kos
 * @property int|null $id_kontraktor
 * @property string|null $nama_syarikat_pelaksana
 * @property string $status
 * @property string|null $id_aliran
 * @property int|null $id_gambar_sebelum
 * @property int|null $id_gambar_selepas
 * @property int|null $id_keperluan_ptn
 * @property string|null $id_pemeriksaan
 * @property string $sumber
 * @property int|null $id_pegawai
 * @property string|null $catatan_penyelesaian
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property bool $sintetik
 * @property Carbon $created_at
 * @property-read User|null $pegawai
 * @property-read AsetFizikal|null $aset
 * @property-read AsetKontraktor|null $kontraktor
 * @property-read JadualPenyelenggaraan|null $jadual
 */
class RekodPenyelenggaraan extends AstModel
{
    use HasUuids, SkopLot;

    protected $table = 'rekod_penyelenggaraan';

    protected $casts = ['tarikh_dijadual' => 'date', 'tarikh_mula' => 'date', 'tarikh_siap' => 'date', 'kos' => 'decimal:2', 'kos_anggaran' => 'decimal:2', 'sintetik' => 'boolean'];

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

    /** @return BelongsTo<AsetKontraktor, $this> */
    public function kontraktor(): BelongsTo
    {
        return $this->belongsTo(AsetKontraktor::class, 'id_kontraktor');
    }

    /** @return BelongsTo<JadualPenyelenggaraan, $this> */
    public function jadual(): BelongsTo
    {
        return $this->belongsTo(JadualPenyelenggaraan::class, 'id_jadual');
    }
}
