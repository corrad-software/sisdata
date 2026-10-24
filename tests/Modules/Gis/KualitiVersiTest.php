<?php

namespace Tests\Modules\Gis;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class KualitiVersiTest extends GisTestCase
{
    private function qa(?array $districts = null): User
    {
        return $this->user(['gis.peta.lihat', 'pro.lot.lihat', 'gis.kualiti.lihat', 'gis.kualiti.kemas_kini'], $districts);
    }

    /** @return array<string, list<array<string, mixed>>> findings by type */
    private function findings(User $user): array
    {
        return collect($this->actingAs($user)->getJson('/api/v1/gis/kualiti?per_halaman=100')->assertOk()->json('data'))->groupBy('jenis_ralat')->map->values()->map->all()->all();
    }

    public function test_SRS_GIS_28_detects_invalid_geometry_overlaps_gaps_and_missing_geometry(): void
    {
        $bowtie = 'MULTIPOLYGON(((101.55 3.10, 101.551 3.101, 101.551 3.10, 101.55 3.101, 101.55 3.10)))';
        $invalid = $this->lot(null, ['no_lot' => 'Bowtie']);
        $this->geometry($invalid, $bowtie, valid: false);
        $a = $this->lot($this->square(101.56, 3.10), ['no_lot' => 'A']);
        $b = $this->lot($this->square(101.5605, 3.10), ['no_lot' => 'B']);      // overlaps half of A
        $none = $this->lot(null, ['no_lot' => 'Tiada']);
        // Four lots around a 20 m × 20 m hole → a gap.
        $s = 0.001;
        $h = 0.0002;
        foreach ([[101.57, 3.10, 101.57 + 2 * $s + $h, 3.10 + $s], [101.57, 3.10 + $s + $h, 101.57 + 2 * $s + $h, 3.10 + 2 * $s + $h],
            [101.57, 3.10 + $s, 101.57 + $s, 3.10 + $s + $h], [101.57 + $s + $h, 3.10 + $s, 101.57 + 2 * $s + $h, 3.10 + $s + $h]] as [$x0, $y0, $x1, $y1]) {
            $this->lot("MULTIPOLYGON((({$x0} {$y0}, {$x1} {$y0}, {$x1} {$y1}, {$x0} {$y1}, {$x0} {$y0})))");
        }

        $user = $this->qa();
        $run = $this->actingAs($user)->postJson('/api/v1/gis/kualiti/jalankan')->assertOk();
        $this->assertGreaterThan(0, $run->json('jumlah_terbuka'));

        $f = $this->findings($user);
        $this->assertSame($invalid, $f['geometri_tidak_sah'][0]['id_lot']);
        $this->assertStringContainsString('Self-intersection', $f['geometri_tidak_sah'][0]['perihal']);
        $this->assertStringContainsString('ST_MakeValid', $f['geometri_tidak_sah'][0]['cadangan']);
        $this->assertSame('/admin/pro/lot/'.$invalid, $f['geometri_tidak_sah'][0]['pautan']);
        $pair = [$f['pertindihan'][0]['id_lot'], $f['pertindihan'][0]['id_lot_lain']];
        sort($pair);
        $expected = [$a, $b];
        sort($expected);
        $this->assertSame($expected, $pair);
        $this->assertEqualsWithDelta(6150, $f['pertindihan'][0]['nilai'], 200);
        $this->assertSame($none, $f['tiada_geometri'][0]['id_lot']);
        $this->assertCount(1, $f['jurang']);
        $this->assertEqualsWithDelta(490, $f['jurang'][0]['nilai'], 30);

        $suggestion = $this->actingAs($user)->getJson('/api/v1/gis/kualiti/'.$f['geometri_tidak_sah'][0]['id'].'/cadangan')->assertOk();
        $this->assertNotNull($suggestion->json('cadangan'));
        $this->assertSame('/admin/pro/lot/'.$invalid, $suggestion->json('pautan_pembetulan'));

        $csv = $this->actingAs($user)->get('/api/v1/gis/kualiti/eksport?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Bowtie', $csv);
    }

    public function test_SRS_GIS_27_coordinates_outside_selangor_and_outside_mukim_beyond_tolerance(): void
    {
        $penang = $this->lot($this->square(100.30, 5.40), ['no_lot' => 'Pinang']);
        $wrongMukim = $this->lot($this->square(101.65, 3.10), ['no_lot' => 'Salah mukim', 'kod_mukim' => '100803']); // inside Dengkil, coded Damansara
        $edge = $this->lot($this->square(101.6001, 3.10, 0.0001), ['no_lot' => 'Tepi', 'kod_mukim' => '100803']);  // ~11 m over the line: within 50 m tolerance

        $user = $this->qa();
        $this->actingAs($user)->postJson('/api/v1/gis/kualiti/jalankan', ['semakan' => ['luar_selangor', 'luar_mukim']])->assertOk();
        $f = $this->findings($user);

        $this->assertSame([$penang], array_column($f['luar_selangor'], 'id_lot'));
        $this->assertContains($wrongMukim, array_column($f['luar_mukim'], 'id_lot'));
        $this->assertNotContains($edge, array_column($f['luar_mukim'], 'id_lot'));

        // A stricter MAIS tolerance flags the edge lot too.
        $this->params->values['gis.toleransi_ketepatan_m'] = 1;
        $this->actingAs($user)->postJson('/api/v1/gis/kualiti/jalankan', ['semakan' => ['luar_mukim']])->assertOk();
        $this->assertContains($edge, array_column($this->findings($user)['luar_mukim'], 'id_lot'));
    }

