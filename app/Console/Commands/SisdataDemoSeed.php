<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Adm\Services\ParameterService;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Services\RoleService;
use App\Modules\Rbc\Support\BaseRoles;
use App\Modules\Wfe\Database\Seeders\WfeSeeder;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Database\Seeders\AduAliranSeeder;
use Database\Seeders\AstAliranSeeder;
use Database\Seeders\CkiAliranSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\G1AliranSeeder;
use Database\Seeders\HkmAliranSeeder;
use Database\Seeders\KesAliranSeeder;
use Database\Seeders\PjkAliranSeeder;
use Database\Seeders\PtnAliranSeeder;
use Database\Seeders\PwsSeeder;
use Database\Seeders\TktAliranSeeder;
use Database\Seeders\TsuAliranSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * php artisan sisdata:demo-seed [--force] [--tanpa-kba] [--pintas-mfa-uat]
 *
 * Fills an empty, migrated database with the full SISDATA demo: base roles and
 * accounts (DatabaseSeeder → RbcSeeder), the Keycloak realm "mais" demo users
 * with roles/scopes matching their SSO claims, reference data, 500 synthetic
 * lots + titles, every module's sample data and every approval flow (with
 * members in every approver group so each flow can be demonstrated).
 *
 * Lives in app/Console (not a module) because it orchestrates module
 * internals the same way database/seeders/*AliranSeeder do.
 */
class SisdataDemoSeed extends Command
{
    protected $signature = 'sisdata:demo-seed
        {--force : Run even though lot_tanah already has rows (steps are idempotent or additive)}
        {--tanpa-kba : Skip kba:index-baseline (needs the local Ollama embedding model)}
        {--pintas-mfa-uat : APP_ENV=uat only: switch on the UAT MFA bypass parameters (wfe.mfa_pintas_uat, kes.mfa_wajib=false)}
        {--hanya-akses : On an existing DB: only (re)create demo users, their roles/scopes, the extra flows and approver-group members — no module data}';

    protected $description = 'Isi pangkalan data kosong dengan data demo SISDATA (pengguna, peranan, data rujukan, data sampel setiap modul, aliran kelulusan)';

    /** Demo password for the Keycloak demo users' local accounts (non-production only; same as in infra/keycloak/realm-mais.json). */
    public const KATA_LALUAN_DEMO = 'Sisdata@123';

    /** e-mail => [base roles, district scope codes] — mirrors the SSO claim mapping in RbcSeeder. */
    public const PENGGUNA_DEMO = [
        'siti.aminah@mais.test' => [[BaseRoles::PEGAWAI_OPERASI, BaseRoles::PELULUS], ['1008']],
        'mohd.faizal@mais.test' => [[BaseRoles::PEGAWAI_OPERASI, BaseRoles::PELULUS, self::PERANAN_PERUNDANGAN], []],
        'nurul.huda@mais.test' => [[BaseRoles::PENGGUNA_PAPARAN, BaseRoles::PELULUS], []],
        // One account per RFP base role (SRS-RBC-05) so every access level can be tried.
        'pentadbir.sistem@mais.test' => [[BaseRoles::PENTADBIR_SISTEM], []],
        'pentadbir.aplikasi@mais.test' => [[BaseRoles::PENTADBIR_APLIKASI], []],
        'pegawai.operasi@mais.test' => [[BaseRoles::PEGAWAI_OPERASI], []],
        'pegawai.klang@mais.test' => [[BaseRoles::PEGAWAI_OPERASI], ['1001']],
        'pelulus@mais.test' => [[BaseRoles::PELULUS], []],
        'paparan@mais.test' => [[BaseRoles::PENGGUNA_PAPARAN], []],
        'juruaudit@mais.test' => [[BaseRoles::JURUAUDIT], []],
        'pihak.luar@mais.test' => [[BaseRoles::PEMEGANG_TARUH_TERHAD], []],
        'perundangan@mais.test' => [[BaseRoles::PEGAWAI_OPERASI, self::PERANAN_PERUNDANGAN], []],
    ];

    /** Display names for demo accounts that only exist in SISDATA (the first three come from the Keycloak realm). */
    private const NAMA_DEMO = [
        'pentadbir.sistem@mais.test' => 'Demo Pentadbir Sistem',
        'pentadbir.aplikasi@mais.test' => 'Demo Pentadbir Aplikasi',
        'pegawai.operasi@mais.test' => 'Demo Pegawai Operasi (semua daerah)',
        'pegawai.klang@mais.test' => 'Demo Pegawai Operasi Daerah Klang',
        'pelulus@mais.test' => 'Demo Pelulus',
        'paparan@mais.test' => 'Demo Pengguna Paparan',
        'juruaudit@mais.test' => 'Demo Juruaudit',
        'pihak.luar@mais.test' => 'Demo Pemegang Taruh Terhad',
        'perundangan@mais.test' => 'Demo Pegawai Perundangan',
    ];

