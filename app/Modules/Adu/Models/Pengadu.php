<?php

namespace App\Modules\Adu\Models;

use App\Modules\Aud\Contracts\HasClassification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SRS-ADU-20/21: the reporter's identity. Sulit; name, phone, e-mail and
 * address are encrypted at rest (Laravel `encrypted` cast, APP_KEY / AES-256)
 * and masked in the audit trail. Only keyed hashes are queryable (status
 * check SRS-ADU-04, rate limit SRS-ADU-24). Anonymous complaints have no row.
 *
 * @property int $id
 * @property string $aduan_id
 * @property string|null $no_rujukan_aduan
 * @property string|null $nama
 * @property string|null $no_telefon
 * @property string|null $emel
 * @property string|null $alamat
 * @property string|null $cincangan_emel
 * @property string|null $cincangan_telefon
 * @property string $saluran_makluman
 * @property bool $status_penyulitan
 * @property bool $status_tanpa_nama
 * @property string $klasifikasi_data
 * @property bool $sintetik
 * @property Carbon $created_at
 */
class Pengadu extends AduModel implements HasClassification
{
    protected $table = 'pengadu';

    public const MEDAN_IDENTITI = ['nama', 'no_telefon', 'emel', 'alamat'];

    protected function casts(): array
    {
        return [
            'nama' => 'encrypted',
            'no_telefon' => 'encrypted',
            'emel' => 'encrypted',
            'alamat' => 'encrypted',
            'status_penyulitan' => 'boolean',
            'status_tanpa_nama' => 'boolean',
            'sintetik' => 'boolean',
        ];
    }

    public function auditSensitive(): array
    {
        return [...self::MEDAN_IDENTITI, 'cincangan_emel', 'cincangan_telefon'];
    }

    public function auditClassification(): string
    {
        return 'sulit';
    }

    /** Keyed hash for lookups without decrypting (lower-cased e-mail, digits-only phone). */
    public static function cincang(?string $value, string $jenis): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $norm = $jenis === 'telefon' ? self::normalTelefon($value) : mb_strtolower(trim($value));

        return hash_hmac('sha256', $jenis.':'.$norm, (string) config('app.key'));
    }

    public static function normalTelefon(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return str_starts_with($digits, '60') ? '0'.substr($digits, 2) : $digits;
    }

    /** @return array<string, string|null> masked values for display without access */
    public function bertopeng(): array
    {
        $mask = fn (?string $v, int $keep) => $v === null || $v === '' ? null : mb_substr($v, 0, $keep).str_repeat('•', max(3, mb_strlen($v) - $keep));

        return [
            'nama' => $mask($this->nama, 1),
            'no_telefon' => $this->no_telefon ? str_repeat('•', max(3, strlen($this->no_telefon) - 3)).substr($this->no_telefon, -3) : null,
            'emel' => $this->emel ? $mask(strtok($this->emel, '@') ?: '', 1).'@'.(explode('@', $this->emel)[1] ?? '') : null,
            'alamat' => $this->alamat ? '••••••' : null,
        ];
    }

    /** @return BelongsTo<Aduan, $this> */
    public function aduan(): BelongsTo
    {
        return $this->belongsTo(Aduan::class, 'aduan_id');
    }
}
