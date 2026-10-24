<?php

namespace Tests\Modules\Ref;

use App\Modules\Ref\Services\NameNormaliser;
use App\Modules\Ref\Services\SampleLotGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SampleLotsTest extends RefTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRef();
    }

    public function test_generated_lots_fall_inside_their_mukim_polygon(): void
    {
        $this->artisan('ref:sample-lots', ['--count' => 120, '--seed' => 7])->assertSuccessful();

        $this->assertSame(120, DB::table('staging_lot_tanah')->count());
        $outside = DB::selectOne(<<<'SQL'
            SELECT count(*) AS n FROM staging_lot_tanah l
            LEFT JOIN ref_sempadan s ON s.jenis = 'mukim' AND s.kod = l.mukim_kod
            WHERE s.id IS NULL OR NOT ST_Within(l.geometri, s.geometri) OR NOT ST_IsValid(l.geometri)
        SQL)->n;
        $this->assertSame(0, (int) $outside);

        // district / state consistent with the mukim code, and with the district polygon
        $this->assertSame(0, DB::table('staging_lot_tanah')->whereRaw('daerah_kod <> left(mukim_kod, 4) OR negeri_kod <> ?', ['10'])->count());
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) AS n FROM staging_lot_tanah l JOIN ref_sempadan d ON d.jenis = 'daerah' AND d.kod = l.daerah_kod WHERE NOT ST_Intersects(l.geometri, d.geometri)")->n);
    }

    public function test_SRS_DAT_01_no_duplicate_lot_number_within_a_mukim(): void
    {
        (new SampleLotGenerator(11))->generate(400, ['1008', '1001'], messy: 0.3);

        $seen = [];
        foreach (DB::table('staging_lot_tanah')->get(['mukim_kod', 'no_lot', 'jenis_lot']) as $lot) {
            $digits = preg_replace('/\D/', '', $lot->no_lot);
            $key = $lot->mukim_kod.'|'.$lot->jenis_lot.'|'.$digits;
            $this->assertArrayNotHasKey($key, $seen, "duplicate {$lot->no_lot} in {$lot->mukim_kod}");
            $seen[$key] = true;
        }
        $this->assertCount(400, $seen);
    }

    public function test_SRS_DAT_01_lot_fields_respect_format_and_valid_ranges(): void
    {
        (new SampleLotGenerator(3))->generate(150);

        foreach (DB::table('staging_lot_tanah')->get() as $lot) {
            $this->assertMatchesRegularExpression('/^(Lot|PT) [1-9]\d*$/', $lot->no_lot);
            $this->assertSame($lot->jenis_lot === 'PT' ? 'PT' : 'Lot', explode(' ', $lot->no_lot)[0]);
            $this->assertGreaterThan(0, (float) $lot->keluasan);
            $this->assertContains($lot->unit_keluasan, ['meter_persegi', 'hektar']);
            $this->assertContains($lot->kategori_guna_tanah_kod, ['PERTANIAN', 'BANGUNAN', 'PERUSAHAAN', 'TIADA']);
            $this->assertTrue(DB::table('ref_mukim')->whereNull('sah_hingga')->where('kod', $lot->mukim_kod)->exists());
            if ($lot->jenis_wakaf !== null) {
                $this->assertTrue((bool) $lot->wakaf);
            }
        }

        // recorded area matches the polygon area (±1 %)
        $off = DB::selectOne("SELECT count(*) AS n FROM staging_lot_tanah WHERE abs(ST_Area(geometri::geography) - keluasan * CASE unit_keluasan WHEN 'hektar' THEN 10000 ELSE 1 END) > 0.01 * ST_Area(geometri::geography)")->n;
        $this->assertSame(0, (int) $off);

        // qualified-title lots (PT) carry a temporary title, final lots a final title
        $mismatch = DB::table('staging_hak_milik as h')->join('staging_lot_tanah as l', 'l.id', '=', 'h.staging_lot_tanah_id')
            ->where(fn ($q) => $q->where('l.jenis_lot', 'PT')->whereNotIn('h.jenis_hak_milik_kod', ['HSD', 'HSM'])
                ->orWhere(fn ($q) => $q->where('l.jenis_lot', 'LOT')->whereIn('h.jenis_hak_milik_kod', ['HSD', 'HSM'])))
            ->count();
        $this->assertSame(0, $mismatch);
    }

    public function test_SRS_DAT_08_every_staging_row_records_its_lineage_and_load_run(): void
    {
        $result = (new SampleLotGenerator(5))->generate(60);

        foreach (['staging_lot_tanah', 'staging_hak_milik', 'staging_pemilik'] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), $table);
            $this->assertSame(0, DB::table($table)->where(fn ($q) => $q->whereNull('sumber_sistem')->orWhereNull('fail_asal')->orWhereNull('kunci_asal')->orWhereNull('dimuat_pada'))->count(), $table);
            $this->assertSame(0, DB::table($table)->where('migrasi_larian_id', '<>', $result['larian']->id)->count(), $table);
            $this->assertSame(
                DB::table($table)->count(),
                DB::table($table)->distinct()->count(DB::raw("sumber_sistem || '|' || fail_asal || '|' || kunci_asal")),
                "{$table} lineage key must be unique",
            );
        }

        $this->assertSame('ref:sample-lots?seed=5', DB::table('staging_lot_tanah')->value('fail_asal'));
        $this->assertDatabaseHas('migrasi_larian', ['id' => $result['larian']->id, 'status' => 'selesai', 'bilangan_diterima' => 60, 'sumber_sistem' => 'SINTETIK']);
    }

    public function test_SRS_DAT_08_rerunning_the_same_seed_replaces_its_rows_with_identical_keys(): void
    {
        (new SampleLotGenerator(9))->generate(40);
        $first = DB::table('staging_lot_tanah')->orderBy('kunci_asal')->pluck('no_lot', 'id')->all();

        (new SampleLotGenerator(9))->generate(40);
        $second = DB::table('staging_lot_tanah')->orderBy('kunci_asal')->pluck('no_lot', 'id')->all();

        $this->assertSame($first, $second);
        $this->assertSame(2, DB::table('migrasi_larian')->count());
    }

    public function test_all_owner_data_is_synthetic_and_ic_numbers_cannot_be_real(): void
    {
        (new SampleLotGenerator(13))->generate(80);

        foreach (['staging_lot_tanah', 'staging_hak_milik', 'staging_pemilik'] as $table) {
            $this->assertSame(0, DB::table($table)->where('sintetik', false)->count(), $table);
        }

        $people = DB::table('staging_pemilik')->where('jenis_pemilik', 'individu')->get();
        $this->assertNotEmpty($people);
        foreach ($people as $p) {
            $this->assertMatchesRegularExpression('/^\d{6}-00-\d{4}$/', $p->no_pengenalan); // "00" = no JPN place-of-birth code
            $this->assertMatchesRegularExpression('/ (bin|binti) /', $p->nama);
        }

        // The database itself refuses a realistic IC on a synthetic row.
        $this->expectException(QueryException::class);
        DB::table('staging_pemilik')->where('id', $people[0]->id)->update(['no_pengenalan' => '800101-10-5011']);
    }

    public function test_wakaf_and_baitulmal_flags_and_owners_are_consistent(): void
    {
        (new SampleLotGenerator(21))->generate(150);

        $this->assertGreaterThan(0, DB::table('staging_lot_tanah')->where('wakaf', true)->count());
        $this->assertGreaterThan(0, DB::table('staging_lot_tanah')->where('baitulmal', true)->count());
        $this->assertSame(0, DB::table('staging_lot_tanah')->where('wakaf', true)->where('baitulmal', true)->count());

        // every title is registered to MAIS; wakaf titles name at least one (synthetic) donor
        $this->assertSame(DB::table('staging_hak_milik')->count(), DB::table('staging_pemilik')->where('peranan', 'pemilik_berdaftar')->where('nama', 'Majlis Agama Islam Selangor')->count());
        $wakafWithoutDonor = DB::table('staging_hak_milik as h')->join('staging_lot_tanah as l', 'l.id', '=', 'h.staging_lot_tanah_id')
            ->where('l.wakaf', true)
            ->whereNotExists(fn ($q) => $q->from('staging_pemilik as p')->whereColumn('p.staging_hak_milik_id', 'h.id')->where('p.peranan', 'pewakaf'))
            ->count();
        $this->assertSame(0, $wakafWithoutDonor);
    }

    public function test_name_normaliser_treats_spelling_variants_as_equal(): void
    {
        $this->assertSame(NameNormaliser::normalise('Ulu Kelang'), NameNormaliser::normalise('Mukim Hulu Kelang'));
        $this->assertSame(NameNormaliser::normalise('Tanjong Duabelas'), NameNormaliser::normalise('Tanjung Dua Belas'));
        $this->assertSame(NameNormaliser::normalise('Telok Panglima Garang'), NameNormaliser::normalise('Teluk Panglima Garang'));
        $this->assertNotSame(NameNormaliser::normalise('Batu'), NameNormaliser::normalise('Batang Kali'));
    }
}
