<?php

namespace App\Modules\Cki\Models;

use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;

/**
 * SRS-CKI-15/20 `penyelarasan_grp_cukai`: the GRP reconciliation record of a
 * completed payment, and the deviations found by a reconciliation run.
 *
 * @property int $id
 * @property int|null $id_larian
 * @property string|null $id_transaksi
 * @property string|null $id_bil
 * @property int|null $id_resit
 * @property string $jenis
 * @property string|null $rujukan_grp
 * @property string|null $amaun_sistem
 * @property string|null $amaun_grp
 * @property string|null $amaun_resit
 * @property string $status_padanan
 * @property string|null $catatan_sisihan
 * @property string|null $kod_daerah
 */
class Penyelarasan extends CkiModel
{
    use ScopedByAccess;

    protected $table = 'penyelarasan_grp_cukai';

    protected array $accessScopes = ['daerah' => 'kod_daerah'];
}
