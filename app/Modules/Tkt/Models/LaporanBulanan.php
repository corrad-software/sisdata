<?php

namespace App\Modules\Tkt\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TKT-19: generated monthly support report (figures + DMS copies).
 *
 * @property int $id
 * @property string $bulan
 * @property array<string, mixed> $ringkasan
 * @property string|null $id_dokumen_pdf
 * @property string|null $id_dokumen_xlsx
 * @property string|null $ralat
 * @property Carbon $dijana_pada
 * @property int|null $id_penjana
 */
class LaporanBulanan extends TktModel
{
    protected $table = 'tkt_laporan_bulanan';

    protected function casts(): array
    {
        return ['ringkasan' => 'array', 'dijana_pada' => 'datetime'];
    }
}