    /** Demo role holding the explicitly-granted classified KES keys (never given by wildcard). */
    public const PERANAN_PERUNDANGAN = 'demo_pegawai_perundangan';

    /** Approver groups the module seeders create empty => demo members. Any other empty group gets mohd.faizal. */
    public const AHLI_KUMPULAN = [
        'adu_penyelia' => ['mohd.faizal@mais.test'],
        'adu_ketua_bahagian' => ['nurul.huda@mais.test'],
        'ast_pelulus_perolehan' => ['mohd.faizal@mais.test'],
        'ast_jawatankuasa_pelupusan' => ['mohd.faizal@mais.test', 'nurul.huda@mais.test'],
        'ast_pelulus_penyelenggaraan' => ['mohd.faizal@mais.test'],
        'kes_ketua_perundangan' => ['mohd.faizal@mais.test'],
        'kes_pengurusan_tertinggi' => ['nurul.huda@mais.test'],
        'kes_pegawai_perundangan' => ['mohd.faizal@mais.test'],
        'ptn_pengesah_laporan' => ['mohd.faizal@mais.test'],
        'ptn_pegawai_berkuasa' => ['nurul.huda@mais.test'],
        'ptn_penyelia_tindakan' => ['mohd.faizal@mais.test'],
        'tkt_pengurusan_sokongan' => ['nurul.huda@mais.test'],
        'tkt_jawatankuasa_projek' => ['nurul.huda@mais.test'],
        'pws_penyemak_kandungan' => ['mohd.faizal@mais.test'],
        'dms_pelulus_pelupusan' => ['mohd.faizal@mais.test'],
        'kba_penyemak_artikel' => ['mohd.faizal@mais.test'],
    ];

    public function handle(RoleService $roles, DefinitionService $definitions): int
    {
        if (app()->environment('production')) {
            $this->components->error('Tidak dibenarkan dalam persekitaran produksi.');

            return self::FAILURE;
        }
        if ($this->option('pintas-mfa-uat') && ! app()->environment('uat')) {
            $this->components->error('--pintas-mfa-uat hanya dibenarkan apabila APP_ENV=uat.');

            return self::FAILURE;
        }
        if ($this->option('hanya-akses')) {
            $failed = [];
            $this->seed(WfeSeeder::class, $failed);
            $this->demoUsers($roles);
            $this->extraFlows($definitions);
            $this->fillApproverGroups();
            $this->components->info('Pengguna demo, peranan dan ahli kumpulan pelulus dikemas kini.');

            return $failed === [] ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && DB::table('lot_tanah')->exists()) {
            $this->components->error('Pangkalan data ini sudah ada lot (lot_tanah). Gunakan --force untuk teruskan juga.');

            return self::FAILURE;
        }

        ini_set('memory_limit', '1G');
        $this->components->info('Pangkalan data: '.DB::connection()->getDatabaseName().' (APP_ENV='.app()->environment().')');
        $failed = [];

        // 1. Platform: base roles, admin, reference data, demo users and the WFE demo flow.
        $this->seed(DatabaseSeeder::class, $failed);
        $this->run2('sisdata:bucket', [], $failed); // DMS bucket (MinIO locally) — uploads fail without it
        $this->run2('ref:seed', [], $failed);
        $this->run2('pro:seed-tadbir', [], $failed);
        $this->seed(WfeSeeder::class, $failed); // creates the Keycloak demo users + contoh.kelulusan
        $this->demoUsers($roles);

        // 2. G1 core: synthetic lots/titles → master register → titles → acquisitions.
        $this->run2('ref:sample-lots', [], $failed);
        $this->run2('pro:import-staging', [], $failed);
        $this->run2('hkm:import-staging', [], $failed);
        $this->seed(G1AliranSeeder::class, $failed);
        $this->seed(HkmAliranSeeder::class, $failed);
        $this->run2('per:sample-cases', ['--seed' => 60], $failed);

        // 3. Operational + legal + asset modules: flows first, then samples.
        foreach ([
            [AduAliranSeeder::class, 'adu:sample', ['--seed' => 8]],
            [PjkAliranSeeder::class, 'pjk:sample', []],
            [CkiAliranSeeder::class, 'cki:sample', []],
            [PtnAliranSeeder::class, 'ptn:sample', ['--seed' => 7]],
            [KesAliranSeeder::class, 'kes:sample', ['--seed' => 9]],
            [TsuAliranSeeder::class, 'tsu:sample', ['--seed' => 10]],
            [AstAliranSeeder::class, 'ast:sample', ['--seed' => 11]],
            [TktAliranSeeder::class, 'tkt:sample', []],
        ] as [$seeder, $command, $options]) {
            $this->seed($seeder, $failed);
            $this->run2($command, $options, $failed);
        }

        // 4. Portal + flows without a module seeder (DMS disposal, KBA article review).
        $this->seed(PwsSeeder::class, $failed);
        $this->extraFlows($definitions);
        $this->fillApproverGroups();

        // 5. Derived data: risk scores, analytics, knowledge base.
        $this->run2('ptn:kira-risiko', [], $failed);
        $this->run2('lap:analitik', [], $failed);
        if (! $this->option('tanpa-kba')) {
            $this->run2('kba:index-baseline', [], $failed);
        }

        if ($this->option('pintas-mfa-uat')) {
            $this->uatMfaBypass();
        }

        $this->summary();
        if ($failed !== []) {
            $this->components->warn('Langkah gagal: '.implode(', ', $failed));

            return self::FAILURE;
        }
        $this->components->info('Selesai. Log masuk: admin@example.com / password; pengguna demo: '.implode(', ', array_keys(self::PENGGUNA_DEMO)).' / '.self::KATA_LALUAN_DEMO);

        return self::SUCCESS;
    }

