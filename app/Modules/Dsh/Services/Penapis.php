<?php

namespace App\Modules\Dsh\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The dashboard's combined filters (SRS-DSH-08): tahun, daerah, mukim,
 * bahagian, kategori tanah, status, pegawai bertanggungjawab and modul. The
 * same normalised array is passed to every KpiProvider (keys the provider
 * doesn't know are ignored by it) so all widgets stay filtered alike.
 */
final class Penapis
{
    public const KEYS = ['tahun', 'kod_daerah', 'kod_mukim', 'bahagian', 'kategori', 'status', 'pegawai', 'modul'];

    /**
     * @param  array<string, mixed>  $input
     * @return array{tahun?: int, kod_daerah?: string, kod_mukim?: string, bahagian?: string, kategori?: string, status?: string, pegawai?: int, modul?: string}
     */
    public static function from(array $input): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $value = $input[$key] ?? null;
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $value = trim((string) $value);
            $ok = match ($key) {
                'tahun' => preg_match('/^(19|20)\d{2}$/', $value) === 1,
                'kod_daerah' => preg_match('/^\d{4}$/', $value) === 1,
                'kod_mukim' => preg_match('/^\d{6}$/', $value) === 1,
                'pegawai' => ctype_digit($value),
                default => preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $value) === 1,
            };
            if (! $ok) {
                throw ValidationException::withMessages([$key => __('dsh::dsh.penapis_tidak_sah')]);
            }
            $out[$key] = in_array($key, ['tahun', 'pegawai'], true) ? (int) $value : $value;
        }
        if (isset($out['kod_mukim']) && ! isset($out['kod_daerah'])) {
            $out['kod_daerah'] = substr($out['kod_mukim'], 0, 4);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function fromRequest(Request $request): array
    {
        return self::from($request->only(self::KEYS));
    }

    public static function year(array $filters): int
    {
        return (int) ($filters['tahun'] ?? Carbon::today()->year);
    }

    /** Stable hash of a filter set (cache key). */
    public static function hash(array $filters): string
    {
        ksort($filters);

        return substr(sha1((string) json_encode($filters)), 0, 12);
    }
}
