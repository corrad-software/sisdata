<?php

namespace App\Modules\Per\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use App\Modules\Per\Models\Concerns\AuditedByPer;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $id_saluran
 * @property string $kod_peringkat
 * @property string $nama_peringkat
 * @property string $jenis
 * @property int $susunan
 * @property int $tempoh_sasaran_hari
 * @property bool $aktif
 */
class PeringkatSaluran extends Model implements Auditable, HasClassification
{
    use AuditedByPer;

    public const JENIS = ['biasa', 'kelulusan', 'pendaftaran', 'simpanan'];

    protected $table = 'peringkat_saluran';

    protected $guarded = ['id'];

    protected $casts = ['aktif' => 'boolean', 'susunan' => 'integer', 'tempoh_sasaran_hari' => 'integer'];

    public function auditClassification(): string
    {
        return 'dalaman';
    }
}
