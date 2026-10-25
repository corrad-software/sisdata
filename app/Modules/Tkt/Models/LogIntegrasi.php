<?php

namespace App\Modules\Tkt\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-TKT-16: integration transaction log + outbox (retry, reconciliation).
 *
 * @property int $id
 * @property string|null $id_tiket
 * @property string $arah
 * @property string $endpoint
 * @property string|null $kunci_idempotensi
 * @property string|null $ringkasan_payload
 * @property array<string, mixed>|null $muatan
 * @property string $status
 * @property int|null $kod_status
 * @property int $bilangan_cuba_semula
 * @property Carbon|null $cuba_seterusnya_pada
 * @property string|null $mesej_ralat
 * @property Carbon $cap_masa
 */
class LogIntegrasi extends TktModel
{
    protected $table = 'tkt_log_integrasi';

    protected function casts(): array
    {
        return ['muatan' => 'array', 'cap_masa' => 'datetime', 'cuba_seterusnya_pada' => 'datetime', 'bilangan_cuba_semula' => 'integer', 'kod_status' => 'integer'];
    }
}
