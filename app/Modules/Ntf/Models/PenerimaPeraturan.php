<?php

namespace App\Modules\Ntf\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $jenis_id
 * @property string $sasaran
 * @property string $nilai
 * @property bool $aktif
 */
class PenerimaPeraturan extends Model implements Auditable
{
    protected $table = 'ntf_penerima_peraturan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /** @return BelongsTo<Jenis, $this> */
    public function jenis(): BelongsTo
    {
        return $this->belongsTo(Jenis::class, 'jenis_id');
    }

    public function auditModule(): string
    {
        return 'ntf';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
