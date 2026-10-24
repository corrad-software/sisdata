<?php

namespace App\Modules\Ast\Support;

use Illuminate\Support\Facades\DB;

/**
 * Read-only names from MOD-REF's versioned code tables (current rows) — MOD-REF
 * has no PHP contract, other modules read the tables the same way. Request-scoped.
 */
class Rujukan
{
    /** @var array<string, array<string, string>> */
    private array $cache = [];

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

    public function namaDaerah(?string $kod): ?string
    {
        return $kod === null ? null : ($this->daerah()[$kod] ?? $kod);
    }

    public function namaMukim(?string $kod): ?string
    {
        return $kod === null ? null : ($this->mukim()[$kod] ?? $kod);
    }

    /**
     * @param  array<string|int, string>  $map
     * @return list<array{kod: string, nama: string}>
     */
    public static function senarai(array $map): array
    {
        $out = [];
        foreach ($map as $kod => $nama) {
            $out[] = ['kod' => (string) $kod, 'nama' => (string) $nama];
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