    public function test_SRS_GIS_28_rerun_closes_fixed_findings_and_status_workflow_is_scoped(): void
    {
        $a = $this->lot($this->square(101.56, 3.10));
        $this->lot($this->square(101.5605, 3.10));
        $sepang = $this->lot(null, ['kod_mukim' => '100901', 'no_lot' => 'Sepang tiada']);
        $user = $this->qa();
        $this->actingAs($user)->postJson('/api/v1/gis/kualiti/jalankan')->assertOk();
        $overlap = $this->findings($user)['pertindihan'][0];

        // Petaling officer: no Sepang findings, cannot touch them.
        $petaling = $this->qa(['1008']);
        $this->assertArrayNotHasKey('tiada_geometri', $this->findings($petaling));
        $sepangFinding = DB::table('gis_ralat_kualiti')->where('id_lot', $sepang)->value('id');
        $this->actingAs($petaling)->putJson("/api/v1/gis/kualiti/{$sepangFinding}", ['status_pembetulan' => 'diabaikan'])->assertNotFound();

        $this->actingAs($user)->putJson("/api/v1/gis/kualiti/{$overlap['id']}", ['status_pembetulan' => 'dalam_tindakan', 'catatan' => 'Semak pelan'])->assertOk();
        $this->assertAudited('kemas_kini', ['modul' => 'gis', 'nama_entiti' => 'gis_ralat_kualiti'])->assertFieldChanged('status_pembetulan', 'baharu', 'dalam_tindakan');

        // Officer fixes lot A in MOD-PRO (new geometry version) → next run closes the finding.
        $this->geometry($a, $this->square(101.58, 3.10));
        $this->travel(2)->seconds();
        $this->actingAs($user)->postJson('/api/v1/gis/kualiti/jalankan')->assertOk();
        $this->assertSame('selesai', DB::table('gis_ralat_kualiti')->where('id', $overlap['id'])->value('status_pembetulan'));

        $this->actingAs($this->user())->postJson('/api/v1/gis/kualiti/jalankan')->assertForbidden();
    }

    public function test_SRS_GIS_27_scheduled_command_scans_everything(): void
    {
        $this->lot(null, ['no_lot' => 'Tiada']);
        $this->artisan('gis:semak-kualiti')->assertSuccessful();
        $this->assertSame(1, DB::table('gis_ralat_kualiti')->where('jenis_ralat', 'tiada_geometri')->count());
    }

    public function test_SRS_GIS_30_geometry_versions_listed_and_compared(): void
    {
        $lot = $this->lot();
        $v1 = $this->geometry($lot, $this->square(101.55, 3.10, 0.001));
        $v2 = $this->geometry($lot, $this->square(101.55, 3.10, 0.0012));
        $user = $this->user();

        $versions = $this->actingAs($user)->getJson("/api/v1/gis/lot/{$lot}/versi")->assertOk()->json('data');
        $this->assertSame([2, 1], array_column($versions, 'versi'));
        $this->assertTrue($versions[0]['semasa']);
        $this->assertFalse($versions[1]['semasa']);

        $diff = $this->actingAs($user)->getJson("/api/v1/gis/lot/{$lot}/versi/banding?a={$v1}&b={$v2}")->assertOk();
        $this->assertGreaterThan(0, $diff->json('beza_luas_m2'));
        $this->assertEqualsWithDelta($diff->json('beza_luas_m2'), $diff->json('luas_ditambah_m2'), 1);
        $this->assertEqualsWithDelta(0, $diff->json('luas_dibuang_m2'), 0.01);
        $this->assertGreaterThan(20, $diff->json('hausdorff_m'));
        $this->assertFalse($diff->json('sama'));

        $this->actingAs($this->user(GisTestCase::PENGGUNA, ['1009']))->getJson("/api/v1/gis/lot/{$lot}/versi")->assertNotFound();
    }

    public function test_SRS_GIS_31_spatial_changes_audited_with_before_and_after(): void
    {
        $officer = $this->specialist();
        $id = $this->actingAs($officer)->postJson('/api/v1/gis/aset', ['nama' => 'Pam', 'kategori' => 'infrastruktur', 'lng' => 101.555, 'lat' => 3.105])->json('data.id');
        $before = DB::table('gis_aset_titik')->where('id', $id)->value('geom');
        $this->actingAs($officer)->withHeader('X-Sebab-Perubahan', rawurlencode('Pembetulan GPS'))->putJson("/api/v1/gis/aset/{$id}", ['lng' => 101.556, 'lat' => 3.106])->assertOk();

        $audit = $this->assertAudited('kemas_kini', ['modul' => 'gis', 'nama_entiti' => 'gis_aset_titik', 'id_rekod' => $id]);
        $this->assertSame('Pembetulan GPS', $audit->row->sebab_perubahan);
        $changed = $audit->changes->firstWhere('nama_medan', 'geom');
        $this->assertNotNull($changed);
        $this->assertSame($before, $changed->nilai_asal);
        $this->assertNotSame($before, $changed->nilai_baharu);
        $this->assertSame('POINT(101.556 3.106)', DB::selectOne('SELECT ST_AsText(?::geometry) AS t', [$changed->nilai_baharu])->t);
    }
}
