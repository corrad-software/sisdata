<?php

namespace App\Modules\Dsh\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $id_pengguna
 * @property string $aras
 * @property string $tandatangan_skop
 * @property array<string, mixed> $penapis
 * @property list<array<string, mixed>> $nilai
 * @property string $sumber
 * @property Carbon $dijana_pada
 */
class Snapshot extends DshModel
{
    protected $table = 'dsh_snapshot';

    protected function casts(): array
    {
        return ['penapis' => 'array', 'nilai' => 'array', 'dijana_pada' => 'datetime'];
    }

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at', 'nilai'];
    }
}
