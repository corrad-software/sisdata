<?php

namespace App\Modules\Pro\Services;

use Illuminate\Support\Facades\DB;

/**
 * SRS-PRO-25 MDM: MOD-PRO never keeps its own copy of reference lists — it
 * reads MOD-REF's versioned tables (current row = sah_hingga IS NULL). Results
 * are memoised per instance (one request / one import run).
 */
class RujukanInduk
{
    /** @var array<string, array<string, object>> */
    private array $cache = [];

    public function mukim(string $kod): ?object
    {
        return $this->all('ref_mukim', ['kod', 'nama', 'daerah_kod'])[$kod] ?? null;
    }

    public function daerah(string $kod): ?object
    {
        return $this->all('ref_daerah', ['kod', 'nama', 'negeri_kod'])[$kod] ?? null;
    }

    public function kategoriGunaTanah(string $kod): ?object
    {
        return $this->all('ref_kategori_guna_tanah', ['kod', 'nama'])[$kod] ?? null;
    }

    public function exists(string $table, string $kod): bool
    {
        return match ($table) {
            'ref_mukim' => $this->mukim($kod) !== null,
            'ref_daerah' => $this->daerah($kod) !== null,
            'ref_kategori_guna_tanah' => $this->kategoriGunaTanah($kod) !== null,
            default => isset($this->all($table, ['kod', 'nama'])[$kod]),
        };
    }

    public function namaMukim(?string $kod): ?string
    {
        return $kod ? $this->mukim($kod)?->nama : null;
    }

    public function namaDaerah(?string $kod): ?string
    {
        return $kod ? $this->daerah($kod)?->nama : null;
    }

    public function namaKategori(?string $kod): ?string
    {
        return $kod ? $this->kategoriGunaTanah($kod)?->nama : null;
    }

    /**
     * Find a mukim code by its (normalised) name inside a daerah — used by imports
     * that carry names instead of codes.
     */
    public function mukimByName(string $name, ?string $daerahKod = null): ?string
    {
        $needle = $this->fold($name);
        foreach ($this->all('ref_mukim', ['kod', 'nama', 'daerah_kod']) as $row) {
            if (($daerahKod === null || $row->daerah_kod === $daerahKod) && $this->fold($row->nama) === $needle) {
                return $row->kod;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, object>
     */
    private function all(string $table, array $columns): array
    {
        if (! isset($this->cache[$table])) {
            $this->cache[$table] = DB::table($table)->whereNull('sah_hingga')->where('aktif', true)
                ->get($columns)->keyBy('kod')->all();
        }

        return $this->cache[$table];
    }

    private function fold(string $name): string
    {
        $s = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $name)));

        return (string) preg_replace('/^(mukim|bandar|pekan) /', '', $s);
    }
}
