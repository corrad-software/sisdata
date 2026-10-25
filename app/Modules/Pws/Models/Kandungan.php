<?php

namespace App\Modules\Pws\Models;

use Illuminate\Support\Carbon;

/**
 * Governed portal content (entity pws_kandungan_cms). Always public (Awam)
 * once published; publishing goes through MOD-WFE (SRS-PWS-04, SRS-GEN-03).
 *
 * @property int $id
 * @property string $jenis
 * @property string $slug
 * @property string $tajuk_bm
 * @property string|null $tajuk_bi
 * @property string $kandungan_bm
 * @property string|null $kandungan_bi
 * @property string|null $ringkasan
 * @property int|null $kemajuan_peratus
 * @property string|null $lokasi
 * @property string $status_terbitan
 * @property bool $dijana_ai
 * @property string|null $prompt_ai
 * @property string|null $model_ai
 * @property int|null $id_pengarang
 * @property int|null $id_penyemak
 * @property string|null $id_aliran
 * @property string|null $catatan_semakan
 * @property Carbon|null $tarikh_terbit
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Kandungan extends PwsModel
{
    protected $table = 'pws_kandungan_cms';

    public const JENIS = [
        'artikel' => 'Artikel / berita',
        'hebahan' => 'Hebahan / pengumuman',
        'soal_jawab' => 'Soal-jawab',
        'promosi' => 'Kandungan promosi',
        'projek' => 'Projek wakaf',
        'halaman' => 'Halaman',
    ];

    public const STATUS = [
        'draf' => 'Draf',
        'dalam_semakan' => 'Dalam semakan',
        'diterbitkan' => 'Diterbitkan',
        'ditolak' => 'Ditolak',
        'ditarik' => 'Ditarik balik',
    ];

    protected function casts(): array
    {
        return ['dijana_ai' => 'boolean', 'tarikh_terbit' => 'datetime', 'kemajuan_peratus' => 'integer'];
    }
}
