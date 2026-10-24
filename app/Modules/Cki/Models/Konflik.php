<?php

namespace App\Modules\Cki\Models;

use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-CKI-09 `konflik_penyegerakan`: manual vs API values of a bill found
 * during re-synchronisation; both versions are kept here and in the audit
 * trail, and an officer decides which one stands.
 *
 * @property int $id
 * @property string $id_bil
 * @property array<string, mixed> $nilai_manual
 * @property array<string, mixed> $nilai_api
 * @property list<string> $medan_berkonflik
 * @property string $status_resolusi
 * @property int|null $id_pegawai_pengesah
 * @property Carbon|null $tarikh_resolusi
 * @property string|null $catatan
 * @property string|null $kod_daerah
 * @property Carbon $created_at
 * @property-read Cukai|null $bil
 */
class Konflik extends CkiModel
{
    use ScopedByAccess;

    protected $table = 'konflik_penyegerakan';

    protected array $accessScopes = ['daerah' => 'kod_daerah'];

    protected $casts = ['nilai_manual' => 'array', 'nilai_api' => 'array', 'medan_berkonflik' => 'array', 'tarikh_resolusi' => 'datetime'];

    /** @return BelongsTo<Cukai, $this> */
    public function bil(): BelongsTo
    {
        return $this->belongsTo(Cukai::class, 'id_bil')->withoutGlobalScope('access');
    }
}
