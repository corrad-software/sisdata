<?php

namespace App\Modules\Kes\Models;

/**
 * SRS-KES-01: configurable case types (no code change to add one).
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string|null $keterangan
 * @property int $susunan
 * @property bool $aktif
 */
class JenisKes extends KesModel
{
    protected $table = 'kes_jenis';

    public function auditClassification(): string
    {
        return 'dalaman';
    }

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'susunan' => 'integer'];
    }
}
