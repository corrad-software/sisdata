<?php

namespace App\Modules\Ntf\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string $modul
 * @property string $keutamaan
 * @property bool $wajib
 * @property list<string> $saluran_lalai
 * @property string $kekerapan
 * @property int|null $tempoh_eskalasi_minit
 * @property int $tempoh_pendiaman_minit
 * @property list<int>|null $hari_peringatan
 * @property list<string> $medan
 * @property list<string> $medan_sensitif
 * @property bool $aktif
 * @property-read Templat|null $templatAktif
 */
class Jenis extends Model implements Auditable
{
    public const SALURAN = ['dalam_sistem', 'emel', 'sms', 'whatsapp'];

    public const KEUTAMAAN = ['kritikal', 'tinggi', 'biasa', 'maklumat'];

    /** Display/sort rank, lowest first (SRS-NTF-08). */
    public const KEDUDUKAN = ['kritikal' => 1, 'tinggi' => 2, 'biasa' => 3, 'maklumat' => 4];

    public const KEKERAPAN = ['serta_merta', 'ringkasan'];

    protected $table = 'ntf_jenis';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
            'aktif' => 'boolean',
            'saluran_lalai' => 'array',
            'hari_peringatan' => 'array',
            'medan' => 'array',
            'medan_sensitif' => 'array',
            'tempoh_eskalasi_minit' => 'integer',
            'tempoh_pendiaman_minit' => 'integer',
        ];
    }

    /** @return HasMany<Templat, $this> */
    public function templat(): HasMany
    {
        return $this->hasMany(Templat::class, 'jenis_id');
    }

    /** @return HasOne<Templat, $this> */
    public function templatAktif(): HasOne
    {
        return $this->hasOne(Templat::class, 'jenis_id')->ofMany(['versi' => 'max'], fn ($q) => $q->where('aktif', true));
    }

    /** @return HasMany<PenerimaPeraturan, $this> */
    public function peraturan(): HasMany
    {
        return $this->hasMany(PenerimaPeraturan::class, 'jenis_id');
    }

    /** Biasa and Maklumat may be bundled into a periodic digest (SRS-NTF-20). */
    public function bolehRingkasan(): bool
    {
        return in_array($this->keutamaan, ['biasa', 'maklumat'], true);
    }

    public function auditModule(): string
    {
        return 'ntf';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
