<?php

namespace App\Modules\Dms\Models;

use App\Modules\Aud\Contracts\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * SRS-DMS-11: admin-defined extra metadata field (null kategori_id = all categories).
 *
 * @property int $id
 * @property int|null $kategori_id
 * @property string $kod
 * @property string $label
 * @property string $jenis
 * @property list<string>|null $pilihan
 * @property bool $wajib
 * @property bool $aktif
 * @property int $susunan
 */
class DokumenMedanMetadata extends Model implements Auditable
{
    public const JENIS = ['teks', 'nombor', 'tarikh', 'senarai', 'ya_tidak'];

    protected $table = 'dokumen_medan_metadata';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pilihan' => 'array',
            'wajib' => 'boolean',
            'aktif' => 'boolean',
            'susunan' => 'integer',
        ];
    }

    /**
     * Active fields that apply to a category (its own + the global ones).
     *
     * @return Collection<int, static>
     */
    public static function forKategori(int $kategoriId): Collection
    {
        return static::query()
            ->where('aktif', true)
            ->where(fn (Builder $q) => $q->whereNull('kategori_id')->orWhere('kategori_id', $kategoriId))
            ->orderBy('susunan')
            ->orderBy('id')
            ->get();
    }

    public function auditModule(): string
    {
        return 'dms';
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
