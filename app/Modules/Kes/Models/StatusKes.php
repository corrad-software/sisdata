<?php

namespace App\Modules\Kes\Models;

/**
 * SRS-KES-07: configurable status stages; `terminal` stages end the case
 * (the lot's legal-action status is lifted when no other case is active).
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property int $susunan
 * @property bool $terminal
 * @property bool $aktif
 */
class StatusKes extends KesModel
{
    protected $table = 'kes_status';

    public function auditClassification(): string
    {
        return 'dalaman';
    }

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'terminal' => 'boolean', 'susunan' => 'integer'];
    }
}
