<?php

namespace App\Modules\Tsu\Models;

use Illuminate\Support\Carbon;

/**
 * Panel of licensed surveyors (SRS-TSU-08..10). Contact details are synthetic in dev.
 *
 * @property int $id
 * @property string $nama_firma
 * @property string $no_lesen_jurukur
 * @property string $nama_jurukur_bertanggungjawab
 * @property array<string, mixed> $maklumat_perhubungan
 * @property int|null $pengalaman_tahun
 * @property string $status_panel
 * @property Carbon $tarikh_tamat_lesen
 * @property int|null $id_pengguna
 * @property bool $sintetik
 */
class PanelJurukur extends TsuModel
{
    protected $table = 'panel_jurukur';

    protected $casts = ['maklumat_perhubungan' => 'array', 'tarikh_tamat_lesen' => 'date', 'sintetik' => 'boolean'];

    public function auditSensitive(): array
    {
        return ['maklumat_perhubungan'];
    }

    public function layak(): bool
    {
        return $this->status_panel === 'aktif' && $this->tarikh_tamat_lesen->gte(today());
    }
}
