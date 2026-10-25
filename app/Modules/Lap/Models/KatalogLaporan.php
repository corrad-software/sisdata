<?php

namespace App\Modules\Lap\Models;

use Illuminate\Support\Carbon;

/**
 * Central report catalogue entry (SRS-LAP-02/09/23).
 *
 * @property int $id
 * @property string $kod_laporan
 * @property string $nama
 * @property string $kategori
 * @property string $jenis lap | modul | komposit
 * @property string|null $keterangan
 * @property string|null $definisi_metrik
 * @property string|null $sumber_data
 * @property string|null $pemilik
 * @property string|null $kebenaran
 * @property list<string>|null $peranan_dibenarkan
 * @property string|null $set_data
 * @property array<string, mixed>|null $definisi
 * @property list<string>|null $bahagian
 * @property string|null $pautan
 * @property string $status_pengesahan
 * @property int|null $disahkan_oleh
 * @property Carbon|null $disahkan_pada
 * @property bool $aktif
 * @property int $susunan
 */
class KatalogLaporan extends LapModel
{
    protected $table = 'lap_katalog_laporan';

    protected function casts(): array
    {
        return ['peranan_dibenarkan' => 'array', 'definisi' => 'array', 'bahagian' => 'array', 'aktif' => 'boolean', 'disahkan_pada' => 'datetime'];
    }
}
