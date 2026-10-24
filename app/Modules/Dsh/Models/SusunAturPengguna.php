<?php

namespace App\Modules\Dsh\Models;

/**
 * @property int $id
 * @property int $id_pengguna
 * @property string $aras
 * @property string $kod_widget
 * @property int $posisi
 * @property int $saiz
 * @property bool $tersembunyi
 * @property string|null $jenis_visual
 */
class SusunAturPengguna extends DshModel
{
    protected $table = 'dsh_susun_atur_pengguna';

    protected function casts(): array
    {
        return ['tersembunyi' => 'boolean', 'posisi' => 'integer', 'saiz' => 'integer'];
    }
}
