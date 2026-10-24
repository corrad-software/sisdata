<?php

namespace App\Modules\Adm\Models;

use App\Modules\Adm\Models\Concerns\AuditedByAdm;
use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Integration settings (SRS-ADM-04). ADM_INTEGRASI.
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string $jenis
 * @property string $endpoint
 * @property string|null $id_rahsia
 * @property int $tempoh_tamat_masa
 * @property int|null $kadar_capaian
 * @property string|null $jadual
 * @property string $status
 * @property int $kegagalan_berturut
 * @property Carbon|null $terakhir_berjaya_pada
 * @property Carbon|null $terakhir_gagal_pada
 * @property string|null $justifikasi
 * @property int|null $dikemas_kini_oleh
 */
class Integrasi extends Model implements Auditable
{
    use AuditedByAdm;

    /** Fields an admin edits (all through second-admin approval, SRS-ADM-18). */
    public const EDITABLE = ['nama', 'jenis', 'endpoint', 'id_rahsia', 'tempoh_tamat_masa', 'kadar_capaian', 'jadual', 'status'];

    protected $table = 'adm_integrasi';

    protected $fillable = ['kod', 'nama', 'jenis', 'endpoint', 'id_rahsia', 'tempoh_tamat_masa', 'kadar_capaian', 'jadual', 'status', 'kegagalan_berturut', 'terakhir_berjaya_pada', 'terakhir_gagal_pada', 'justifikasi', 'dikemas_kini_oleh'];

    protected function casts(): array
    {
        return [
            'tempoh_tamat_masa' => 'integer',
            'kadar_capaian' => 'integer',
            'kegagalan_berturut' => 'integer',
            'terakhir_berjaya_pada' => 'datetime',
            'terakhir_gagal_pada' => 'datetime',
        ];
    }
}
