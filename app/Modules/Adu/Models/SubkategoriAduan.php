<?php

namespace App\Modules\Adu\Models;

/**
 * SRS-ADU-08: configurable subcategory under a MOD-REF ref_kategori_aduan code.
 *
 * @property int $id
 * @property string $kod_kategori
 * @property string $kod
 * @property string $nama
 * @property bool $aktif
 * @property int $susunan
 */
class SubkategoriAduan extends AduModel
{
    protected $table = 'subkategori_aduan';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
