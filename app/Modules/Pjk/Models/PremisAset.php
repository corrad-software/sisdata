<?php

namespace App\Modules\Pjk\Models;

use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * SRS-PJK-03/04: a premise or plot of land offered for rent, on a lot.
 * Scoped by daerah/mukim through the lot's codes (copied at registration).
 *
 * @property int $id
 * @property string $lot_id
 * @property string $kod_premis
 * @property string $nama_premis
 * @property string $jenis_aset
 * @property string $kategori_hartanah
 * @property string|null $saluran_pemerolehan
 * @property string|null $keluasan
 * @property string|null $alamat
 * @property string $status_premis
 * @property Carbon|null $tarikh_status
 * @property string|null $kadar_tawaran
 * @property string $kod_daerah
 * @property string $kod_mukim
 * @property string|null $bahagian
 * @property bool $papar_portal
 * @property string|null $keterangan_awam
 * @property string $klasifikasi
 * @property bool $sintetik
 */
class PremisAset extends PjkModel
{
    use ScopedByAccess, SoftDeletes;

    protected $table = 'premis_aset';

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'];

    protected function casts(): array
    {
        return ['tarikh_status' => 'date:Y-m-d', 'papar_portal' => 'boolean', 'sintetik' => 'boolean'];
    }

    /** @return HasMany<Kontrak, $this> */
    public function kontrak(): HasMany
    {
        return $this->hasMany(Kontrak::class, 'premis_id');
    }

    /** @return HasMany<SejarahStatusPremis, $this> */
    public function sejarahStatus(): HasMany
    {
        return $this->hasMany(SejarahStatusPremis::class, 'premis_id');
    }
}
