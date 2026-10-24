<?php

namespace App\Modules\Tsu\Models;

/**
 * @property int $id
 * @property int $templat_id
 * @property int $no_urutan
 * @property string $kod
 * @property string $nama_peringkat
 * @property string|null $tindakan_diperlukan
 * @property string $pihak_bertanggungjawab
 * @property int $tempoh_sasaran_hari
 * @property list<string> $senarai_dokumen_wajib
 * @property list<string> $senarai_kategori_kos
 * @property bool $perlu_kelulusan
 * @property string|null $kesan
 */
class TemplatPeringkat extends TsuModel
{
    protected $table = 'templat_peringkat';

    protected $casts = ['senarai_dokumen_wajib' => 'array', 'senarai_kategori_kos' => 'array', 'perlu_kelulusan' => 'boolean'];
}
