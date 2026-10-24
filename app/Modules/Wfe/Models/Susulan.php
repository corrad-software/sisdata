<?php

namespace App\Modules\Wfe\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Wfe\Models\Concerns\AuditedByWfe;
use Illuminate\Database\Eloquent\Model;

/**
 * SRS-WFE-20: follow-up task created when an instance ends.
 *
 * @property int $id
 * @property int $id_definisi
 * @property string $bila lulus | tolak | semua
 * @property string $tajuk
 * @property string $jenis_penerima
 * @property string|null $nilai_penerima
 * @property int|null $sla_nilai
 * @property string $sla_unit
 */
class Susulan extends Model implements Auditable
{
    use AuditedByWfe;

    public const JENIS = ['peranan', 'jawatan', 'individu', 'kumpulan', 'pemohon'];

    protected $table = 'wf_susulan';

    protected $guarded = ['id'];
}
