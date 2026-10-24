<?php

namespace App\Modules\Ptn\Models;

/**
 * SRS-PTN-04: a versioned checklist template. A change creates a new version
 * (same kod); visits keep the version they were recorded with.
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string|null $kategori_lot
 * @property string $jenis_pemeriksaan
 * @property list<array{kod: string, kategori: string, label: string, kategori_isu?: string}> $senarai_item
 * @property int $versi
 * @property bool $status_aktif
 * @property int|null $id_pencipta
 */
class TemplatSenaraiSemak extends PtnModel
{
    protected $table = 'templat_senarai_semak';

    protected $casts = ['senarai_item' => 'array', 'status_aktif' => 'boolean', 'versi' => 'integer'];
}
