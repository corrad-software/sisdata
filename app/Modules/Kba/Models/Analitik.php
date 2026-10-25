<?php

namespace App\Modules\Kba\Models;

/**
 * Monthly roll-up of questions (SRS-KBA-18), refreshed by `kba:analitik`.
 *
 * @property int $id
 * @property string $soalan_dinormalkan
 * @property int $bilangan_ditanya
 * @property int $kadar_jawapan_memuaskan
 * @property int|null $id_sumber_paling_dirujuk
 * @property int|null $id_artikel_paling_dirujuk
 * @property string|null $modul
 * @property string $tempoh
 */
class Analitik extends KbaModel
{
    protected $table = 'kba_analitik';
}
