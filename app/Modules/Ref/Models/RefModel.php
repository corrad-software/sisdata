<?php

namespace App\Modules\Ref\Models;

use App\Modules\Aud\Contracts\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A versioned reference code (SRS-DAT-05). Several rows may share a kod —
 * one per versi; the row valid on date D has sah_dari <= D < sah_hingga
 * (sah_hingga NULL = still valid). Write through RefVersioning, not directly.
 *
 * @property int $id
 * @property string $kod
 * @property string $nama
 * @property string|null $keterangan
 * @property int $susunan
 * @property bool $aktif
 * @property int $versi
 * @property CarbonInterface $sah_dari
 * @property CarbonInterface|null $sah_hingga
 * @property string|null $sumber
 * @property string|null $sumber_url
 */
abstract class RefModel extends Model implements Auditable
{
    protected $guarded = ['id'];

    /** Extra columns this list exposes through the API, beyond the common ones. */
    public const EXTRA = [];

    /** Columns that may be used as API filters: query param => column. */
    public const FILTERS = [];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'versi' => 'integer',
            'susunan' => 'integer',
            'sah_dari' => 'date',
            'sah_hingga' => 'date',
        ];
    }

    /**
     * Rows valid on $date (default today). Inactive codes are still "valid"
     * versions; add ->where('aktif', true) to hide them.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSahPada(Builder $query, ?CarbonInterface $date = null): void
    {
        $d = ($date ?? now())->toDateString();
        $query->whereDate('sah_dari', '<=', $d)
            ->where(fn (Builder $q) => $q->whereNull('sah_hingga')->orWhereDate('sah_hingga', '>', $d));
    }

    /**
     * The current version of each code (sah_hingga IS NULL).
     *
     * @param  Builder<static>  $query
     */
    public function scopeSemasa(Builder $query): void
    {
        $query->whereNull('sah_hingga');
    }

    /**
     * Attributes compared when deciding whether a seed/admin change is a new version.
     *
     * @return list<string>
     */
    public function versionedAttributes(): array
    {
        return ['nama', 'keterangan', 'susunan', 'aktif', 'sumber', 'sumber_url', ...static::EXTRA];
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        $out = ['kod' => $this->kod, 'nama' => $this->nama];
        foreach (static::EXTRA as $col) {
            $out[$col] = $this->getAttribute($col);
        }

        return $out + [
            'keterangan' => $this->keterangan,
            'aktif' => $this->aktif,
            'versi' => $this->versi,
            'sah_dari' => $this->sah_dari->toDateString(),
            'sah_hingga' => $this->sah_hingga?->toDateString(),
            'sumber' => $this->sumber,
        ];
    }

    public function auditModule(): string
    {
        return 'ref';
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
