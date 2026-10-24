<?php

namespace App\Modules\Ptn\Models;

/**
 * SRS-PTN-04: one checklist item result (baik | isu | kritikal + catatan).
 *
 * @property int $id
 * @property string $id_lawatan
 * @property int|null $id_templat_senarai_semak
 * @property string $kod_item
 * @property string $kategori_item
 * @property string $label
 * @property string $status_item
 * @property string|null $catatan
 * @property string|null $kategori_isu
 * @property string|null $tahap_keterukan
 */
class ItemSenaraiSemak extends PtnModel
{
    protected $table = 'item_senarai_semak';
}
