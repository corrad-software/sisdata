<?php

namespace App\Modules\Adu\Models;

/**
 * SRS-ADU-09: configurable triage rule — keyword → suggested category /
 * priority, or the repeat-complaint window (days) and radius (metres).
 *
 * @property int $id
 * @property string $jenis_peraturan kata_kunci | semakan_berulang
 * @property list<string>|null $kata_kunci
 * @property string|null $kategori_cadangan
 * @property string|null $keutamaan_cadangan
 * @property int|null $tempoh_semakan_berulang
 * @property int|null $jejari_meter
 * @property bool $status_aktif
 */
class PeraturanTriaj extends AduModel
{
    protected $table = 'peraturan_triaj';

    protected function casts(): array
    {
        return ['kata_kunci' => 'array', 'status_aktif' => 'boolean'];
    }
}
