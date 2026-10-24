<?php

namespace App\Modules\Ntf\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $jenis_id
 * @property int $versi
 * @property string $subjek
 * @property string $kandungan
 * @property bool $aktif
 * @property string|null $catatan
 * @property int|null $dicipta_oleh
 * @property Carbon|null $created_at
 */
class Templat extends Model implements Auditable
{
    protected $table = 'ntf_templat';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'versi' => 'integer'];
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
