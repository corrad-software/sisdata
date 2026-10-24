<?php

namespace App\Modules\Wfe\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $id_peringkat
 * @property int $aras
 * @property int $tempoh_nilai
 * @property string $tempoh_unit
 * @property string $jenis_penerima
 * @property string|null $nilai_penerima
 */
class Eskalasi extends Model implements Auditable
{
    use AuditedByWfe;

    public const JENIS = ['peranan', 'jawatan', 'individu', 'kumpulan', 'penyelia'];

    protected $table = 'wf_eskalasi';

    protected $guarded = ['id'];
}
