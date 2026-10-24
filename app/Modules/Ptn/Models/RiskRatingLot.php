<?php

namespace App\Modules\Ptn\Models;

use App\Modules\Ptn\Models\Concerns\UntukPengguna;
use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Support\Carbon;

/**
 * SRS-PTN-11/12: one risk computation for a lot (history; semasa = latest).
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> untuk(\App\Models\User $user)
 *
 * @property int $id
 * @property string $lot_id
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string $klasifikasi
 * @property Carbon $tarikh_kiraan
 * @property string $skor_pencerobohan
 * @property string $skor_tunggakan_cukai
 * @property string $skor_pertikaian
 * @property string $skor_dokumen
 * @property string $skor_tamat_kontrak
 * @property string $skor_potensi_hasil
 * @property string $skor_keseluruhan
 * @property string $kod_warna
 * @property bool $paksa_merah
 * @property array<string, mixed> $butiran
 * @property bool $semasa
 */
class RiskRatingLot extends PtnModel
{
    use ScopedByAccess, UntukPengguna;

    protected $table = 'risk_rating_lot';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected $casts = ['tarikh_kiraan' => 'datetime', 'butiran' => 'array', 'semasa' => 'boolean', 'paksa_merah' => 'boolean'];

    /** Risk scores are recomputed in bulk; only explicit threshold/weight changes need field-level audit. */
    public function auditExcept(): array
    {
        return ['updated_at', 'butiran'];
    }
}
