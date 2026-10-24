<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-06: one issue of an original document and its return.
 *
 * @property int $id
 * @property int $daftar_peti_besi_id
 * @property Carbon $tarikh_pengeluaran
 * @property int|null $id_pegawai_keluar
 * @property string $penerima_nama
 * @property string|null $penerima_jawatan
 * @property string $tujuan_pengeluaran
 * @property string $status_semasa_dikeluarkan
 * @property Carbon $tarikh_jangka_pulang
 * @property Carbon|null $tarikh_pulang_sebenar
 * @property int|null $id_pegawai_terima_pulang
 * @property string|null $keadaan_dokumen_pulang
 * @property string|null $catatan_pulang
 * @property string $status_pengeluaran
 * @property-read DaftarPetiBesi $daftar
 * @property-read User|null $pegawaiKeluar
 * @property-read User|null $pegawaiTerimaPulang
 */
class PengeluaranDokumen extends Model implements Auditable, HasClassification
{
    public const DIKELUARKAN = 'dikeluarkan';

    public const DIPULANGKAN = 'dipulangkan';

    protected $table = 'pengeluaran_dokumen';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tarikh_pengeluaran' => 'datetime',
            'tarikh_jangka_pulang' => 'date:Y-m-d',
            'tarikh_pulang_sebenar' => 'datetime',
        ];
    }

    public function auditModule(): string
    {
        return 'hkm';
    }

    public function auditExcept(): array
    {
        return ['updated_at', 'created_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }

    public function auditClassification(): string
    {
        return 'sulit';
    }

    /** @return BelongsTo<DaftarPetiBesi, $this> */
    public function daftar(): BelongsTo
    {
        return $this->belongsTo(DaftarPetiBesi::class, 'daftar_peti_besi_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawaiKeluar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_keluar');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawaiTerimaPulang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_terima_pulang');
    }

    public function lewat(): bool
    {
        return $this->status_pengeluaran === self::DIKELUARKAN && $this->tarikh_jangka_pulang->lt(today());
    }
}
