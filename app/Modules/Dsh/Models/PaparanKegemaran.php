<?php

namespace App\Modules\Dsh\Models;

/**
 * @property int $id
 * @property int $id_pengguna
 * @property string $nama_paparan
 * @property string|null $aras
 * @property array<string, mixed> $parameter_penapis
 * @property bool $lalai
 */
class PaparanKegemaran extends DshModel
{
    protected $table = 'dsh_paparan_kegemaran';

    protected function casts(): array
    {
        return ['parameter_penapis' => 'array', 'lalai' => 'boolean'];
    }
}
