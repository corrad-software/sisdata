<?php

namespace App\Modules\Hkm\Models;

use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\HasClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-HKM-21: a registration/amendment lodged at the Land Office, tracked by
 * presentation number until done.
 *
 * @property int $id
 * @property string $hak_milik_id
 * @property string $jenis_urusan
 * @property string $pejabat_pendaftaran
 * @property string $no_perserahan
 * @property Carbon $tarikh_perserahan
 * @property string $status_urusan
 * @property Carbon|null $tarikh_selesai
 * @property string|null $catatan
 * @property string|null $permohonan_hak_milik_kekal_id
 * @property int|null $id_pegawai
 */
class UrusanPendaftaranPt extends Model implements Auditable, HasClassification
{
    protected $table = 'urusan_pendaftaran_pt';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tarikh_perserahan' => 'date:Y-m-d', 'tarikh_selesai' => 'date:Y-m-d'];
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

    /** @return BelongsTo<HakMilik, $this> */
    public function hakMilik(): BelongsTo
    {
        return $this->belongsTo(HakMilik::class, 'hak_milik_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai');
    }
}
