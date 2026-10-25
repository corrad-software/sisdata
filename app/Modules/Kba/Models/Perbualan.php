<?php

namespace App\Modules\Kba\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A user's Q&A conversation (history per user). Hiding one only hides it from
 * the user's list — the prompt log behind it is immutable (SRS-KBA-16).
 *
 * @property string $id
 * @property int $id_pengguna
 * @property string $tajuk
 * @property bool $disembunyikan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Perbualan extends KbaModel
{
    use HasUuids;

    protected $table = 'kba_perbualan';

    protected function casts(): array
    {
        return ['disembunyikan' => 'boolean'];
    }

    /** @return HasMany<LogPrompt, $this> */
    public function log(): HasMany
    {
        return $this->hasMany(LogPrompt::class, 'id_perbualan')->orderBy('id');
    }
}
