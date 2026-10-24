<?php

namespace App\Modules\Aud\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SRS-AUD-20 retention policy (single active row). Changes are audited by the
 * global listener like any other configuration change.
 *
 * @property int $tempoh_dalam_talian_bulan
 * @property int|null $tempoh_arkib_tahun
 * @property string|null $catatan
 * @property Carbon|null $updated_at
 */
class AuditRetensiPolisi extends Model implements Auditable
{
    protected $table = 'audit_retensi_polisi';

    protected $fillable = ['tempoh_dalam_talian_bulan', 'tempoh_arkib_tahun', 'catatan'];

    public static function current(): self
    {
        return static::query()->latest('id')->first()
            ?? static::query()->create(['tempoh_dalam_talian_bulan' => (int) config('aud.retensi_minimum_bulan', 24)]);
    }

    public function onlineMonths(): int
    {
        return max((int) config('aud.retensi_minimum_bulan', 24), $this->tempoh_dalam_talian_bulan);
    }

    public function auditModule(): string
    {
        return 'aud';
    }

    public function auditExcept(): array
    {
        return [];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
