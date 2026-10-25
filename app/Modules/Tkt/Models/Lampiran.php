<?php

namespace App\Modules\Tkt\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TKT-03: attachment index; the file itself lives in MOD-DMS (type,
 * size and antivirus controls are DMS's). Screenshots carry the consent flag.
 *
 * @property int $id
 * @property string $id_tiket
 * @property string $id_dokumen
 * @property string $nama_fail
 * @property string|null $jenis_fail
 * @property int $saiz
 * @property string $status_imbasan
 * @property string $lokasi_storan
 * @property bool $tangkapan_skrin
 * @property bool $persetujuan_tangkapan_skrin
 * @property Carbon|null $persetujuan_pada
 * @property bool $kelihatan_pelapor
 * @property int|null $id_pemuat_naik
 * @property Carbon $created_at
 */
class Lampiran extends TktModel
{
    protected $table = 'tkt_lampiran';

    protected function casts(): array
    {
        return ['tangkapan_skrin' => 'boolean', 'persetujuan_tangkapan_skrin' => 'boolean', 'kelihatan_pelapor' => 'boolean', 'persetujuan_pada' => 'datetime', 'saiz' => 'integer'];
    }
}
