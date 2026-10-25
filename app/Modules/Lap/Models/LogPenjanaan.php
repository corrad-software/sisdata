<?php

namespace App\Modules\Lap\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;

/**
 * One report generation / export (SRS-LAP-07/10): parameters, user, time,
 * record count, status and — for background / scheduled runs — the file.
 *
 * @property string $id
 * @property string|null $id_definisi
 * @property string|null $kod_laporan
 * @property int|null $id_jadual
 * @property string $tajuk
 * @property string|null $set_data
 * @property array<string, mixed>|null $parameter_digunakan
 * @property string $format_eksport
 * @property string $mod
 * @property int $bilangan_rekod
 * @property int|null $id_pengguna
 * @property Carbon $cap_masa
 * @property string $status
 * @property int|null $tempoh_ms
 * @property string|null $laluan_fail
 * @property int|null $saiz_bait
 * @property string|null $checksum
 * @property string $klasifikasi
 * @property string|null $ralat
 * @property Carbon|null $tamat_pada
 */
class LogPenjanaan extends LapModel
{
    use HasUuids;

    protected $table = 'lap_log_penjanaan';

    protected function casts(): array
    {
        return ['parameter_digunakan' => 'array', 'cap_masa' => 'datetime', 'tamat_pada' => 'datetime'];
    }

    /** Logs are evidence: never audited field-by-field as edits (they are the audit). */
    public function auditExcept(): array
    {
        return ['created_at', 'updated_at', 'parameter_digunakan', 'laluan_fail', 'checksum', 'saiz_bait', 'tempoh_ms', 'tamat_pada', 'ralat'];
    }
}