    /** @param list<string> $failed */
    private function seed(string $class, array &$failed): void
    {
        $this->run2('db:seed', ['--class' => $class, '--force' => true], $failed, class_basename($class));
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $failed
     */
    private function run2(string $command, array $options, array &$failed, ?string $label = null): void
    {
        $label ??= $command;
        $this->components->task($label, function () use ($command, $options, $label, &$failed) {
            try {
                $code = $this->callSilently($command, $options);
            } catch (Throwable $e) {
                $this->newLine();
                $this->components->error("{$label}: ".$e->getMessage());
                $code = self::FAILURE;
            }
            if ($code !== self::SUCCESS) {
                $failed[] = $label;
            }

            return $code === self::SUCCESS;
        });
    }

    /** Roles, district scope and a local demo password for the Keycloak demo users (password login stays behind AUTH_PASSWORD_LOGIN). */
    private function demoUsers(RoleService $roles): void
    {
        $legal = Peranan::query()->where('kod', self::PERANAN_PERUNDANGAN)->first() ?? $roles->create([
            'kod' => self::PERANAN_PERUNDANGAN, 'nama' => 'Pegawai Perundangan (demo)',
            'keterangan' => 'DEMO: capaian eksplisit rekod kes berklasifikasi Rahsia (MOD-KES)',
        ]);
        $roles->setPermissions($legal, ['kes.kes.lihat', 'kes.kes.cipta', 'kes.kes.kemas_kini', 'kes.kes.lulus', 'kes.rahsia.lihat', 'kes.pihak.lihat', 'kes.laporan.lihat', 'kes.log_capaian.lihat']);

        foreach (self::PENGGUNA_DEMO as $email => [$codes, $districts]) {
            $user = User::query()->where('email', $email)->first();
            if ($user === null && isset(self::NAMA_DEMO[$email])) {
                $user = User::query()->forceCreate(['name' => self::NAMA_DEMO[$email], 'email' => $email, 'password' => Hash::make(self::KATA_LALUAN_DEMO), 'role' => 'user']);
            }
            if ($user === null) {
                continue;
            }
            $user->forceFill([
                'password' => Hash::make(self::KATA_LALUAN_DEMO), 'kata_laluan_ditukar_pada' => now(),
                'email_verified_at' => $user->email_verified_at ?? now(), 'is_active' => true, 'status_akaun' => 'aktif',
            ])->save();
            foreach ($codes as $code) {
                $roles->assignRoleByCode($user, $code);
            }
            $roles->syncUserScopes($user, $districts === [] ? [] : ['daerah' => $districts]);
        }
    }

    /** dms.pelupusan and kba.terbit_artikel are declared by their modules but have no seeder: one review stage each. */
    private function extraFlows(DefinitionService $definitions): void
    {
        $author = User::query()->where('email', 'admin@example.com')->first();
        if ($author === null) {
            return;
        }
        $flows = [
            ['dms.pelupusan', 'Kelulusan pelupusan dokumen', 'dms', 'dokumen_pelupusan', 'dms_pelulus_pelupusan', 'DMS — Pelulus pelupusan dokumen (Ketua Bahagian / Unit Rekod)'],
            ['kba.terbit_artikel', 'Semakan & penerbitan artikel Pangkalan Pengetahuan', 'kba', 'kba_artikel', 'kba_penyemak_artikel', 'KBA — Penyemak artikel pangkalan pengetahuan'],
        ];
        foreach ($flows as [$code, $name, $module, $subject, $group, $groupName]) {
            Kumpulan::query()->firstOrCreate(['kod' => $group], ['nama' => $groupName]);
            if (Definisi::query()->where('kod_aliran', $code)->exists()) {
                continue;
            }
            $definition = $definitions->create([
                'kod_aliran' => $code, 'nama' => $name, 'keterangan' => 'Dibenihkan oleh sisdata:demo-seed (satu peringkat semakan).',
                'modul' => $module, 'jenis_transaksi' => $subject, 'templat' => false,
                'peringkat' => [[
                    'kod' => 'semakan', 'nama' => 'Semakan & kelulusan', 'mod_kelulusan' => 'mana_mana',
                    'sla_nilai' => 3, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => false, 'perlu_justifikasi' => false,
                    'pelulus' => [['jenis' => 'kumpulan', 'nilai' => $group, 'label' => $groupName]],
                    'eskalasi' => [['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null]],
                ]],
                'peralihan' => [['dari' => 'mula', 'ke' => 'semakan'], ['dari' => 'semakan', 'ke' => 'tamat']],
                'susulan' => [],
                'susun_atur' => ['nodes' => ['mula' => ['x' => 0, 'y' => 120], 'semakan' => ['x' => 260, 'y' => 100], 'tamat' => ['x' => 560, 'y' => 120]]],
            ], $author, 'api', 'Dibenihkan oleh sisdata:demo-seed');
            $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
            $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh sisdata:demo-seed');
        }
    }

    /** Every approver group gets at least one demo member so each flow can be demonstrated end to end. */
    private function fillApproverGroups(): void
    {
        $ids = User::query()->whereIn('email', array_keys(self::PENGGUNA_DEMO))->pluck('id', 'email');
        foreach (Kumpulan::query()->withCount('ahli')->get() as $group) {
            $emails = self::AHLI_KUMPULAN[$group->kod] ?? ($group->ahli_count === 0 ? ['mohd.faizal@mais.test'] : []);
            $members = collect($emails)->map(fn ($e) => $ids[$e] ?? null)->filter()->values()->all();
            if ($members !== []) {
                $group->ahli()->syncWithoutDetaching($members);
            }
            // Segregation of duties drops the requester from the approvers: with a single
            // member, that member's own requests would fall to a non-demo supervisor.
            if ($group->ahli()->count() < 2) {
                $group->ahli()->syncWithoutDetaching(collect(['mohd.faizal@mais.test', 'nurul.huda@mais.test'])->map(fn ($e) => $ids[$e] ?? null)->filter()->values()->all());
            }
        }
    }

    /**
     * UAT only (APP_ENV=uat): testers sign in with passwords because the UAT
     * host is not registered with SSO MAIS. These two parameters stand in for
     * the SSO/MFA proof; both are critical parameters and the bypass is
     * refused by the code in any other environment. Written directly (no
     * two-admin approval) because this is a fresh demo database.
     */
    private function uatMfaBypass(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();
        foreach (['wfe.mfa_pintas_uat' => true, 'kes.mfa_wajib' => false] as $key => $value) {
            app(ParameterService::class)->store($key, $value, 'UAT: pengganti bukti SSO/MFA (sisdata:demo-seed --pintas-mfa-uat)', $admin);
        }
        $this->components->warn('Pintasan MFA UAT dihidupkan (wfe.mfa_pintas_uat=true, kes.mfa_wajib=false).');
    }

    private function summary(): void
    {
        $rows = [];
        foreach (['users', 'lot_tanah', 'hak_milik', 'pemerolehan', 'aduan', 'kontrak_pajakan', 'cukai', 'rekod_lawatan', 'kes_mahkamah', 'urusan_tanah', 'aset_fizikal', 'tkt_tiket', 'kba_segmen', 'wf_definisi', 'wf_kumpulan', 'audit_rekod'] as $table) {
            try {
                $rows[] = [$table, DB::table($table)->count()];
            } catch (Throwable) {
                $rows[] = [$table, '—'];
            }
        }
        $this->table(['Jadual', 'Baris'], $rows);
    }
}
