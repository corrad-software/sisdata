<?php

namespace App\Modules\Ast\Models;

use Illuminate\Support\Carbon;

/**
 * Maintenance contractor; id_pengguna is the contractor's limited-access account (ast.kerja.kemas_kini on its own work orders only).
 *
 * @property int $id
 * @property string $nama_syarikat
 * @property string|null $no_pendaftaran
 * @property string|null $no_telefon
 * @property string|null $emel
 * @property list<string> $bidang
 * @property Carbon|null $tarikh_tamat_pendaftaran
 * @property int|null $id_pengguna
 * @property bool $status_aktif
 * @property bool $sintetik
 */
class AsetKontraktor extends AstModel
{
    protected $table = 'aset_kontraktor';

    protected $casts = ['bidang' => 'array', 'tarikh_tamat_pendaftaran' => 'date', 'status_aktif' => 'boolean', 'sintetik' => 'boolean'];
}
