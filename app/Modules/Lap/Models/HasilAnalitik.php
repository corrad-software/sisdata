<?php

namespace App\Modules\Lap\Models;

use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Support\Carbon;

/**
 * Analytic result per lot (SRS-LAP-15..18) — a system suggestion, never an
 * official MAIS decision (SRS-LAP-21).
 *
 * @property int $id
 * @property string $jenis_analitik
 * @property string $id_lot
 * @property string|null $no_lot
 * @property string|null $kod_daerah
 * @property string|null $kod_mukim
 * @property string $klasifikasi
 * @property string $skor
 * @property string $aras_risiko
 * @property array<string, mixed>|null $faktor
 * @property Carbon $tarikh_kira
 * @property string $versi_model
 * @property bool $semasa
 */
class HasilAnalitik extends LapModel
{
    use ScopedByAccess;

    public const JENIS = ['potensi_hasil', 'risiko_tunggakan', 'tanah_tidak_produktif', 'potensi_pembangunan'];

    public const ARAS = ['rendah', 'sederhana', 'tinggi', 'kritikal'];

    protected $table = 'lap_hasil_analitik';

    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected function casts(): array
    {
        return ['faktor' => 'array', 'tarikh_kira' => 'datetime', 'semasa' => 'boolean'];
    }
}
