<?php

namespace App\Modules\Pjk\Models;

/**
 * SRS-PJK-09: configurable letter template; editing creates a new version.
 *
 * @property int $id
 * @property string $kod
 * @property string $jenis_surat
 * @property string $tajuk
 * @property string $kandungan
 * @property list<string> $medan_gabungan
 * @property int $versi
 * @property bool $status_aktif
 */
class TemplatSurat extends PjkModel
{
    protected $table = 'templat_surat';

    protected function casts(): array
    {
        return ['medan_gabungan' => 'array', 'status_aktif' => 'boolean', 'versi' => 'integer'];
    }
}
