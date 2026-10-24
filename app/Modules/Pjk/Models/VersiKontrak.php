<?php

namespace App\Modules\Pjk\Models;

/**
 * SRS-PJK-10: one version of a contract with before/after data and DMS
 * document ids (append-only).
 *
 * @property int $id_versi
 * @property string $kontrak_id
 * @property int $no_versi
 * @property string $jenis_pindaan
 * @property array<string, mixed>|null $data_sebelum
 * @property array<string, mixed> $data_selepas
 */
class VersiKontrak extends PjkModel
{
    public const UPDATED_AT = null;

    protected $table = 'versi_kontrak';

    protected $primaryKey = 'id_versi';

    protected function casts(): array
    {
        return ['data_sebelum' => 'array', 'data_selepas' => 'array', 'tarikh_pindaan' => 'date:Y-m-d'];
    }
}
