<?php

namespace App\Modules\Kes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS-KES-03: a party to the case. Name, IC and contact are personal data —
 * encrypted at rest (Laravel `encrypted` cast, APP_KEY / AES-256), masked in
 * the audit trail and in lists; full values only through the audited reveal
 * endpoint. Only a keyed hash of the name is searchable.
 *
 * @property int $id
 * @property string $kes_mahkamah_id
 * @property string $nama_pihak
 * @property string|null $cincangan_nama
 * @property string $jenis_pihak
 * @property string $peranan
 * @property string|null $no_pengenalan
 * @property string|null $maklumat_perhubungan
 * @property string $status_wakilan
 * @property bool $adalah_mais
 * @property bool $sintetik
 */
class Pihak extends KesModel
{
    protected $table = 'kes_pihak';

    public const MEDAN_PERIBADI = ['nama_pihak', 'no_pengenalan', 'maklumat_perhubungan'];

    protected function casts(): array
    {
        return [
            'nama_pihak' => 'encrypted',
            'no_pengenalan' => 'encrypted',
            'maklumat_perhubungan' => 'encrypted',
            'adalah_mais' => 'boolean',
            'sintetik' => 'boolean',
        ];
    }

    public function auditSensitive(): array
    {
        return [...self::MEDAN_PERIBADI, 'cincangan_nama'];
    }

    public static function cincang(string $nama): string
    {
        return hash_hmac('sha256', 'kes_pihak:'.mb_strtolower(preg_replace('/\s+/', ' ', trim($nama)) ?? ''), (string) config('app.key'));
    }

    /** Individuals are masked; MAIS, agencies and companies are not personal data. */
    public function perluTopeng(): bool
    {
        return $this->jenis_pihak === 'individu' && ! $this->adalah_mais;
    }

    /** @return array<string, string|null> */
    public function bertopeng(): array
    {
        if (! $this->perluTopeng()) {
            return ['nama_pihak' => $this->nama_pihak, 'no_pengenalan' => null, 'maklumat_perhubungan' => $this->maklumat_perhubungan === null ? null : '••••••'];
        }
        $nama = (string) $this->nama_pihak;
        $words = preg_split('/\s+/', trim($nama)) ?: [];

        return [
            'nama_pihak' => implode(' ', array_map(fn (string $w) => mb_substr($w, 0, 1).str_repeat('•', max(2, mb_strlen($w) - 1)), $words)),
            'no_pengenalan' => $this->no_pengenalan === null ? null : str_repeat('•', max(4, strlen($this->no_pengenalan) - 4)).substr($this->no_pengenalan, -4),
            'maklumat_perhubungan' => $this->maklumat_perhubungan === null ? null : '••••••',
        ];
    }

    /** @return BelongsTo<KesMahkamah, $this> */
    public function kes(): BelongsTo
    {
        return $this->belongsTo(KesMahkamah::class, 'kes_mahkamah_id')->withoutGlobalScope('access');
    }
}
