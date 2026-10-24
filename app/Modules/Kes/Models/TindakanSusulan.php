<?php

namespace App\Modules\Kes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-KES-17: a follow-up action raised by a decision; each one is a MOD-WFE
 * task (`kes.tindakan_susulan`) — approving the task closes the action.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property int|null $kes_keputusan_id
 * @property string $jenis_tindakan
 * @property string|null $keterangan
 * @property string|null $id_tugasan_workflow
 * @property Carbon|null $tarikh_sasaran
 * @property string $status
 * @property string|null $id_lot_terjejas
 * @property int|null $id_pegawai
 * @property Carbon|null $tarikh_selesai
 * @property bool $sintetik
 * @property-read KesMahkamah|null $kes
 * @property-read User|null $pegawai
 */
class TindakanSusulan extends KesModel
{
    protected $table = 'kes_tindakan_susulan';

    public const JENIS = ['pematuhan_perintah', 'penguatkuasaan_keputusan', 'pemfailan_rayuan', 'pengemaskinian_hak_milik', 'lain'];

    protected function casts(): array
    {
        return ['tarikh_sasaran' => 'date:Y-m-d', 'tarikh_selesai' => 'datetime', 'sintetik' => 'boolean'];
    }

    /** @return BelongsTo<KesMahkamah, $this> */
    public function kes(): BelongsTo
    {
        return $this->belongsTo(KesMahkamah::class, 'kes_mahkamah_id')->withoutGlobalScope('access');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
