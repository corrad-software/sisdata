<?php

namespace App\Modules\Cki\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-CKI-06 `daftar_liputan_integrasi`: per Pejabat Tanah / PBT, whether
 * the central API's enquiry and payment services are available, plus the
 * agency's current operating mode (api | sandaran — SRS-CKI-08).
 *
 * @property int $id
 * @property string $kod_agensi
 * @property string $jenis_agensi
 * @property string|null $kod_pbt
 * @property string|null $kod_ptd
 * @property string $nama_agensi
 * @property bool $perkhidmatan_semakan
 * @property bool $perkhidmatan_bayaran
 * @property string $mod_semasa
 * @property int $kegagalan_berturut
 * @property Carbon|null $semakan_terakhir
 * @property string|null $catatan
 * @property Carbon|null $tarikh_kemas_kini
 */
class Liputan extends CkiModel
{
    protected $table = 'daftar_liputan_integrasi';

    protected $casts = [
        'perkhidmatan_semakan' => 'boolean', 'perkhidmatan_bayaran' => 'boolean', 'kegagalan_berturut' => 'integer',
        'semakan_terakhir' => 'datetime', 'tarikh_kemas_kini' => 'datetime',
    ];

    public function auditExcept(): array
    {
        return ['updated_at', 'created_at', 'semakan_terakhir', 'kegagalan_berturut'];
    }

    public function dalamSandaran(): bool
    {
        return $this->mod_semasa === 'sandaran';
    }
}
