<?php

namespace App\Modules\Rbc\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS-RBC-02 claim mapping rule: when SSO claim `jenis_tuntutan` matches
 * `nilai`, grant `peranan` and/or the scope `skop_jenis` = `skop_nilai`.
 *
 * @property int $id
 * @property string $jenis_tuntutan
 * @property string $nilai
 * @property int|null $peranan_id
 * @property string|null $skop_jenis
 * @property string|null $skop_nilai
 * @property bool $aktif
 * @property int $keutamaan
 * @property string|null $keterangan
 * @property-read Peranan|null $peranan
 */
class PemetaanTuntutan extends Model
{
    public const JENIS = ['roles', 'bahagian', 'unit', 'jawatan'];

    protected $table = 'pemetaan_tuntutan';

    protected $fillable = ['jenis_tuntutan', 'nilai', 'peranan_id', 'skop_jenis', 'skop_nilai', 'aktif', 'keutamaan', 'keterangan'];

    protected $attributes = ['aktif' => true, 'keutamaan' => 100];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'keutamaan' => 'integer'];
    }

    /** @return BelongsTo<Peranan, $this> */
    public function peranan(): BelongsTo
    {
        return $this->belongsTo(Peranan::class, 'peranan_id');
    }
}
