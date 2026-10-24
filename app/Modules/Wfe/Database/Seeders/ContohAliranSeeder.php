<?php

namespace App\Modules\Wfe\Database\Seeders;

use App\Models\User;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo flow for an end-to-end walkthrough:
 *  - `contoh.kelulusan` v1 (active): Semakan Pegawai → Kelulusan Pengurusan
 *    (critical stage, MFA step-up), 2-level escalation on each stage and a
 *    follow-up task for the applicant on approval;
 *  - template `templat.kelulusan_ambang`: same plus an extra stage when
 *    nilai > 1,000,000 (branch condition) — copy it from the template library.
 *
 * Approvers are WFE groups whose members are the Keycloak realm "mais" test
 * users (created here if they have never signed in). Idempotent.
 */
class ContohAliranSeeder extends Seeder
{
    public function run(DefinitionService $definitions): void
    {
        $users = [];
        foreach ([
            'siti.aminah@mais.test' => 'Siti Aminah binti Abdullah',
            'mohd.faizal@mais.test' => 'Mohd Faizal bin Hassan',
            'nurul.huda@mais.test' => 'Nurul Huda binti Ismail',
        ] as $email => $name) {
            $users[$email] = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => Str::random(64), 'role' => 'user', 'is_active' => true,
            ]);
        }

        $reviewers = Kumpulan::query()->firstOrCreate(['kod' => 'contoh_penyemak'], ['nama' => 'Contoh — Pegawai Penyemak']);
        $reviewers->ahli()->syncWithoutDetaching([$users['siti.aminah@mais.test']->id]);
        $approvers = Kumpulan::query()->firstOrCreate(['kod' => 'contoh_pelulus'], ['nama' => 'Contoh — Pengurusan (Pelulus)']);
        $approvers->ahli()->syncWithoutDetaching([$users['mohd.faizal@mais.test']->id, $users['nurul.huda@mais.test']->id]);

        $author = User::query()->where('role', 'admin')->orderBy('id')->first() ?? $users['nurul.huda@mais.test'];

        if (! Definisi::query()->where('kod_aliran', 'contoh.kelulusan')->exists()) {
            $definition = $definitions->create(self::structure('contoh.kelulusan', 'Contoh kelulusan dua peringkat', false), $author, 'api', 'Seeder demo');
            $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
            $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh seeder demo');
        }

        if (! Definisi::query()->where('kod_aliran', 'templat.kelulusan_ambang')->exists()) {
            $definitions->create(self::structure('templat.kelulusan_ambang', 'Templat: kelulusan dengan ambang nilai', true), $author, 'api', 'Seeder demo (templat)');
        }
    }

    /** @return array<string, mixed> */
    public static function structure(string $code, string $name, bool $withThreshold): array
    {
        $stages = [
            [
                'kod' => 'semakan', 'nama' => 'Semakan Pegawai', 'mod_kelulusan' => 'mana_mana',
                'sla_nilai' => 2, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => false, 'perlu_justifikasi' => false,
                'pelulus' => [['jenis' => 'kumpulan', 'nilai' => 'contoh_penyemak', 'label' => 'Pegawai Penyemak']],
                'eskalasi' => [
                    ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null],
                    ['aras' => 2, 'tempoh_nilai' => 1, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'peranan', 'nilai_penerima' => 'admin'],
                ],
            ],
            [
                'kod' => 'kelulusan', 'nama' => 'Kelulusan Pengurusan', 'mod_kelulusan' => 'mana_mana',
                'sla_nilai' => 8, 'sla_unit' => 'jam', 'perlu_mfa' => true, 'perlu_justifikasi' => false,
                'pelulus' => [['jenis' => 'kumpulan', 'nilai' => 'contoh_pelulus', 'label' => 'Pengurusan']],
                'eskalasi' => [
                    ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'jam', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null],
                    ['aras' => 2, 'tempoh_nilai' => 4, 'tempoh_unit' => 'jam', 'jenis_penerima' => 'peranan', 'nilai_penerima' => 'admin'],
                ],
            ],
        ];
        $edges = [
            ['dari' => 'mula', 'ke' => 'semakan'],
            ['dari' => 'semakan', 'ke' => 'kelulusan'],
            ['dari' => 'kelulusan', 'ke' => 'tamat'],
        ];
        $layout = ['nodes' => [
            'mula' => ['x' => 0, 'y' => 120], 'semakan' => ['x' => 220, 'y' => 100],
            'kelulusan' => ['x' => 720, 'y' => 100], 'tamat' => ['x' => 980, 'y' => 120],
        ]];

        if ($withThreshold) {
            array_splice($stages, 1, 0, [[
                'kod' => 'kewangan', 'nama' => 'Semakan Kewangan (nilai > RM1 juta)', 'mod_kelulusan' => 'mana_mana',
                'sla_nilai' => 3, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => false, 'perlu_justifikasi' => true,
                'pelulus' => [['jenis' => 'peranan', 'nilai' => 'pegawai_kewangan', 'label' => 'Pegawai Kewangan']],
                'eskalasi' => [
                    ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null],
                    ['aras' => 2, 'tempoh_nilai' => 2, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'peranan', 'nilai_penerima' => 'admin'],
                ],
            ]]);
            $edges = [
                ['dari' => 'mula', 'ke' => 'semakan'],
                ['dari' => 'semakan', 'ke' => 'kewangan', 'keutamaan' => 1, 'label' => 'Nilai > RM1 juta', 'syarat' => [['medan' => 'nilai', 'operator' => 'lebih', 'nilai' => 1000000]]],
                ['dari' => 'semakan', 'ke' => 'kelulusan', 'keutamaan' => 2],
                ['dari' => 'kewangan', 'ke' => 'kelulusan'],
                ['dari' => 'kelulusan', 'ke' => 'tamat'],
            ];
            $layout['nodes']['kewangan'] = ['x' => 470, 'y' => 260];
        }

        return [
            'kod_aliran' => $code,
            'nama' => $name,
            'keterangan' => 'Aliran demonstrasi MOD-WFE.',
            'modul' => 'wfe',
            'jenis_transaksi' => 'contoh_permohonan',
            'templat' => $withThreshold,
            'peringkat' => $stages,
            'peralihan' => $edges,
            'susulan' => [[
                'bila' => 'lulus', 'tajuk' => 'Kemas kini rekod dan maklumkan keputusan', 'jenis_penerima' => 'pemohon',
                'nilai_penerima' => null, 'sla_nilai' => 3, 'sla_unit' => 'hari_bekerja',
            ]],
            'susun_atur' => $layout,
        ];
    }
}
