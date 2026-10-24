<?php

namespace App\Modules\Ast\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-AST-11: free (bebas) or structured (berstruktur) tag.
 *
 * @property int $id
 * @property string $id_aset
 * @property string $nama_tag
 * @property string $jenis_tag
 * @property int|null $id_pengguna
 * @property Carbon $tarikh_tambah
 */
class AsetTag extends AstModel
{
    protected $table = 'aset_tag';

    public $timestamps = false;

    protected $casts = ['tarikh_tambah' => 'datetime'];
}
