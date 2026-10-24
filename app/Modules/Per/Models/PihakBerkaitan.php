<?php

namespace App\Modules\Per\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use App\Modules\Per\Models\Concerns\AuditedByPer;
use Illuminate\Database\Eloquent\Model;

/**
 * SRS-PER-23 related party. Name and ID number are personal data (Sulit):
 * ID numbers are stored encrypted, masked in lists and in the audit trail.
 *
 * @property int $id
 * @property string $id_pemerolehan
 * @property string $peranan_pihak
 * @property string $nama
 * @property string|null $hubungan
 * @property string|null $jenis_dokumen_pengenalan
 * @property string|null $no_dokumen_pengenalan
 * @property string|null $no_pengenalan_4akhir
 * @property string|null $bahagian_pusaka
 * @property string $status_pengesahan
 * @property string|null $sumber_pengesahan
 * @property string|null $catatan
 * @property string $klasifikasi
 */
class PihakBerkaitan extends Model implements Auditable, HasClassification
{
    use AuditedByPer;

    public const STATUS = ['belum_disahkan', 'disahkan', 'ditolak'];

    protected $table = 'pihak_berkaitan_pemerolehan';

    protected $guarded = ['id'];

    protected $casts = ['no_dokumen_pengenalan' => 'encrypted'];

    /** @var list<string> */
    protected array $sensitive = ['nama', 'no_dokumen_pengenalan', 'no_pengenalan_4akhir'];

    protected static function booted(): void
    {
        static::saving(function (self $p) {
            $digits = (string) preg_replace('/\W/', '', (string) $p->no_dokumen_pengenalan);
            $p->no_pengenalan_4akhir = $digits === '' ? null : substr($digits, -4);
        });
    }
}
