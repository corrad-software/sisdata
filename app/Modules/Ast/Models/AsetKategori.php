<?php

namespace App\Modules\Ast\Models;

/**
 * SRS-AST-03: configurable asset category with its maintenance cycle
 * (SRS-AST-15), revaluation threshold (SRS-AST-19), inspection period
 * (SRS-AST-21) and straight-line depreciation parameters.
 *
 * @property int $id
 * @property string $kod
 * @property string $nama_kategori
 * @property string $jenis_lalai
 * @property int $kitaran_penyelenggaraan_bulan
 * @property int $tempoh_ambang_penilaian_bulan
 * @property int $tempoh_pemeriksaan_bulan
 * @property int $hayat_guna_tahun
 * @property string $kadar_nilai_sisa
 * @property list<string> $jenis_penyelenggaraan_lalai
 * @property bool $status_aktif
 * @property int $susunan
 */
class AsetKategori extends AstModel
{
    protected $table = 'aset_kategori';

    protected $casts = ['jenis_penyelenggaraan_lalai' => 'array', 'status_aktif' => 'boolean', 'kadar_nilai_sisa' => 'decimal:2'];
}
