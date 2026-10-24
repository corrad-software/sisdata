<?php

namespace App\Modules\Cki\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-CKI-15: tax ledger entry (append-only — DB trigger).
 *
 * @property int $id
 * @property string $id_bil
 * @property Carbon $tarikh
 * @property string $jenis
 * @property string $debit
 * @property string $kredit
 * @property string|null $rujukan
 * @property string|null $keterangan
 * @property int|null $id_pengguna
 */
class Lejar extends CkiModel
{
    public const UPDATED_AT = null;

    protected $table = 'lejar_cukai';

    protected $casts = ['tarikh' => 'date'];
}
