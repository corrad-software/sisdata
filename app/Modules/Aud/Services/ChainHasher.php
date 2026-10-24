<?php

namespace App\Modules\Aud\Services;

use Illuminate\Support\Carbon;

/**
 * SRS-AUD-06: SHA-256 over a canonical serialisation of a record and its
 * field changes, including the previous record's hash — so altering, removing
 * or reordering any record breaks every hash after it.
 *
 * The canonical form is rebuilt from what the database returns (strings,
 * ints, bools, json text), so the integrity check can recompute it later.
 */
class ChainHasher
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /** Fields of audit_rekod covered by the hash, in canonical order. */
    public const FIELDS = [
        'no_urutan', 'cincangan_sebelum', 'tarikh_masa_utc', 'tarikh_masa_tempatan', 'id_pengguna',
        'pengecam_pengguna', 'peranan_semasa', 'id_sesi', 'alamat_ip', 'user_agent', 'jenis_peranti',
        'lokasi_anggaran', 'saluran_capaian', 'modul', 'nama_entiti', 'id_rekod', 'jenis_tindakan',
        'status_transaksi', 'sebab_perubahan', 'id_korelasi', 'klasifikasi', 'meta',
    ];

    /**
     * @param  array<string, mixed>|object  $record
     * @param  iterable<array<string, mixed>|object>  $changes  rows with nama_medan, nilai_asal, nilai_baharu, sensitif_ditapis
     */
    public static function hash(array|object $record, iterable $changes): string
    {
        return hash('sha256', self::canonical($record, $changes));
    }

    /**
     * @param  array<string, mixed>|object  $record
     * @param  iterable<array<string, mixed>|object>  $changes
     */
    public static function canonical(array|object $record, iterable $changes): string
    {
        $record = (array) $record;
        $out = [];

        foreach (self::FIELDS as $field) {
            $value = $record[$field] ?? null;

            $out[$field] = match ($field) {
                'tarikh_masa_utc' => Carbon::parse((string) $value)->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'tarikh_masa_tempatan' => Carbon::parse((string) $value, 'UTC')->format('Y-m-d\TH:i:s.u'),
                'peranan_semasa', 'meta' => $value === null ? null : self::sortKeys(json_decode((string) $value, true)),
                default => $value === null ? null : (string) $value,
            };
        }

        $out['perubahan'] = [];
        foreach ($changes as $change) {
            $change = (array) $change;
            $out['perubahan'][] = [
                (string) $change['nama_medan'],
                $change['nilai_asal'] === null ? null : (string) $change['nilai_asal'],
                $change['nilai_baharu'] === null ? null : (string) $change['nilai_baharu'],
                (bool) $change['sensitif_ditapis'],
            ];
        }

        return (string) json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::sortKeys(...), $value);
    }
}
