<?php

namespace App\Modules\Kes\Models;

use Illuminate\Support\Carbon;

/**
 * Panel peguam MAIS (external law firm). A firm may have a SISDATA user
 * account (`id_pengguna`); that user sees only cases where the firm holds an
 * approved (aktif) appointment — security control "panel peguam luar hanya
 * melihat kes yang ditugaskan kepada mereka".
 *
 * @property int $id
 * @property string $nama_firma
 * @property string|null $no_pendaftaran
 * @property string|null $pegawai_penghubung
 * @property string|null $no_telefon
 * @property string|null $emel
 * @property string|null $alamat
 * @property string $status
 * @property Carbon|null $tarikh_lantikan
 * @property Carbon|null $tarikh_tamat
 * @property int|null $id_pengguna
 * @property bool $sintetik
 */
class PanelPeguam extends KesModel
{
    protected $table = 'kes_panel_peguam';

    public function auditClassification(): string
    {
        return 'sulit';
    }

    protected function casts(): array
    {
        return [
            'no_telefon' => 'encrypted',
            'emel' => 'encrypted',
            'alamat' => 'encrypted',
            'tarikh_lantikan' => 'date:Y-m-d',
            'tarikh_tamat' => 'date:Y-m-d',
            'sintetik' => 'boolean',
        ];
    }

    public function auditSensitive(): array
    {
        return ['no_telefon', 'emel', 'alamat'];
    }
}
