<?php

namespace App\Modules\Dsh\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $id_pengguna
 * @property string $teks_prompt
 * @property array<string, mixed> $pertanyaan_dijana
 * @property array<string, mixed> $skop_rbac
 * @property array<string, mixed>|null $jawapan
 * @property string $enjin
 * @property string $status
 * @property Carbon $cap_masa
 */
class LogPromptAi extends DshModel
{
    protected $table = 'dsh_log_prompt_ai';

    protected function casts(): array
    {
        return ['pertanyaan_dijana' => 'array', 'skop_rbac' => 'array', 'jawapan' => 'array', 'cap_masa' => 'datetime'];
    }
}
