<?php

namespace App\Modules\Per\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use App\Modules\Per\Models\Concerns\AuditedByPer;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $id_saluran
 * @property string $jenis_dokumen
 * @property string $nama_dokumen
 * @property string $kategori_dms
 * @property bool $status_wajib
 * @property bool $dijana_sistem
 * @property string $peringkat_diperlukan
 * @property int $susunan_paparan
 */
class SemakDokumen extends Model implements Auditable, HasClassification
{
    use AuditedByPer;

    protected $table = 'senarai_semak_dokumen_saluran';

    protected $guarded = ['id'];

    protected $casts = ['status_wajib' => 'boolean', 'dijana_sistem' => 'boolean', 'susunan_paparan' => 'integer'];

    public function auditClassification(): string
    {
        return 'dalaman';
    }
}
