<?php

namespace App\Modules\Kba\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An indexed source document (SRS-KBA-01/08): baseline doc, uploaded manual /
 * SOP, DMS document or published article.
 *
 * @property int $id
 * @property string $kod
 * @property string $asal
 * @property string|null $id_dokumen_dms
 * @property string|null $laluan_fail
 * @property string $jenis_dokumen
 * @property string $tajuk
 * @property string|null $versi
 * @property int $no_versi
 * @property string|null $modul
 * @property int|null $id_pemilik
 * @property string $klasifikasi
 * @property array<array-key, string> $senarai_peranan_dibenarkan
 * @property string|null $kod_daerah
 * @property list<array{padanan: string, klasifikasi?: string, peranan?: list<string>}> $peraturan_seksyen
 * @property string $format
 * @property string|null $teks_sumber
 * @property string|null $cincangan_kandungan
 * @property string $status_indeks
 * @property string|null $mesej_indeks
 * @property int $bilangan_segmen
 * @property Carbon|null $tarikh_indeks_akhir
 * @property bool $aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SumberDokumen extends KbaModel
{
    protected $table = 'kba_sumber_dokumen';

    public const ASAL = ['baseline', 'dms', 'muat_naik', 'artikel'];

    public const JENIS = [
        'URS', 'BRS', 'SRS', 'SDS', 'reka_bentuk', 'rajah_aliran', 'manual_pengguna', 'manual_pentadbir', 'manual_operasi',
        'manual_teknikal', 'SOP', 'nota_keluaran', 'artikel_tiket', 'artikel', 'dokumen_dms', 'lain',
    ];

    public const KLASIFIKASI = ['awam', 'dalaman', 'sulit', 'rahsia'];

    protected $hidden = ['teks_sumber'];

    /** DB defaults, so a freshly created model is complete before refresh(). */
    protected $attributes = [
        'senarai_peranan_dibenarkan' => '[]', 'peraturan_seksyen' => '[]', 'aktif' => true, 'no_versi' => 0,
        'status_indeks' => 'menunggu', 'bilangan_segmen' => 0, 'format' => 'markdown', 'klasifikasi' => 'dalaman',
    ];

    protected function casts(): array
    {
        return [
            'senarai_peranan_dibenarkan' => 'array',
            'peraturan_seksyen' => 'array',
            'tarikh_indeks_akhir' => 'datetime',
            'aktif' => 'boolean',
            'no_versi' => 'integer',
            'bilangan_segmen' => 'integer',
        ];
    }

    /** @return HasMany<Segmen, $this> */
    public function segmen(): HasMany
    {
        return $this->hasMany(Segmen::class, 'id_sumber');
    }

    public function versiPaparan(): string
    {
        return $this->versi ?? ('v'.$this->no_versi);
    }
}
