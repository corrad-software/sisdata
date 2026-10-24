<?php

namespace App\Modules\Adu\Models;

/**
 * SRS-ADU-02 / GEN-04: ADU-side metadata of a file stored in MOD-DMS.
 *
 * @property int $id
 * @property string $aduan_id
 * @property string $id_dokumen
 * @property string $jenis_dokumen
 * @property string|null $laluan_simpanan
 * @property int|null $saiz
 * @property string|null $jenis_fail
 * @property string $sumber_muat_naik
 * @property string|null $lat
 * @property string|null $lng
 */
class DokumenAduan extends AduModel
{
    protected $table = 'dokumen_aduan';
}
