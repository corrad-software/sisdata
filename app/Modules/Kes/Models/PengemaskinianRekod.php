<?php

namespace App\Modules\Kes\Models;

use Illuminate\Support\Carbon;

/**
 * SRS-KES-18: a title / land-status update made on the strength of a court
 * order, cross-referenced to the case number and the order document
 * (append-only). The change itself is proposed to MOD-HKM through
 * Hkm\Contracts\TitleChangeProposals (id_cadangan_hkm).
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property int|null $kes_tindakan_susulan_id
 * @property string|null $lot_id
 * @property string $jenis_rekod
 * @property string|null $no_hak_milik
 * @property string|null $jenis_perubahan
 * @property string $ringkasan_perubahan
 * @property string $no_kes_mahkamah
 * @property string $id_dokumen_perintah
 * @property string|null $id_cadangan_hkm
 * @property int|null $id_pengguna
 * @property Carbon $created_at
 */
class PengemaskinianRekod extends Sejarah
{
    protected $table = 'kes_pengemaskinian_rekod';
}
