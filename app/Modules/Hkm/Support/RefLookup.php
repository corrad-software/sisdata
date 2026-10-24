<?php

namespace App\Modules\Hkm\Support;

use Illuminate\Support\Facades\DB;

/**
 * Read-only access to MOD-REF's versioned code tables (ref_*). MOD-REF exposes
 * no PHP contract yet (only /api/v1/ref/*), so the few lookups HKM needs are
 * isolated here — current rows only (sah_hingga IS NULL), cached per request.
 */
class RefLookup
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $cache = [];

    /** @return array<string, array{kod: string, nama: string, singkatan: string, sementara: bool, hak_milik: bool, aktif: bool}> */
    public function jenisHakMilik(): array
    {
        return $this->cache['jhm'] ??= DB::table('ref_jenis_hak_milik')->whereNull('sah_hingga')->orderBy('susunan')->orderBy('kod')
            ->get(['kod', 'nama', 'singkatan', 'sementara', 'hak_milik', 'aktif'])
            ->mapWithKeys(fn ($r) => [$r->kod => ['kod' => $r->kod, 'nama' => $r->nama, 'singkatan' => $r->singkatan, 'sementara' => (bool) $r->sementara, 'hak_milik' => (bool) $r->hak_milik, 'aktif' => (bool) $r->aktif]])
            ->all();
    }

    /** @return array<string, array{kod: string, nama: string, aktif: bool}> */
    public function kategoriGunaTanah(): array
    {
        return $this->cache['kgt'] ??= DB::table('ref_kategori_guna_tanah')->whereNull('sah_hingga')->orderBy('susunan')->orderBy('kod')
            ->get(['kod', 'nama', 'aktif'])
            ->mapWithKeys(fn ($r) => [$r->kod => ['kod' => $r->kod, 'nama' => $r->nama, 'aktif' => (bool) $r->aktif]])
            ->all();
    }

    /** @return array<string, array{kod: string, nama: string, daerah_kod: string}> */
    public function mukim(): array
    {
        return $this->cache['mukim'] ??= DB::table('ref_mukim')->whereNull('sah_hingga')
            ->get(['kod', 'nama', 'daerah_kod'])
            ->mapWithKeys(fn ($r) => [$r->kod => ['kod' => $r->kod, 'nama' => $r->nama, 'daerah_kod' => $r->daerah_kod]])
            ->all();
    }

    /** @return array<string, array{kod: string, nama: string}> */
    public function daerah(): array
    {
        return $this->cache['daerah'] ??= DB::table('ref_daerah')->whereNull('sah_hingga')
            ->get(['kod', 'nama'])
            ->mapWithKeys(fn ($r) => [$r->kod => ['kod' => $r->kod, 'nama' => $r->nama]])
            ->all();
    }

    public function validJenis(string $kod): bool
    {
        return ($this->jenisHakMilik()[$kod]['aktif'] ?? false) === true;
    }

    public function validKategori(string $kod): bool
    {
        return ($this->kategoriGunaTanah()[$kod]['aktif'] ?? false) === true;
    }

    /** Temporary title (HS(D)/HS(M)/TOL…) per ref_jenis_hak_milik.sementara. */
    public function sementara(string $jenis): bool
    {
        return $this->jenisHakMilik()[$jenis]['sementara'] ?? false;
    }

    public function namaJenis(?string $kod): ?string
    {
        return $kod === null ? null : ($this->jenisHakMilik()[$kod]['nama'] ?? $kod);
    }

    public function namaKategori(?string $kod): ?string
    {
        return $kod === null ? null : ($this->kategoriGunaTanah()[$kod]['nama'] ?? $kod);
    }

    public function namaMukim(?string $kod): ?string
    {
        return $kod === null ? null : ($this->mukim()[$kod]['nama'] ?? $kod);
    }

    public function namaDaerah(?string $kod): ?string
    {
        return $kod === null ? null : ($this->daerah()[$kod]['nama'] ?? $kod);
    }
}
