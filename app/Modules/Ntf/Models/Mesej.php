<?php

namespace App\Modules\Ntf\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $jenis_id
 * @property int|null $templat_id
 * @property string $keutamaan
 * @property string $modul
 * @property string $subjek
 * @property string $kandungan
 * @property string|null $pautan
 * @property array<string, scalar|null> $data
 * @property string|null $kunci_dedupe
 * @property int|null $dicipta_oleh
 * @property Carbon $created_at
 * @property-read Jenis $jenis
 */
class Mesej extends Model implements Auditable
{
    protected $table = 'ntf_mesej';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /** @return BelongsTo<Jenis, $this> */
    public function jenis(): BelongsTo
    {
        return $this->belongsTo(Jenis::class, 'jenis_id');
    }

    /** @return HasMany<Penghantaran, $this> */
    public function penghantaran(): HasMany
    {
        return $this->hasMany(Penghantaran::class, 'mesej_id');
    }

    /**
     * Full frontend URL for e-mail and other external channels. The frontend
     * origin is the one CORS allows (FRONTEND_URL).
     */
    public function pautanPenuh(): string
    {
        $origin = (string) (config('cors.allowed_origins.0') ?: config('app.url'));

        return rtrim($origin, '/').($this->pautan ?? '/admin/notifikasi');
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
