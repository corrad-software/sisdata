<?php

namespace App\Modules\Kes\Services;

use App\Modules\Kes\Models\JenisKes;
use App\Modules\Kes\Models\StatusKes;
use Illuminate\Support\Facades\DB;

/**
 * Per-request cache of the configurable code lists (case types SRS-KES-01,
 * status stages SRS-KES-07) and district/mukim names. Bound as scoped.
 */
class Rujukan
{
    /** @var array<string, array{nama: string, aktif: bool}>|null */
    private ?array $jenis = null;

    /** @var array<string, array{nama: string, terminal: bool, aktif: bool, susunan: int}>|null */
    private ?array $status = null;

    /** @var array<string, string>|null */
    private ?array $daerah = null;

    /** @var array<string, string>|null */
    private ?array $mukim = null;

    /** @return array<string, array{nama: string, aktif: bool}> */
    public function jenis(): array
    {
        return $this->jenis ??= JenisKes::query()->orderBy('susunan')->orderBy('nama')->get()
            ->mapWithKeys(fn (JenisKes $j) => [$j->kod => ['nama' => $j->nama, 'aktif' => $j->aktif]])->all();
    }

    /** @return array<string, array{nama: string, terminal: bool, aktif: bool, susunan: int}> */
    public function status(): array
    {
        return $this->status ??= StatusKes::query()->orderBy('susunan')->get()
            ->mapWithKeys(fn (StatusKes $s) => [$s->kod => ['nama' => $s->nama, 'terminal' => $s->terminal, 'aktif' => $s->aktif, 'susunan' => $s->susunan]])->all();
    }

    /** @return list<string> */
    public function statusTerminal(): array
    {
        $codes = array_keys(array_filter($this->status(), fn ($s) => $s['terminal']));

        return $codes === [] ? ['selesai'] : $codes;
    }

    public function namaJenis(?string $kod): ?string
    {
        return $kod === null ? null : ($this->jenis()[$kod]['nama'] ?? $kod);
    }

    public function namaStatus(?string $kod): ?string
    {
        return $kod === null ? null : ($this->status()[$kod]['nama'] ?? $kod);
    }

    public function namaDaerah(?string $kod): ?string
    {
        $this->daerah ??= DB::table('ref_daerah')->pluck('nama', 'kod')->map(fn ($v) => (string) $v)->all();

        return $kod === null ? null : ($this->daerah[$kod] ?? $kod);
    }

    public function namaMukim(?string $kod): ?string
    {
        $this->mukim ??= DB::table('ref_mukim')->pluck('nama', 'kod')->map(fn ($v) => (string) $v)->all();

        return $kod === null ? null : ($this->mukim[$kod] ?? $kod);
    }

    public function lupa(): void
    {
        $this->jenis = $this->status = null;
    }
}
