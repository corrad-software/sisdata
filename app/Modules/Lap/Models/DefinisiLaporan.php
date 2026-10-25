<?php

namespace App\Modules\Lap\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Saved ad hoc report definition (SRS-LAP-03). Shared definitions are always
 * executed with the OPENER's permissions and scope, never the creator's.
 *
 * @property string $id
 * @property string|null $kod_laporan
 * @property string $nama
 * @property string|null $keterangan
 * @property string $set_data
 * @property array<string, mixed> $pertanyaan
 * @property array<string, mixed>|null $parameter_lalai
 * @property int $id_pencipta
 * @property string $status_kongsi peribadi | peranan | semua
 * @property list<string>|null $kongsi_peranan
 * @property string $sumber
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $pencipta
 */
class DefinisiLaporan extends LapModel
{
    use HasUuids, SoftDeletes;

    public const KONGSI = ['peribadi', 'peranan', 'semua'];

    protected $table = 'lap_definisi_laporan';

    protected function casts(): array
    {
        return ['pertanyaan' => 'array', 'parameter_lalai' => 'array', 'kongsi_peranan' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function pencipta(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pencipta');
    }
}
