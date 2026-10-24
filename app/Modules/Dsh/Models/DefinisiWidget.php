<?php

namespace App\Modules\Dsh\Models;

/**
 * @property int $id
 * @property string $kod_widget
 * @property string|null $tajuk
 * @property string $jenis_visual
 * @property string $pertanyaan_sumber
 * @property list<string> $aras_dashboard
 * @property int $saiz
 * @property int $susunan
 * @property bool $aktif
 */
class DefinisiWidget extends DshModel
{
    protected $table = 'dsh_definisi_widget';

    protected function casts(): array
    {
        return ['aras_dashboard' => 'array', 'aktif' => 'boolean', 'saiz' => 'integer', 'susunan' => 'integer'];
    }
}
