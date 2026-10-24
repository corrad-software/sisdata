<?php

namespace Tests\Modules\Dsh;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dsh\Kpi\DshKpi;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Dsh\Support\ArrayParameters;
use Tests\TestCase;

/**
 * MOD-DSH test set-up: real RBC (factory permissions / scopes), real AUD and
 * the real KpiProviders registry (with PJK / CKI / ADU's KPIs registered by
 * their own providers), array parameters (no KPI cache unless a test turns it
 * on), and a tiny synthetic portfolio inserted straight into the owning
 * modules' tables (MOD-DSH only reads them): two districts — 1008 Petaling,
 * 1009 Sepang.
 */
abstract class DshTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    protected ArrayParameters $params;

    public const EKSEKUTIF = ['dsh.dashboard.lihat', 'dsh.pengurusan.lihat', 'dsh.eksekutif.lihat', 'dsh.dashboard.eksport', 'dsh.ai.lihat', 'dsh.langganan.cipta', 'pro.lot.lihat', 'hkm.hak_milik.lihat', 'per.laporan.lihat', 'wfe.pemantauan.lihat', 'wfe.laporan.lihat', 'wfe.tugasan.lihat'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(Parameters::class, $this->params = new ArrayParameters);
        $this->params->values['dsh.cache_saat'] = 0;
        app(PermissionRegistry::class)->register('ptn', 'Pemantauan Tanah (ujian)', ['ptn.laporan.lihat' => 'Lihat laporan pemantauan (ujian)']);
        DshKpi::flushNames();
        $this->seedReference();
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>|null  $districts
     */
    protected function user(array $permissions = self::EKSEKUTIF, ?array $districts = null, ?string $role = null, ?array $bahagian = null): User
    {
        $factory = User::factory()->withPermissions($permissions);
        if ($districts !== null) {
            $factory = $factory->withScope('daerah', $districts);
        }
        if ($bahagian !== null) {
            $factory = $factory->withScope('bahagian', $bahagian);
        }
        if ($role !== null) {
            $factory = $factory->withRole($role);
        }

        return $factory->create(['role' => 'user', 'is_active' => true]);
    }

    protected function seedReference(): void
    {
        $now = now();
        $row = fn (array $r) => $r + ['sah_dari' => '2020-01-01', 'aktif' => true, 'created_at' => $now, 'updated_at' => $now];
        DB::table('ref_daerah')->insert([
            $row(['kod' => '1008', 'nama' => 'Petaling', 'negeri_kod' => '10', 'kod_pendek' => '08', 'susunan' => 1]),
            $row(['kod' => '1009', 'nama' => 'Sepang', 'negeri_kod' => '10', 'kod_pendek' => '09', 'susunan' => 2]),
        ]);
        DB::table('ref_mukim')->insert([
            $row(['kod' => '100803', 'nama' => 'Damansara', 'daerah_kod' => '1008', 'kod_pendek' => '03']),
            $row(['kod' => '100901', 'nama' => 'Dengkil', 'daerah_kod' => '1009', 'kod_pendek' => '01']),
        ]);
        DB::table('ref_kategori_guna_tanah')->insert([
            $row(['kod' => 'BANGUNAN', 'nama' => 'Bangunan']),
            $row(['kod' => 'PERTANIAN', 'nama' => 'Pertanian']),
        ]);
        DB::table('ref_jenis_hak_milik')->insert([
            $row(['kod' => 'GRN', 'nama' => 'Geran', 'singkatan' => 'GRN', 'peringkat' => 'daftar']),
            $row(['kod' => 'PM', 'nama' => 'Pajakan Mukim', 'singkatan' => 'PM', 'peringkat' => 'daftar']),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    protected function lot(array $attributes = []): string
    {
        $id = (string) Str::uuid();
        $mukim = $attributes['kod_mukim'] ?? '100803';
        DB::table('lot_tanah')->insert($attributes + [
            'id' => $id,
            'no_lot' => 'Lot '.random_int(1000, 999999),
            'kod_mukim' => $mukim,
            'kod_daerah' => substr($mukim, 0, 4),
            'keluasan' => 10000,
            'unit_keluasan' => 'm2',
            'kategori_guna_tanah' => 'BANGUNAN',
            'bahagian_pemilik' => 'wakaf',
            'status_lot' => 'aktif',
            'klasifikasi' => 'dalaman',
            'sintetik' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, mixed> $attributes */
    protected function title(string $lotId, array $attributes = []): string
    {
        $id = (string) Str::uuid();
        $lot = DB::table('lot_tanah')->where('id', $lotId)->first();
        DB::table('hak_milik')->insert($attributes + [
            'id' => $id, 'lot_id' => $lotId, 'no_hak_milik' => 'GRN '.random_int(1000, 99999), 'jenis_hak_milik' => 'GRN',
            'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim, 'pemilik_berdaftar' => 'MAIS (sintetik)',
            'status_semasa' => 'aktif', 'disahkan' => true, 'klasifikasi' => 'sulit', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, mixed> $attributes */
    protected function acquisition(array $attributes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('pemerolehan')->insert($attributes + [
            'id' => $id, 'no_kes' => 'PER/'.random_int(1000, 99999), 'id_saluran' => 1, 'tajuk' => 'Kes sintetik',
            'kod_daerah' => '1008', 'kod_bahagian' => 'wakaf', 'tarikh_mula_kes' => now()->subDays(30)->toDateString(),
            'status_pemerolehan' => 'dalam_proses', 'tempoh_sasaran_hari' => 180, 'klasifikasi' => 'sulit',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** An open WFE task (instance + task rows, straight into MOD-WFE's tables). */
    protected function task(string $penerima, ?string $kodDaerah = '1008', ?string $due = null, string $status = 'menunggu', string $modul = 'per'): int
    {
        $def = DB::table('wf_definisi')->insertGetId(['kod_aliran' => 'ujian.'.Str::random(6), 'nama' => 'Aliran ujian', 'no_versi' => 1, 'jenis_transaksi' => 'ujian', 'modul' => $modul, 'created_at' => now(), 'updated_at' => now()]);
        $inst = (string) Str::uuid();
        DB::table('wf_instans')->insert(['id' => $inst, 'id_definisi' => $def, 'no_versi_definisi' => 1, 'kod_aliran' => 'ujian', 'modul' => $modul, 'jenis_subjek' => 'ujian',
            'id_rekod' => '1', 'tajuk' => 'Permohonan ujian', 'kod_daerah' => $kodDaerah, 'id_pemohon' => User::factory()->create()->id, 'status' => 'berjalan', 'tarikh_mula' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('wf_tugasan')->insertGetId(['id_instans' => $inst, 'jenis' => 'kelulusan', 'tajuk' => 'Semak permohonan', 'jenis_penerima' => strtok($penerima, ':'),
            'nilai_penerima' => substr($penerima, strpos($penerima, ':') + 1), 'penerima_kunci' => $penerima, 'status' => $status,
            'tarikh_akhir_sla' => $due ?? now()->addDays(3), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, array<string, mixed>> KPI results keyed by key */
    protected function kpis(User $user, array $query = []): array
    {
        $json = $this->actingAs($user)->getJson('/api/v1/dsh/kpi?'.http_build_query($query))->assertOk()->json('data');

        return collect($json)->keyBy('key')->all();
    }
}
