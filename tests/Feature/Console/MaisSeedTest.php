<?php

namespace Tests\Feature\Console;

use App\Console\MaisSeed\Dataset;
use App\Modules\Dms\Jobs\IndeksKandunganDokumen;
use App\Modules\Dms\Services\Scanning\FakeVirusScanner;
use App\Modules\Dms\Services\Scanning\VirusScanner;
use Database\Seeders\AstAliranSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\G1AliranSeeder;
use Database\Seeders\PjkAliranSeeder;
use Database\Seeders\PtnAliranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * sisdata:mais-seed on a tiny fixture of database/data/mais/hartanah_mais.json:
 * guards, lineage, one record per process, synthetic-only personal data,
 * idempotency — plus sanity checks on the committed dataset itself.
 */
class MaisSeedTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = 'tests/Feature/Console/fixtures/hartanah_mais_mini.json';

    protected function setUp(): void
    {
        parent::setUp();
        config(['dms.encryption_key' => 'base64:'.base64_encode(str_repeat('m', 32))]);
        Storage::fake('dokumen');
        Queue::fake([IndeksKandunganDokumen::class]);
        $this->app->instance(VirusScanner::class, new FakeVirusScanner);
    }

    private function prasyarat(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('ref:seed')->assertSuccessful();
        foreach ([G1AliranSeeder::class, PjkAliranSeeder::class, AstAliranSeeder::class, PtnAliranSeeder::class] as $seeder) {
            $this->seed($seeder);
        }
    }

    public function test_mais_seed_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';
        try {
            $this->artisan('sisdata:mais-seed', ['--data' => self::FIXTURE])->assertFailed();
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertSame(0, DB::table('lot_tanah')->count());
    }

    public function test_mais_seed_refuses_a_database_without_reference_data(): void
    {
        $this->artisan('sisdata:mais-seed', ['--data' => self::FIXTURE])->assertFailed();
        $this->assertSame(0, DB::table('lot_tanah')->count());
    }

    public function test_SRS_DAT_08_mais_seed_builds_profiles_with_lineage_across_modules(): void
    {
        $this->prasyarat();
        $fixture = Dataset::load(self::FIXTURE);

        $this->artisan('sisdata:mais-seed', ['--data' => self::FIXTURE])->assertSuccessful();

        $lots = DB::table('lot_tanah')->where('sumber_sistem', 'mais_awam')->get();
        $this->assertCount(count($fixture->hartanah), $lots);
        foreach ($fixture->hartanah as $h) {
            $lot = $lots->firstWhere('kunci_asal', $h['kunci']);
            $this->assertNotNull($lot, $h['kunci']);
            $this->assertTrue((bool) $lot->sintetik);
            $this->assertSame('database/data/mais/hartanah_mais.json', $lot->fail_asal);
            $this->assertStringContainsString($h['nama'], (string) $lot->catatan, 'real name kept in catatan');
            $this->assertStringContainsString('SINTETIK', (string) $lot->catatan);
            $this->assertSame(1, DB::table('data_spatial')->where('lot_id', $lot->id)->where('semasa', true)->count());
            $this->assertSame(1, DB::table('pemerolehan')->where('lot_id', $lot->id)->where('sumber_rekod', 'sintetik')->count());
            $this->assertGreaterThanOrEqual(4, DB::table('cukai')->where('lot_id', $lot->id)->count(), 'bills 2023–2026');
            $this->assertGreaterThan(0, DB::table('aset_fizikal')->where('lot_id', $lot->id)->count());
            $this->assertGreaterThan(0, DB::table('rekod_lawatan')->where('lot_id', $lot->id)->count());
        }
        // Completed acquisitions register MAIS (an organisation) as owner, deposited in the vault.
        $titles = DB::table('hak_milik')->where('sumber_sistem', 'mais_awam')->get();
        $this->assertNotEmpty($titles);
        $this->assertSame(['Majlis Agama Islam Selangor'], $titles->pluck('pemilik_berdaftar')->unique()->values()->all());
        $this->assertSame($titles->count(), DB::table('daftar_peti_besi')->whereIn('hak_milik_id', $titles->pluck('id'))->where('jenis_dokumen_asal', 'dokumen_hak_milik_keluaran')->count());

        // A commercial property is let; tenants are synthetic only.
        $commercial = $lots->whereIn('kunci_asal', collect($fixture->hartanah)->whereIn('jenis', ['komersial', 'hotel', 'bangunan_pejabat'])->pluck('kunci'))->pluck('id');
        $this->assertNotEmpty($commercial);
        $this->assertGreaterThan(0, DB::table('kontrak_pajakan')->whereIn('lot_id', $commercial)->count());
        $tenants = DB::table('penyewa')->whereIn('id', DB::table('kontrak_pajakan')->whereIn('lot_id', $lots->pluck('id'))->select('penyewa_id'))->get();
        foreach ($tenants as $t) {
            $this->assertTrue((bool) $t->sintetik);
            $this->assertStringContainsString('(Sintetik)', $t->nama);
            if ($t->no_pengenalan !== null) {
                $this->assertMatchesRegularExpression('/^\d{6}-00-\d{4}$/', $t->no_pengenalan, 'impossible place-of-birth code');
            }
        }
        // Documents through DMS, watermarked PDFs stored on the (fake) disk.
        $this->assertGreaterThan(0, DB::table('dokumen')->where('jenis_entiti_induk', 'lot_tanah')->whereIn('id_rekod_induk', $lots->pluck('id'))->count());
        // Approvals went to WFE.
        $this->assertGreaterThan(0, DB::table('wf_instans')->count());
    }

    public function test_SRS_DAT_08_mais_seed_is_idempotent(): void
    {
        $this->prasyarat();
        $this->artisan('sisdata:mais-seed', ['--data' => self::FIXTURE])->assertSuccessful();
        $counts = fn () => [DB::table('lot_tanah')->count(), DB::table('hak_milik')->count(), DB::table('kontrak_pajakan')->count(), DB::table('cukai')->count(), DB::table('aset_fizikal')->count()];
        $before = $counts();

        $this->artisan('sisdata:mais-seed', ['--data' => self::FIXTURE])->assertSuccessful();
        $this->artisan('sisdata:mais-seed', ['--data' => self::FIXTURE, '--force' => true])->assertSuccessful();

        $this->assertSame($before, $counts());
    }

    public function test_committed_dataset_is_curated_public_data_without_personal_fields(): void
    {
        $set = Dataset::load(Dataset::LALAI);
        $this->assertGreaterThanOrEqual(100, count($set->hartanah));
        $this->assertCount(9, array_unique(array_column($set->hartanah, 'kod_daerah')), 'all nine districts');
        $this->assertLessThan(2 * 1024 * 1024, filesize(base_path(Dataset::LALAI)));
        $json = (string) file_get_contents(base_path(Dataset::LALAI));
        $this->assertDoesNotMatchRegularExpression('/\b\d{6}-\d{2}-\d{4}\b/', $json, 'no MyKad numbers');
        $this->assertDoesNotMatchRegularExpression('/\b01\d-?\d{7,8}\b/', $json, 'no mobile numbers');
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', $json, 'no e-mail addresses');
        foreach ($set->hartanah as $h) {
            $this->assertNotEmpty($h['sumber_url'] ?? [], $h['kunci'].' has a public source');
            $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $h['kod_mukim']);
            $this->assertGreaterThan(2.5, (float) $h['lat']);
            $this->assertLessThan(3.9, (float) $h['lat']);
        }
    }
}
