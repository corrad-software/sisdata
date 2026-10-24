<?php

namespace App\Modules\Adu\Support;

use App\Modules\Adu\Models\SubkategoriAduan;
use Illuminate\Support\Facades\DB;

/**
 * Read-only access to MOD-REF's versioned code tables (ref_kategori_aduan,
 * ref_daerah, ref_mukim, ref_pbt, ref_sempadan) — MOD-REF exposes no PHP
 * contract, other modules read the current rows the same way. Request-scoped.
 */
class Rujukan
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /** @return array<string, string> kod => nama */
    public function kategori(): array
    {
        return $this->cache['kategori'] ??= $this->names('ref_kategori_aduan');
    }

    /** @return array<string, string> */
    public function daerah(): array
    {
        return $this->cache['daerah'] ??= $this->names('ref_daerah');
    }

    /** @return array<string, string> */
    public function mukim(): array
    {
        return $this->cache['mukim'] ??= $this->names('ref_mukim');
    }

    /** @return array<string, array<string, string>> kod_kategori => [kod => nama] */
    public function subkategori(bool $activeOnly = true): array
    {
        return $this->cache['sub'.(int) $activeOnly] ??= SubkategoriAduan::query()
            ->when($activeOnly, fn ($q) => $q->where('aktif', true))
            ->orderBy('kod_kategori')->orderBy('susunan')->get()
            ->groupBy('kod_kategori')
            ->map(fn ($rows) => $rows->pluck('nama', 'kod')->all())
            ->all();
    }

    public function namaKategori(?string $kod): ?string
    {
        return $kod === null ? null : ($this->kategori()[$kod] ?? $kod);
    }

    public function namaSubkategori(?string $kategori, ?string $kod): ?string
    {
        if ($kod === null) {
            return null;
        }

        return $this->subkategori(false)[$kategori][$kod] ?? $kod;
    }

    public function namaDaerah(?string $kod): ?string
    {
        return $kod === null ? null : ($this->daerah()[$kod] ?? $kod);
    }

    public function namaMukim(?string $kod): ?string
    {
        return $kod === null ? null : ($this->mukim()[$kod] ?? $kod);
    }

    public function kategoriSah(string $kod): bool
    {
        return array_key_exists($kod, $this->kategori());
    }

    /**
     * daerah / mukim codes whose boundary contains the point (MOD-REF
     * ref_sempadan; mukim shapes are approximations).
     *
     * @return array{kod_daerah: string|null, kod_mukim: string|null}
     */
    public function kawasanTitik(float $lat, float $lng): array
    {
        $out = ['kod_daerah' => null, 'kod_mukim' => null];
        $rows = DB::select(
            "SELECT jenis, kod FROM ref_sempadan WHERE jenis IN ('daerah', 'mukim') AND ST_Contains(geometri, ST_SetSRID(ST_MakePoint(?, ?), 4326))",
            [$lng, $lat],
        );
        foreach ($rows as $row) {
            $out['kod_'.$row->jenis] ??= (string) $row->kod;
        }
        if ($out['kod_daerah'] === null && $out['kod_mukim'] !== null) {
            $out['kod_daerah'] = substr($out['kod_mukim'], 0, 4);
        }

        return $out;
    }

    /**
     * SRS-ADU-10: agencies to suggest for a location that is not MAIS land —
     * the local authority (ref_pbt covering the district) and the district
     * land office.
     *
     * @return list<array{nama: string, jenis: string}>
     */
    public function agensiCadangan(?string $kodDaerah): array
    {
        $out = [];
        if ($kodDaerah !== null) {
            foreach (DB::table('ref_pbt')->whereNull('sah_hingga')->where('aktif', true)->get(['nama', 'daerah_kod']) as $pbt) {
                $codes = json_decode((string) $pbt->daerah_kod, true) ?: [];
                if (in_array($kodDaerah, array_map('strval', (array) $codes), true)) {
                    $out[] = ['nama' => (string) $pbt->nama, 'jenis' => 'pbt'];
                }
            }
            $out[] = ['nama' => 'Pejabat Daerah dan Tanah '.($this->namaDaerah($kodDaerah) ?? $kodDaerah), 'jenis' => 'pejabat_tanah'];
        } else {
            $out[] = ['nama' => 'Pejabat Tanah dan Galian Selangor (PTGS)', 'jenis' => 'pejabat_tanah'];
        }

        return $out;
    }

    /** @return array<string, string> */
    private function names(string $table): array
    {
        return DB::table($table)->whereNull('sah_hingga')->orderBy('susunan')->orderBy('kod')->pluck('nama', 'kod')
            ->mapWithKeys(fn ($nama, $kod) => [(string) $kod => (string) $nama])->all();
    }
}
