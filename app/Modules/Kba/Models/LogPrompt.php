<?php

namespace App\Modules\Kba\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Prompt log (SRS-KBA-16): prompt, answer, segments retrieved into the
 * context, cited sources, user, roles, IP, model and time. Append-only —
 * updates and deletes are refused at model level; the tamper-evident copy is
 * MOD-AUD's hash-chained trail (action "prompt_ai").
 *
 * @property int $id
 * @property string|null $id_perbualan
 * @property int|null $id_pengguna
 * @property list<string> $peranan
 * @property string|null $alamat_ip
 * @property string $saluran
 * @property string $prompt
 * @property string $prompt_dinormalkan
 * @property string $bahasa
 * @property string|null $jawapan
 * @property string|null $mod_jawapan
 * @property string|null $model_digunakan
 * @property string|null $model_embedding
 * @property list<int> $senarai_id_segmen_dicapai
 * @property list<array<string, mixed>> $senarai_sumber_dipetik
 * @property int $bilangan_token
 * @property int $masa_respons_ms
 * @property float|null $skor_tertinggi
 * @property string|null $modul
 * @property string $status
 * @property string|null $sebab
 * @property Carbon $cap_masa
 */
class LogPrompt extends KbaModel
{
    protected $table = 'kba_log_prompt';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Log prompt tidak boleh dipinda (SRS-KBA-16).'));
        static::deleting(fn () => throw new LogicException('Log prompt tidak boleh dipadam (SRS-KBA-16).'));
    }

    protected function casts(): array
    {
        return [
            'peranan' => 'array',
            'senarai_id_segmen_dicapai' => 'array',
            'senarai_sumber_dipetik' => 'array',
            'cap_masa' => 'datetime',
            'skor_tertinggi' => 'float',
        ];
    }

    /** @return HasOne<MaklumBalas, $this> */
    public function maklumBalas(): HasOne
    {
        return $this->hasOne(MaklumBalas::class, 'id_log_prompt');
    }
}
