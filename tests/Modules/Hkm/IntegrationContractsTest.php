<?php

namespace Tests\Modules\Hkm;

use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Hkm\Events\TitleRegistered;
use App\Modules\Hkm\Models\DaftarPetiBesi;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\WarisPemilikBersama;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * TitleRegistry for MOD-PER (SRS-PER-21/22 → SRS-HKM-01/03) and the staging
 * import command (sample data).
 */
class IntegrationContractsTest extends HkmTestCase
{
    public function test_SRS_HKM_01_title_registry_creates_title_and_vault_entry_for_completed_acquisition(): void
    {
        Event::fake([TitleRegistered::class]);
        $registry = app(TitleRegistry::class);
        $actor = $this->user();
        $this->vault(null, 'PB-PER');
        $lotId = $this->lot();

        $id = $registry->registerFromAcquisition($lotId, [
            'no_hak_milik' => 'GRN 60001', 'jenis_hak_milik' => 'GRN', 'tarikh_pendaftaran' => '2026-10-01', 'pemilik_berdaftar' => 'Majlis Agama Islam Selangor',
        ], $actor, ['kod_peti_besi' => 'PB-PER', 'no_fail' => 'PER-1'], 'pemerolehan:abc');

        $title = HakMilik::query()->withoutGlobalScopes()->findOrFail($id);
        $this->assertSame($lotId, $title->lot_id);
        $this->assertSame('PER-1', DaftarPetiBesi::query()->where('hak_milik_id', $id)->value('no_fail'));
        $this->assertDatabaseHas('kronologi_pemilikan', ['hak_milik_id' => $id, 'id_pemerolehan' => 'pemerolehan:abc', 'sumber' => 'pemerolehan']);
        Event::assertDispatched(TitleRegistered::class, fn ($e) => $e->titleId === $id && $e->sourceRef === 'pemerolehan:abc');

        // Same title number in the same mukim updates rather than duplicating.
        $again = $registry->registerFromAcquisition($lotId, ['no_hak_milik' => 'grn 60001', 'jenis_hak_milik' => 'GRN', 'pemilik_berdaftar' => 'MAIS (Baitulmal)'], $actor, null, 'pemerolehan:def');
        $this->assertSame($id, $again);
        $this->assertSame('MAIS (Baitulmal)', $title->refresh()->pemilik_berdaftar);
        $this->assertSame(2, $title->versi_rekod);

        $this->assertSame([['id' => $id, 'no_hak_milik' => 'GRN 60001', 'jenis_hak_milik' => 'GRN', 'status_semasa' => 'aktif', 'tarikh_tamat_tempoh' => null]], $registry->forLot($lotId, $actor));
        $this->assertSame([], $registry->forLot($lotId, $this->user(self::PENDAFTAR, ['1009'])));
    }

    public function test_SRS_HKM_01_import_staging_matches_lots_and_is_idempotent(): void
    {
        $run = (string) Str::uuid();
        DB::table('migrasi_larian')->insert(['id' => $run, 'jenis' => 'sampel', 'sumber_sistem' => 'SINTETIK', 'status' => 'selesai', 'mula_pada' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $lineage = fn (string $key) => ['sintetik' => true, 'sumber_sistem' => 'SINTETIK', 'fail_asal' => 'ref:sample-lots?seed=1', 'kunci_asal' => $key, 'dimuat_pada' => now(), 'created_at' => now(), 'updated_at' => now(), 'migrasi_larian_id' => $run];

        $stagingLots = [];
        foreach ([['LOT-000001', 'Lot 501', true], ['LOT-000002', 'PT 77', false]] as [$key, $no, $imported]) {
            $id = (string) Str::uuid();
            $stagingLots[$key] = $id;
            DB::table('staging_lot_tanah')->insert($lineage($key) + ['id' => $id, 'no_lot' => $no, 'jenis_lot' => 'LOT', 'negeri_kod' => '10', 'daerah_kod' => '1008', 'mukim_kod' => '100803',
                'keluasan' => 1000, 'unit_keluasan' => 'meter_persegi', 'kategori_guna_tanah_kod' => 'BANGUNAN', 'sekatan_kepentingan' => $key === 'LOT-000001' ? 'Tanah wakaf — tidak boleh dijual (contoh sintetik).' : null, 'wakaf' => true]);
            if ($imported) {
                $this->lot(['no_lot' => $no, 'kod_mukim' => '100803']); // MOD-PRO imported it with a new id: match by mukim + no_lot
            }
        }
        foreach ([['HM-000001', 'LOT-000001', 'GRN 123'], ['HM-000002', 'LOT-000002', 'HS(D) 9']] as [$key, $lotKey, $no]) {
            $hid = (string) Str::uuid();
            DB::table('staging_hak_milik')->insert($lineage($key) + ['id' => $hid, 'staging_lot_tanah_id' => $stagingLots[$lotKey], 'no_hak_milik' => $no, 'jenis_hak_milik_kod' => str_starts_with($no, 'GRN') ? 'GRN' : 'HSD',
                'tarikh_daftar' => '1990-01-01', 'lokasi_simpanan_fizikal' => 'Bilik Kebal MAIS, Rak C-12, Fail 0042 (sintetik)']);
            DB::table('staging_pemilik')->insert($lineage('PMK-'.$key.'-1') + ['id' => (string) Str::uuid(), 'staging_hak_milik_id' => $hid, 'peranan' => 'pemilik_berdaftar', 'jenis_pemilik' => 'organisasi', 'nama' => 'Majlis Agama Islam Selangor', 'bahagian' => '1/1']);
            DB::table('staging_pemilik')->insert($lineage('PMK-'.$key.'-2') + ['id' => (string) Str::uuid(), 'staging_hak_milik_id' => $hid, 'peranan' => 'pewakaf', 'jenis_pemilik' => 'individu', 'nama' => 'Ali bin Abu', 'no_pengenalan' => '500101-00-1111', 'bahagian' => '1/1']);
        }

        $this->artisan('hkm:import-staging')->assertSuccessful();

        $title = HakMilik::query()->withoutGlobalScopes()->where('kunci_asal', 'HM-000001')->firstOrFail();
        $this->assertSame('GRN 123', $title->no_hak_milik);
        $this->assertSame('SINTETIK', $title->sumber_sistem);
        $this->assertSame(1, HakMilik::query()->withoutGlobalScopes()->count()); // HM-000002's lot isn't imported yet → skipped
        $this->assertSame('pewakaf', WarisPemilikBersama::query()->where('hak_milik_id', $title->id)->value('hubungan'));
        $this->assertTrue((bool) WarisPemilikBersama::query()->where('hak_milik_id', $title->id)->value('sintetik'));
        $this->assertSame(['C-12', '0042'], array_values(DaftarPetiBesi::query()->where('hak_milik_id', $title->id)->first(['lokasi_rak', 'no_fail'])->only(['lokasi_rak', 'no_fail'])));
        $this->assertDatabaseHas('sekatan_kepentingan', ['hak_milik_id' => $title->id, 'jenis_sekatan' => 'sekatan_kepentingan', 'status_sekatan' => 'aktif']);

        // Re-run: nothing duplicated; once the second lot exists it is picked up.
        $this->lot(['no_lot' => 'PT 77', 'kod_mukim' => '100803']);
        $this->artisan('hkm:import-staging')->assertSuccessful();
        $this->assertSame(2, HakMilik::query()->withoutGlobalScopes()->count());
    }
}
