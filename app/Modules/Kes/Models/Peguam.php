<?php

namespace App\Modules\Kes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS-KES-04: counsel for a party. MAIS's own counsel may be a panel firm;
 * a panel appointment is approved through MOD-WFE (`kes.lantikan_panel`)
 * before the firm's users can see the case.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property int|null $kes_pihak_id
 * @property int|null $kes_panel_peguam_id
 * @property string $nama_peguam
 * @property string|null $nama_firma
 * @property string|null $pegawai_penghubung
 * @property string|null $no_telefon
 * @property string|null $emel
 * @property bool $indikator_panel_mais
 * @property string $status_lantikan
 * @property string|null $id_instans_aliran
 * @property bool $sintetik
 * @property-read PanelPeguam|null $panel
 * @property-read Pihak|null $pihak
 */
class Peguam extends KesModel
{
    protected $table = 'kes_peguam';

    protected function casts(): array
    {
        return [
            'no_telefon' => 'encrypted',
            'emel' => 'encrypted',
            'indikator_panel_mais' => 'boolean',
            'sintetik' => 'boolean',
        ];
    }

    public function auditSensitive(): array
    {
        return ['no_telefon', 'emel'];
    }

    /** @return BelongsTo<PanelPeguam, $this> */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(PanelPeguam::class, 'kes_panel_peguam_id');
    }

    /** @return BelongsTo<Pihak, $this> */
    public function pihak(): BelongsTo
    {
        return $this->belongsTo(Pihak::class, 'kes_pihak_id');
    }

    /** @return BelongsTo<KesMahkamah, $this> */
    public function kes(): BelongsTo
    {
        return $this->belongsTo(KesMahkamah::class, 'kes_mahkamah_id')->withoutGlobalScope('access');
    }
}
