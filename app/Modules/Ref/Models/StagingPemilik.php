<?php

namespace App\Modules\Ref\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Staging owner / donor of a title. Today every row is SYNTHETIC
 * (sintetik = true, IC with place-of-birth code "00", which JPN never issues).
 *
 * @property string $id
 * @property string $peranan
 * @property string $jenis_pemilik
 * @property string $nama
 * @property string|null $no_pengenalan
 * @property bool $sintetik
 */
class StagingPemilik extends Model implements Auditable
{
    use HasUuids;

    protected $table = 'staging_pemilik';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sintetik' => 'boolean',
            'dimuat_pada' => 'datetime',
        ];
    }

    public function auditModule(): string
    {
        return 'ref';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return ['no_pengenalan'];
    }
}
