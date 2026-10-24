<?php

namespace App\Modules\Rbc\Support;

use Illuminate\Support\Str;

/**
 * The seven base role categories of RFP 11.2.3 (SRS-RBC-05, acceptance #9),
 * seeded as ordinary dynamic roles. `include`/`exclude` are wildcard
 * patterns over permission keys, applied ONCE to each permission the first
 * time it is registered — after that the role belongs to the administrators
 * (removing a permission is not undone by the next sync).
 */
final class BaseRoles
{
    public const PENTADBIR_SISTEM = 'pentadbir_sistem';

    public const PENTADBIR_APLIKASI = 'pentadbir_aplikasi';

    public const PEGAWAI_OPERASI = 'pegawai_operasi';

    public const PELULUS = 'pelulus';

    public const PENGGUNA_PAPARAN = 'pengguna_paparan';

    public const JURUAUDIT = 'juruaudit';

    public const PEMEGANG_TARUH_TERHAD = 'pemegang_taruh_terhad';

    /** Modules whose functions are administrative / oversight, never operational. */
    private const PLATFORM_ADMIN = ['rbc.*', 'aud.*', 'adm.*'];

    /**
     * Access to Sulit/Rahsia material is never granted by a wildcard: it must
     * be given explicitly per role or user (SRS-DMS-19, SRS §4.2).
     */
    private const CLASSIFIED = ['*.sulit.*', '*.rahsia.*'];

    /**
     * @return array<string, array{nama: string, keterangan: string, include: list<string>, exclude: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            self::PENTADBIR_SISTEM => [
                'nama' => 'Pentadbir Sistem',
                'keterangan' => 'Capaian pentadbiran penuh kepada konfigurasi sistem, pengurusan pengguna, tetapan keselamatan, parameter dan fungsi teknikal.',
                'include' => ['*'],
                'exclude' => [],
            ],
            self::PENTADBIR_APLIKASI => [
                'nama' => 'Pentadbir Aplikasi',
                'keterangan' => 'Mengurus pengguna, peranan, parameter aplikasi, kod rujukan, konfigurasi modul dan laporan pentadbiran mengikut skop diluluskan.',
                'include' => [
                    'rbc.pengguna.*', 'rbc.peranan.*', 'rbc.pemetaan.*', 'rbc.laporan.*', 'rbc.matriks.*',
                    'rbc.recert.*', 'rbc.sesi.lihat', 'rbc.log_masuk.lihat', 'rbc.polisi.lihat', 'rbc.pentadbiran.akses',
                    'ref.*',
                    'adm.*',
                    // Must hold what it hands out: lets it assign Pengguna Paparan etc.
                    '*.lihat',
                ],
                'exclude' => self::CLASSIFIED,
            ],
            self::PEGAWAI_OPERASI => [
                'nama' => 'Pegawai Operasi',
                'keterangan' => 'Kemasukan data, kemas kini rekod, pengurusan dokumen, semakan maklumat dan tindakan harian mengikut bidang tugas.',
                'include' => ['*.lihat', '*.cipta', '*.kemas_kini'],
                'exclude' => [...self::PLATFORM_ADMIN, ...self::CLASSIFIED],
            ],
            self::PELULUS => [
                'nama' => 'Pelulus',
                'keterangan' => 'Menyemak, mengesahkan dan meluluskan transaksi, permohonan dan perubahan status mengikut kuasa yang ditetapkan.',
                'include' => ['*.lihat', '*.lulus'],
                'exclude' => [...self::PLATFORM_ADMIN, ...self::CLASSIFIED],
            ],
            self::PENGGUNA_PAPARAN => [
                'nama' => 'Pengguna Paparan',
                'keterangan' => 'Melihat rekod, laporan dan dashboard tanpa kebenaran mengubah data.',
                'include' => ['*.lihat'],
                'exclude' => [...self::PLATFORM_ADMIN, ...self::CLASSIFIED],
            ],
            self::JURUAUDIT => [
                'nama' => 'Juruaudit',
                'keterangan' => 'Mencapai rekod audit, log sistem, sejarah perubahan data dan maklumat pematuhan bagi tujuan semakan.',
                'include' => [
                    'aud.*.lihat', 'aud.*.eksport', 'rbc.log_masuk.*', 'rbc.amaran.lihat', 'rbc.laporan.*', 'rbc.matriks.*',
                    'rbc.peranan.lihat', 'rbc.pengguna.lihat', 'rbc.sesi.lihat', 'rbc.recert.lihat',
                ],
                'exclude' => [],
            ],
            self::PEMEGANG_TARUH_TERHAD => [
                'nama' => 'Pemegang Taruh Terhad',
                'keterangan' => 'Mencapai portal atau maklumat tertentu yang dibenarkan — status permohonan, dokumen, bayaran atau komunikasi berkaitan.',
                'include' => ['pws.*.lihat'],
                'exclude' => [],
            ],
        ];
    }

    public static function grantsByDefault(string $role, string $permission): bool
    {
        $definition = self::definitions()[$role] ?? null;

        return $definition !== null
            && Str::is($definition['include'], $permission)
            && ! Str::is($definition['exclude'], $permission);
    }
}
