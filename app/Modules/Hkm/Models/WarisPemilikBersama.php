<?php

namespace App\Modules\Hkm\Models;

use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SRS-HKM-12: heir / co-owner of a title with share fraction and verification.
 *
 * @property int $id
 * @property string $hak_milik_id
 * @property string $nama
 * @property string|null $no_pengenalan
 * @property string $hubungan
 * @property int|null $pecahan_bahagian_pembilang
 * @property int|null $pecahan_bahagian_penyebut
 * @property string|null $sumber_pengesahan
 * @property string|null $no_kes_faraid
 * @property string $status_pengesahan
 * @property string|null $catatan
 * @property bool $sintetik
 */
class WarisPemilikBersama extends Model implements Auditable, HasClassification
{
    use SoftDeletes;

    protected $table = 'waris_pemilik_bersama';

    protected $guarded = ['id'];

    public const MEDAN = ['nama', 'no_pengenalan', 'hubungan', 'pecahan_bahagian_pembilang', 'pecahan_bahagian_penyebut', 'sumber_pengesahan', 'no_kes_faraid', 'status_pengesahan', 'catatan'];

    protected function casts(): array
    {
        return ['sintetik' => 'boolean', 'pecahan_bahagian_pembilang' => 'integer', 'pecahan_bahagian_penyebut' => 'integer'];
    }

    public function auditModule(): string
    {
        return 'hkm';
    }

    public function auditExcept(): array
    {
        return ['updated_at', 'created_at'];
    }

    public function auditSensitive(): array
    {
        return ['no_pengenalan'];
    }

    public function auditClassification(): string
    {
        return 'sulit';
    }

    /** @return BelongsTo<HakMilik, $this> */
    public function hakMilik(): BelongsTo
    {
        return $this->belongsTo(HakMilik::class, 'hak_milik_id');
    }
}
