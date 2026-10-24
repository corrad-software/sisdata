<?php

namespace App\Modules\Pjk\Models;

/**
 * SRS-PJK-18/21: a settlement / statement file from the gateway, GRP MAIS or
 * e-Sewa. `rekod` = normalised rows: [{rujukan, amaun, status?, tarikh?}].
 *
 * @property int $id
 * @property string $sumber
 * @property list<array{rujukan: string, amaun: string, status?: string, tarikh?: string}> $rekod
 */
class FailPenyelesaian extends PjkModel
{
    protected $table = 'fail_penyelesaian';

    protected function casts(): array
    {
        return ['rekod' => 'array', 'tarikh_penyelesaian' => 'date:Y-m-d'];
    }
}
