<?php

namespace Tests\Modules\Ref;

use App\Modules\Ref\Models\Daerah;
use App\Modules\Ref\Models\JenisHakMilik;
use App\Modules\Ref\Models\KategoriGunaTanah;
use App\Modules\Ref\Models\KlasifikasiData;
use App\Modules\Ref\Models\Mukim;
use App\Modules\Ref\Models\Pbt;
use App\Modules\Ref\Services\RefVersioning;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReferenceCodesTest extends RefTestCase
{
    public function test_SRS_DAT_05_seeds_all_selangor_districts_mukims_and_local_authorities(): void
    {
        $this->seedRef(spatial: false);

        $daerah = Daerah::query()->semasa()->get();
        $this->assertCount(9, $daerah);
        $this->assertEqualsCanonicalizing(
            ['Klang', 'Kuala Langat', 'Kuala Selangor', 'Sabak Bernam', 'Hulu Langat', 'Hulu Selangor', 'Petaling', 'Gombak', 'Sepang'],
            $daerah->pluck('nama')->all(),
        );
        foreach ($daerah as $d) {
            $this->assertMatchesRegularExpression('/^10\d{2}$/', $d->kod);
            $this->assertSame('10', $d->getAttribute('negeri_kod'));
        }

        $mukim = Mukim::query()->semasa()->get();
        $this->assertCount(54, $mukim);
        foreach ($mukim as $m) {
            // 6-digit code whose first four digits are its district code
            $this->assertMatchesRegularExpression('/^\d{6}$/', $m->kod);
            $this->assertSame(substr($m->kod, 0, 4), $m->getAttribute('daerah_kod'));
            $this->assertTrue($daerah->contains('kod', $m->getAttribute('daerah_kod')));
        }
        $this->assertSame(13, $mukim->where('daerah_kod', '1007')->count());

        $this->assertSame(12, Pbt::query()->semasa()->count());
        $this->assertSame('Majlis Bandaraya Diraja Klang', Pbt::query()->semasa()->where('kod', 'MBDK')->value('nama'));
    }

    public function test_SRS_DAT_05_starter_lists_cover_land_use_title_types_and_classification(): void
    {
        $this->seedRef(spatial: false);

        $this->assertEqualsCanonicalizing(['PERTANIAN', 'BANGUNAN', 'PERUSAHAAN', 'TIADA'], KategoriGunaTanah::query()->semasa()->pluck('kod')->all());
        foreach (['GRN', 'PN', 'GM', 'PM', 'HSD', 'HSM', 'TOL', 'RIZAB'] as $kod) {
            $this->assertTrue(JenisHakMilik::query()->semasa()->where('kod', $kod)->exists(), "jenis hak milik {$kod}");
        }
        $this->assertSame(['AWAM' => 1, 'DALAMAN' => 2, 'SULIT' => 3, 'RAHSIA' => 4], KlasifikasiData::query()->semasa()->orderBy('aras')->pluck('aras', 'kod')->all());
        $this->assertGreaterThan(5, DB::table('ref_jenis_dokumen')->count());
        $this->assertGreaterThan(5, DB::table('ref_kategori_aduan')->count());
    }

    public function test_SRS_DAT_05_database_rejects_a_second_current_row_for_the_same_code(): void
    {
        $this->seedRef(spatial: false);

        $this->expectException(QueryException::class);
        DB::table('ref_daerah')->insert([
            'kod' => '1008', 'nama' => 'Petaling Pendua', 'versi' => 9, 'sah_dari' => now()->toDateString(),
            'negeri_kod' => '10', 'kod_pendek' => '08',
        ]);
    }

    public function test_SRS_DAT_05_database_rejects_duplicate_names_within_a_district_but_allows_them_across_districts(): void
    {
        $this->seedRef(spatial: false);

        // "Batu" legitimately exists in Kuala Langat (100202) and Gombak (100901)
        $this->assertSame(2, Mukim::query()->semasa()->whereRaw('lower(nama) = ?', ['batu'])->count());

        $this->expectException(QueryException::class);
        DB::table('ref_mukim')->insert([
            'kod' => '100999', 'nama' => 'BATU', 'versi' => 1, 'sah_dari' => now()->toDateString(),
            'daerah_kod' => '1009', 'kod_pendek' => '99',
        ]);
    }

    public function test_SRS_DAT_05_a_change_creates_a_new_version_and_keeps_the_old_values(): void
    {
        Carbon::setTestNow('2026-01-01');
        $this->seedRef(spatial: false);
        $versioning = app(RefVersioning::class);

        Carbon::setTestNow('2026-06-15');
        [$result, $row] = $versioning->upsert(Pbt::class, ['kod' => 'MPKJ', 'nama' => 'Majlis Bandaraya Kajang', 'jenis' => 'majlis_bandaraya']);

        $this->assertSame(RefVersioning::VERSIONED, $result);
        $this->assertSame(2, $row->versi);

        $versions = Pbt::query()->where('kod', 'MPKJ')->orderBy('versi')->get();
        $this->assertCount(2, $versions);
        $this->assertSame('Majlis Perbandaran Kajang', $versions[0]->nama);
        $this->assertSame('2026-06-15', $versions[0]->sah_hingga?->toDateString());
        $this->assertNull($versions[1]->sah_hingga);

        // as-of queries return the value valid on that date
        $this->assertSame('Majlis Perbandaran Kajang', Pbt::query()->sahPada(Carbon::parse('2026-03-01'))->where('kod', 'MPKJ')->value('nama'));
        $this->assertSame('Majlis Bandaraya Kajang', Pbt::query()->sahPada()->where('kod', 'MPKJ')->value('nama'));
        Carbon::setTestNow();
    }

    public function test_SRS_DAT_05_reseeding_unchanged_data_is_a_no_op(): void
    {
        $this->seedRef(spatial: false);
        $before = DB::table('ref_mukim')->count();

        $stats = $this->seedRef(spatial: false)->stats;

        $this->assertSame($before, DB::table('ref_mukim')->count());
        foreach ($stats as $table => $s) {
            $this->assertSame(0, $s[RefVersioning::CREATED] + $s[RefVersioning::VERSIONED] + $s[RefVersioning::RETIRED], "{$table} changed on re-seed");
        }
    }

    public function test_SRS_DAT_05_a_code_removed_from_the_source_is_closed_not_deleted(): void
    {
        $this->seedRef(spatial: false);
        app(RefVersioning::class)->upsert(KategoriGunaTanah::class, ['kod' => 'UJIAN', 'nama' => 'Ujian', 'aktif' => true]);

        $stats = $this->seedRef(spatial: false)->stats;

        $this->assertSame(1, $stats['ref_kategori_guna_tanah'][RefVersioning::RETIRED]);
        $this->assertTrue(KategoriGunaTanah::query()->where('kod', 'UJIAN')->exists());
        $this->assertFalse(KategoriGunaTanah::query()->semasa()->where('kod', 'UJIAN')->exists());
    }
}
