<?php

namespace App\Modules\Dms\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SRS-DMS-07: a file that failed the antivirus scan, kept encrypted and
 * isolated under the kuarantin/ prefix — never reachable as a document.
 *
 * @property int $id
 * @property string $nama_fail
 * @property int $saiz_bait
 * @property string $cincangan_fail
 * @property string|null $kunci_storan
 * @property string|null $kunci_data_bersulit
 * @property string|null $id_kunci_induk
 * @property string|null $nama_ancaman
 * @property string|null $jenis_entiti_induk
 * @property string|null $id_rekod_induk
 * @property int|null $dimuat_naik_oleh
 * @property Carbon|null $dimusnahkan_pada
 * @property Carbon $created_at
 */
class DokumenKuarantin extends Model implements Auditable
{
    protected $table = 'dokumen_kuarantin';

    protected $guarded = ['id'];

    protected $hidden = ['kunci_storan', 'kunci_data_bersulit', 'id_kunci_induk'];

    protected function casts(): array
    {
        return ['dimusnahkan_pada' => 'datetime', 'saiz_bait' => 'integer'];
    }

    public function auditModule(): string
    {
        return 'dms';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return ['kunci_data_bersulit'];
    }
}
