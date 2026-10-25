<?php

namespace App\Modules\Lap\Models;

/**
 * An approved Metabase dashboard / question embedded in SISDATA (SRS-LAP-01).
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string|null $keterangan
 * @property string $jenis dashboard | question
 * @property int $id_metabase
 * @property string|null $kebenaran
 * @property array<string, string>|null $parameter_kunci Metabase param => RBC scope type
 * @property bool $aktif
 */
class PapanBi extends LapModel
{
    protected $table = 'lap_papan_bi';

    protected function casts(): array
    {
        return ['parameter_kunci' => 'array', 'aktif' => 'boolean', 'id_metabase' => 'integer'];
    }
}
