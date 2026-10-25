<?php

namespace Tests\Modules\Lap;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Lap\Support\ArrayParameters;
use Tests\Modules\Lap\Support\RecordingNotifier;
use Tests\TestCase;

/**
 * MOD-LAP test set-up: real RBC (factory permissions / scopes), real AUD,
 * array parameters (no result cache unless a test turns it on), a recording
 * notifier, and a tiny synthetic portfolio inserted straight into the
 * owning modules' tables (MOD-LAP only reads them through `laporan` views):
 * two districts — 1008 Petaling, 1009 Sepang.
 */
abstract class LapTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    protected ArrayParameters $params;

    protected RecordingNotifier $notifier;

    /** Everything a state-level report reader needs. */
    public const PEMBACA = [
        'lap.laporan.lihat', 'lap.laporan.eksport', 'lap.adhoc.cipta', 'lap.jadual.cipta',
        'pro.lot.lihat', 'hkm.hak_milik.lihat', 'pjk.laporan.lihat', 'cki.laporan.lihat', 'adu.laporan.lihat', 'ptn.laporan.lihat',
        'wfe.laporan.lihat', 'kes.laporan.lihat', 'ast.laporan.lihat', 'per.laporan.lihat', 'ptn.risiko.lihat',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(Parameters::class, $this->params = new ArrayParameters);
        $this->params->values['lap.cache_saat'] = 0;
        $this->app->instance(Notifier::class, $this->notifier = new RecordingNotifier);
        $this->seedReference();
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>|null  $districts
     */
    protected function user(array $permissions = self::PEMBACA, ?array $districts = null, ?string $role = null): User
    {
        $f = User::factory()->withPermissions($permissions);
        if ($districts !== null) {
            $f = $f->withScope('daerah', $districts);
        }
        if ($role !== null) {
            $f = $f->withRole($role);
        }

        return $f->create(['role' => 'user', 'is_active' => true]);
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
            $row(['kod' => '100804', 'nama' => 'Bukit Raja', 'daerah_kod' => '1008', 'kod_pendek' => '04']),
            $row(['kod' => '100901', 'nama' => 'Dengkil', 'daerah_kod' => '1009', 'kod_pendek' => '01']),
        ]);
        DB::table('ref_kategori_guna_tanah')->insert([
            $row(['kod' => 'BANGUNAN', 'nama' => 'Bangunan']),
            $row(['kod' => 'PERTANIAN', 'nama' => 'Pertanian']),
        ]);
    }

    /** @param array<string, mixed> $a */
    protected function lot(array $a = []): string
    {
        $id = (string) Str::uuid();
        $mukim = $a['kod_mukim'] ?? '100803';
        DB::table('lot_tanah')->insert($a + [
            'id' => $id, 'no_lot' => 'Lot '.random_int(1000, 999999), 'kod_mukim' => $mukim, 'kod_daerah' => substr($mukim, 0, 4),
            'keluasan' => 10000, 'unit_keluasan' => 'm2', 'kategori_guna_tanah' => 'BANGUNAN', 'bahagian_pemilik' => 'wakaf',
            'status_lot' => 'aktif', 'klasifikasi' => 'dalaman', 'sintetik' => true, 'created_at' => now(), 'updated_at' => now(),
            'koordinat_pusat' => DB::raw('ST_SetSRID(ST_MakePoint(101.6, 3.1), 4326)'),
        ]);

        return $id;
    }

    /** @param array<string, mixed> $a */
    protected function contract(string $lotId, array $a = []): string
    {
        $id = (string) Str::uuid();
        $lot = DB::table('lot_tanah')->where('id', $lotId)->first();
        DB::table('kontrak_pajakan')->insert($a + [
            'id' => $id, 'lot_id' => $lotId, 'no_kontrak' => 'PJK/'.random_int(1000, 99999), 'status' => 'aktif', 'kategori_hartanah' => 'premis',
            'tarikh_mula' => '2024-01-01', 'tarikh_tamat' => now()->addYears(2)->toDateString(), 'kadar' => 1000, 'nilai_kontrak' => 36000,
            'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim, 'bahagian' => 'wakaf', 'klasifikasi' => 'dalaman', 'sintetik' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, mixed> $a */
    protected function charge(string $contractId, string $date, string $amount, string $paid = '0', array $a = []): int
    {
        return DB::table('jadual_caj')->insertGetId($a + [
            'kontrak_id' => $contractId, 'no_caj' => 'C'.random_int(100000, 999999), 'kod_caj' => 'sewa', 'tempoh' => substr($date, 0, 7),
            'tarikh_caj' => $date, 'tarikh_akhir' => $date, 'amaun' => $amount, 'amaun_dibayar' => $paid, 'amaun_dikecualikan' => 0,
            'status_caj' => bccomp($paid, $amount, 2) >= 0 ? 'dibayar' : 'belum_bayar', 'dikeluarkan' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $a */
    protected function tax(string $lotId, array $a = []): string
    {
        $id = (string) Str::uuid();
        $lot = DB::table('lot_tanah')->where('id', $lotId)->first();
        DB::table('cukai')->insert($a + [
            'id' => $id, 'lot_id' => $lotId, 'jenis' => 'cukai_tanah', 'no_bil' => 'B'.random_int(100000, 999999), 'jumlah' => 500,
            'tarikh_bil' => now()->subMonths(6)->toDateString(), 'tarikh_akhir' => now()->subMonths(5)->toDateString(), 'status_bayaran' => 'tertunggak',
            'amaun_dibayar' => 0, 'amaun_denda' => 0, 'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim, 'klasifikasi' => 'dalaman',
            'sintetik' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, mixed> $a */
    protected function complaint(string $kodMukim, array $a = []): string
    {
        $id = (string) Str::uuid();
        DB::table('aduan')->insert($a + [
            'id' => $id, 'no_rujukan_aduan' => 'ADU-'.random_int(10000, 99999), 'kategori' => 'pencerobohan', 'status' => 'baharu',
            'tarikh_aduan' => now()->toDateString(), 'tarikh_terima' => now(), 'saluran_penerimaan' => 'portal', 'kod_mukim' => $kodMukim,
            'kod_daerah' => substr($kodMukim, 0, 4), 'klasifikasi' => 'dalaman', 'sintetik' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function jana(User $user, array $source, array $extra = []): array
    {
        return $this->actingAs($user)->postJson('/api/v1/lap/jana', ['sumber' => $source] + $extra)->assertOk()->json('data');
    }

    /**
     * Rows of the first section keyed by the first group / label.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    protected function rows(array $data, string $key): array
    {
        $out = [];
        foreach ($data['bahagian'][0]['hasil']['baris'] as $r) {
            $out[(string) ($r[$key] ?? '')] = $r;
        }

        return $out;
    }
}
