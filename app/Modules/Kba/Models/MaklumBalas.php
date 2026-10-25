<?php

namespace App\Modules\Kba\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-KBA-17: user feedback on one answer, routed to content admins.
 *
 * @property int $id
 * @property int $id_log_prompt
 * @property string $penilaian
 * @property string|null $ulasan
 * @property int|null $id_pengguna
 * @property Carbon $cap_masa
 * @property string $status_tindakan
 * @property string|null $catatan_tindakan
 * @property int|null $id_pentadbir
 */
class MaklumBalas extends KbaModel
{
    protected $table = 'kba_maklum_balas';

    public const PENILAIAN = ['membantu', 'tidak_membantu', 'sumber_salah'];

    public const STATUS = ['baharu', 'dalam_tindakan', 'selesai', 'ditolak'];

    /** Feedback is reviewed for content, not people. */
    protected $hidden = ['id_pengguna'];

    protected function casts(): array
    {
        return ['cap_masa' => 'datetime'];
    }

    /** @return BelongsTo<LogPrompt, $this> */
    public function log(): BelongsTo
    {
        return $this->belongsTo(LogPrompt::class, 'id_log_prompt');
    }
}
