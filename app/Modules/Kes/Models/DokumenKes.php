<?php

namespace App\Modules\Kes\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-KES-12: case-side index of a legal document stored in MOD-DMS
 * (pleading, affidavit, order, judgment…), with category and version.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property string $id_dokumen_dms
 * @property string $kategori_dokumen
 * @property string|null $nama_fail
 * @property int $versi
 * @property Carbon $tarikh_muat_naik
 * @property int|null $id_pengguna
 * @property string $sumber
 */
class DokumenKes extends KesModel
{
    protected $table = 'kes_dokumen';

    public const KATEGORI = ['surat_tuntutan', 'pliding', 'afidavit', 'perintah_mahkamah', 'penghakiman', 'perjanjian_penyelesaian', 'laporan_peguam', 'dokumen_aduan', 'lain'];

    protected function casts(): array
    {
        return ['tarikh_muat_naik' => 'datetime', 'versi' => 'integer'];
    }
}
