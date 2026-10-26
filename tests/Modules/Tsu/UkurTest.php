<?php

namespace Tests\Modules\Tsu;

use App\Models\User;
use App\Modules\Tsu\Gis\HasilUkurLayer;
use App\Modules\Tsu\Gis\UrusanTanahLayer;
use App\Modules\Tsu\Models\HasilUkur;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Models\LantikanJurukur;
use App\Modules\Tsu\Models\PanelJurukur;
use App\Modules\Tsu\Models\SebutHargaTawaran;
use App\Modules\Tsu\Models\SebutHargaUkur;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\AliranTsu;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use ZipArchive;

class UkurTest extends TsuTestCase
{
    /** @return list<PanelJurukur> */
    private function panel(int $n = 3): array
    {
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $out[] = PanelJurukur::query()->create(['nama_firma' => 'Firma '.$i, 'no_lesen_jurukur' => 'JTB-'.$i, 'nama_jurukur_bertanggungjawab' => 'Jurukur '.$i, 'pengalaman_tahun' => $i * 5, 'status_panel' => 'aktif', 'tarikh_tamat_lesen' => today()->addYear()->toDateString()]);
        }

        return $out;
    }

    /** Full procurement until an appointment exists. @return array{0: UrusanTanah, 1: LantikanJurukur, 2: User} */
    private function lantikan(?User $officer = null): array
    {
        $officer ??= $this->user(self::PEGAWAI);
        $u = $this->daftar($officer, 'pecah_sempadan', [$this->lot()]);
        $buyer = $this->user(self::PEROLEHAN);
        $p = $this->panel();
        $sh = $this->actingAs($buyer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/sebutharga', ['tarikh_tutup' => now()->addDays(7)->toIso8601String(), 'skop_kerja' => 'Ukur pecah sempadan 3 lot', 'jurukur_ids' => array_map(fn ($j) => $j->id, $p)])->assertCreated()->json('data');
        foreach (SebutHargaTawaran::query()->where('sebutharga_id', $sh['id'])->get() as $i => $t) {
            $this->actingAs($buyer)->postJson('/api/v1/tsu/sebutharga/'.$sh['id'].'/tawaran/'.$t->id, ['harga_tawaran' => (string) (20000 - $i * 1000), 'tempoh_siap_hari' => 60 + $i * 10])->assertOk();
        }
        $this->travel(8)->days();
        $rec = SebutHargaTawaran::query()->where('sebutharga_id', $sh['id'])->orderBy('id')->first();
        $this->actingAs($this->user(self::PENILAI))->postJson('/api/v1/tsu/sebutharga/'.$sh['id'].'/nilai', ['id_tawaran_disyorkan' => $rec->id, 'justifikasi_pemilihan' => 'Tempoh siap terpantas dan pengalaman memadai.'])->assertOk();
        $this->complete(AliranTsu::LANTIKAN, AliranTsu::SUBJEK_SEBUTHARGA, $sh['id'], 'lulus', $this->user([])->id, 'Lulus');
        $l = $this->actingAs($buyer)->postJson('/api/v1/tsu/sebutharga/'.$sh['id'].'/lantik', ['no_surat_setuju_terima' => 'SST-1', 'tarikh_mula' => today()->toDateString()])->assertCreated()->json('data.id');

        return [$u, LantikanJurukur::query()->findOrFail($l), $officer];
    }

    public function test_SRS_TSU_08_09_10_full_surveyor_procurement_cycle_with_segregation_of_duties(): void
    {
        $officer = $this->user([...self::PEGAWAI, ...self::PENILAI]);
        $u = $this->daftar($officer, 'pecah_sempadan', [$this->lot()]);
        $buyer = $this->user(self::PEROLEHAN);
        $p = $this->panel(4);
        $p[3]->forceFill(['tarikh_tamat_lesen' => today()->subDay()])->save();
        $url = '/api/v1/tsu/urusan/'.$u->id.'/sebutharga';
        $this->actingAs($officer)->postJson($url, [])->assertForbidden();
        $this->actingAs($buyer)->postJson($url, ['tarikh_tutup' => now()->addDays(7)->toIso8601String(), 'skop_kerja' => 'x', 'jurukur_ids' => [$p[0]->id, $p[1]->id]])->assertUnprocessable();
        $this->actingAs($buyer)->postJson($url, ['tarikh_tutup' => now()->addDays(7)->toIso8601String(), 'skop_kerja' => 'x', 'jurukur_ids' => [$p[0]->id, $p[1]->id, $p[3]->id]])->assertUnprocessable();
        $sh = $this->actingAs($buyer)->postJson($url, ['tarikh_tutup' => now()->addDays(7)->toIso8601String(), 'skop_kerja' => 'Ukur pecah sempadan', 'jurukur_ids' => [$p[0]->id, $p[1]->id, $p[2]->id]])->assertCreated();
        $id = $sh->json('data.id');
        $this->assertMatchesRegularExpression('#^TSU-SH/\d{4}/\d{4}$#', $sh->json('data.no_jemputan'));
        $this->assertCount(3, $sh->json('data.tawaran'));
        $this->assertNotNull(SebutHargaUkur::query()->find($id)->id_dokumen_jemputan, 'dokumen jemputan dijana ke DMS');
        $this->assertSame('application/pdf', $this->actingAs($buyer)->get('/api/v1/tsu/sebutharga/'.$id.'/jemputan.pdf')->assertOk()->headers->get('Content-Type'));

        $offers = SebutHargaTawaran::query()->where('sebutharga_id', $id)->orderBy('id')->get();
        $this->actingAs($buyer)->postJson('/api/v1/tsu/sebutharga/'.$id.'/tawaran/'.$offers[0]->id, ['harga_tawaran' => '15000.00', 'tempoh_siap_hari' => 90, 'pengalaman_tahun' => 10])->assertOk();
        $this->actingAs($buyer)->postJson('/api/v1/tsu/sebutharga/'.$id.'/tawaran/'.$offers[1]->id, ['harga_tawaran' => '12000.00', 'tempoh_siap_hari' => 60])->assertOk();
        // Price confidential before the closing date: encrypted at rest, hidden in API, no comparison.
        $this->assertNotSame('15000.00', DB::table('sebutharga_tawaran')->where('id', $offers[0]->id)->value('harga_tawaran'));
        $this->actingAs($buyer)->getJson('/api/v1/tsu/sebutharga/'.$id.'/perbandingan')->assertForbidden();
        $this->assertNull($this->actingAs($buyer)->getJson('/api/v1/tsu/urusan/'.$u->id.'/ukur')->assertOk()->json('data.sebutharga.0.tawaran.0.harga_tawaran'));
        $this->actingAs($this->user(self::PENILAI))->postJson('/api/v1/tsu/sebutharga/'.$id.'/nilai', ['id_tawaran_disyorkan' => $offers[1]->id, 'justifikasi_pemilihan' => 'Harga terendah dan siap terpantas.'])->assertUnprocessable();

        $this->travel(8)->days();
        $this->actingAs($buyer)->postJson('/api/v1/tsu/sebutharga/'.$id.'/tawaran/'.$offers[2]->id, ['harga_tawaran' => '9000', 'tempoh_siap_hari' => 30])->assertUnprocessable(); // late offer
        $table = $this->actingAs($buyer)->getJson('/api/v1/tsu/sebutharga/'.$id.'/perbandingan')->assertOk()->json('data.jadual');
        $this->assertSame([$offers[1]->id, $offers[0]->id, $offers[2]->id], array_column($table, 'id'), 'disusun mengikut markah');
        $this->assertSame(['12000.00', 60, 1], [$table[0]['harga_tawaran'], $table[0]['tempoh_siap_hari'], $table[0]['kedudukan']]);
        $this->assertNull($table[2]['markah'], 'tiada tawaran');

        // Segregation: the urusan registrar cannot evaluate.
        $this->actingAs($officer)->postJson('/api/v1/tsu/sebutharga/'.$id.'/nilai', ['id_tawaran_disyorkan' => $offers[1]->id, 'justifikasi_pemilihan' => 'Saya pendaftar urusan ini.'])->assertUnprocessable()->assertJsonValidationErrors('id_penilai');
        $this->actingAs($this->user(self::PENILAI))->postJson('/api/v1/tsu/sebutharga/'.$id.'/nilai', ['id_tawaran_disyorkan' => $offers[1]->id, 'justifikasi_pemilihan' => 'Harga terendah dan tempoh siap terpantas.'])->assertOk()->assertJsonPath('data.status', 'menunggu_kelulusan');
        $sh = SebutHargaUkur::query()->findOrFail($id);
        $this->assertSame(['sebutharga_ukur', $id], [$this->workflow->of(AliranTsu::LANTIKAN)[0]['subjectType'], $this->workflow->of(AliranTsu::LANTIKAN)[0]['subjectId']]);
        $this->assertTrue((bool) SebutHargaTawaran::query()->find($offers[1]->id)->indikator_disyorkan);
        $this->assertSame('tidak_respons', SebutHargaTawaran::query()->find($offers[2]->id)->status);
        $this->actingAs($buyer)->postJson('/api/v1/tsu/sebutharga/'.$id.'/lantik', ['no_surat_setuju_terima' => 'SST-1', 'tarikh_mula' => today()->toDateString()])->assertUnprocessable();

        // Evaluator approving their own recommendation → recorded as rejected.
        $this->complete(AliranTsu::LANTIKAN, AliranTsu::SUBJEK_SEBUTHARGA, $id, 'lulus', $sh->id_penilai, 'Saya lulus');
        $this->assertSame('ditolak', $sh->refresh()->status);
        $this->actingAs($this->user(self::PENILAI))->postJson('/api/v1/tsu/sebutharga/'.$id.'/nilai', ['id_tawaran_disyorkan' => $offers[1]->id, 'justifikasi_pemilihan' => 'Dinilai semula: harga terendah.'])->assertOk();
        $this->complete(AliranTsu::LANTIKAN, AliranTsu::SUBJEK_SEBUTHARGA, $id, 'lulus', $this->user([])->id, 'Lulus');
        $this->assertSame('diluluskan', $sh->refresh()->status);

        $l = $this->actingAs($buyer)->postJson('/api/v1/tsu/sebutharga/'.$id.'/lantik', ['no_surat_setuju_terima' => 'SST/TSU/2026/01', 'tarikh_mula' => today()->toDateString()])->assertCreated();
        $this->assertSame(['JTB-2', 'SST/TSU/2026/01', '12000.00', 60, today()->addDays(60)->toDateString()], [$l->json('data.jurukur.no_lesen_jurukur'), $l->json('data.no_surat_setuju_terima'), $l->json('data.nilai_kontrak'), $l->json('data.tempoh_siap_hari'), $l->json('data.tarikh_jangka_siap')]);
        $this->actingAs($buyer)->postJson('/api/v1/tsu/sebutharga/'.$id.'/lantik', ['no_surat_setuju_terima' => 'SST-2', 'tarikh_mula' => today()->toDateString()])->assertUnprocessable();
    }

    public function test_SRS_TSU_11_progress_lateness_notifies_and_external_surveyor_only_reports_own_appointment(): void
    {
        [$u, $l, $officer] = $this->lantikan();
        $ketua = $this->user([]);
        $this->directory->supervisors[$officer->id] = [$ketua->id];
        $surveyor = $this->user(['tsu.jurukur.kemas_kini']);
        $other = $this->user(['tsu.jurukur.kemas_kini']);
        $l->jurukur->forceFill(['id_pengguna' => $surveyor->id])->save();

        $this->actingAs($surveyor)->getJson('/api/v1/tsu/jurukur/lantikan')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($other)->getJson('/api/v1/tsu/jurukur/lantikan')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($other)->postJson('/api/v1/tsu/lantikan/'.$l->id.'/kemajuan', ['milestone' => 'x', 'peratus_siap' => 10])->assertForbidden();
        $this->actingAs($surveyor)->getJson('/api/v1/tsu/urusan/'.$u->id)->assertForbidden();

        $this->actingAs($surveyor)->postJson('/api/v1/tsu/lantikan/'.$l->id.'/kemajuan', ['milestone' => 'Kerja lapangan', 'peratus_siap' => 10])->assertCreated()->assertJsonPath('data.lewat', false);
        $this->travel(45)->days(); // 75 % expected of 60 days
        $this->actingAs($surveyor)->postJson('/api/v1/tsu/lantikan/'.$l->id.'/kemajuan', ['milestone' => 'Pengiraan', 'peratus_siap' => 5])->assertUnprocessable();
        $this->actingAs($surveyor)->postJson('/api/v1/tsu/lantikan/'.$l->id.'/kemajuan', ['milestone' => 'Pengiraan', 'peratus_siap' => 40])->assertCreated()->assertJsonPath('data.lewat', true);
        $late = $this->notifier->of('tsu.ukur_lewat');
        $this->assertCount(1, $late);
        $this->assertEqualsCanonicalizing([$officer->id, $ketua->id], $late[0]['to']);
        $this->assertTrue($l->refresh()->lewat);
        $this->assertSame(40, $l->peratus_siap_semasa);
    }

    public function test_SRS_TSU_11_daily_check_flags_appointment_without_progress(): void
    {
        [, $l] = $this->lantikan();
        $this->travel(30)->days();
        $this->artisan('tsu:semak-tempoh', ['--tanpa-ptd' => true])->assertSuccessful();
        $this->assertTrue($l->refresh()->lewat);
        $this->assertCount(1, $this->notifier->of('tsu.ukur_lewat'));
    }

    public function test_SRS_TSU_16_imports_geojson_dxf_and_shapefile_with_crs_validation(): void
    {
        $gdal = new Process([(string) config('gis.ogr2ogr'), '--version']);
        $gdal->run();
        if (! $gdal->isSuccessful()) {
            $this->markTestSkipped('GDAL ogr2ogr tidak dipasang.');
        }

        [$u, $l, $officer] = $this->lantikan();
        $gis = $this->user(self::GIS);
        [$lat, $lng] = self::PUSAT_PETALING;
        $url = '/api/v1/tsu/urusan/'.$u->id.'/hasil-ukur';

        // GeoJSON (WGS84), declared area.
        $res = $this->actingAs($gis)->postJson($url, ['format_fail' => 'geojson', 'sistem_koordinat' => 4326, 'geojson' => $this->petak($lat, $lng, 0.0005), 'keluasan_dokumen' => 12000, 'no_lot_cadangan' => 'PT 501', 'lantikan_id' => $l->id])->assertCreated();
        $h = $res->json('data.0');
        $this->assertEqualsWithDelta(12300, (float) $h['keluasan_dikira'], 400);
        $this->assertSame(4326, $h['sistem_koordinat']);
        // GeoJSON whose crs member disagrees with the declared one → rejected.
        $bad = json_decode($this->petak($lat, $lng, 0.0005), true) + ['crs' => ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:EPSG::3380']]];
        $this->actingAs($gis)->postJson($url, ['format_fail' => 'geojson', 'sistem_koordinat' => 4326, 'geojson' => json_encode($bad)])->assertUnprocessable()->assertJsonValidationErrors('sistem_koordinat');
        // Wrong declared SRID puts the polygon outside Malaysia → rejected.
        $this->actingAs($gis)->postJson($url, ['format_fail' => 'geojson', 'sistem_koordinat' => 3375, 'geojson' => $this->petak($lat, $lng, 0.0005)])->assertUnprocessable()->assertJsonValidationErrors('sistem_koordinat');
        $this->actingAs($gis)->postJson($url, ['format_fail' => 'geojson', 'sistem_koordinat' => 2000, 'geojson' => $this->petak($lat, $lng, 0.0005)])->assertUnprocessable();

        // DXF in GDM2000 / Selangor Cassini (EPSG:3380): two closed LWPOLYLINEs = two new lots.
        $rings = [];
        foreach ([[-0.0005, 0], [0.0005, 0]] as [$dx]) {
            $pts = DB::select('SELECT ST_X(g) x, ST_Y(g) y FROM (SELECT (ST_DumpPoints(ST_Transform(ST_SetSRID(ST_MakeEnvelope(?, ?, ?, ?), 4326), 3380))).geom g) s', [$lng + $dx - 0.0004, $lat - 0.0004, $lng + $dx + 0.0004, $lat + 0.0004]);
            $rings[] = array_slice($pts, 0, 4);
        }
        $dxf = "0\nSECTION\n2\nENTITIES\n";
        foreach ($rings as $i => $ring) {
            $dxf .= "0\nLWPOLYLINE\n8\nPT 60".($i + 1)."\n90\n4\n70\n1\n";
            foreach ($ring as $pt) {
                $dxf .= "10\n".$pt->x."\n20\n".$pt->y."\n";
            }
        }
        $dxf .= "0\nLINE\n8\nX\n10\n0\n20\n0\n0\nENDSEC\n0\nEOF\n";
        $file = UploadedFile::fake()->createWithContent('ukur.dxf', $dxf);
        $res = $this->actingAs($gis)->post($url, ['format_fail' => 'dxf', 'sistem_koordinat' => 3380, 'fail' => $file], ['Accept' => 'application/json'])->assertCreated();
        $this->assertCount(2, $res->json('data'));
        $this->assertSame(['PT 601', 'PT 602'], array_column($res->json('data'), 'no_lot_cadangan'));
        $this->assertEqualsWithDelta(7860, (float) $res->json('data.0.keluasan_dikira'), 300);
        $this->actingAs($gis)->post($url, ['format_fail' => 'dxf', 'sistem_koordinat' => 3380, 'fail' => UploadedFile::fake()->createWithContent('kosong.dxf', "0\nSECTION\n2\nENTITIES\n0\nENDSEC\n")], ['Accept' => 'application/json'])->assertUnprocessable();

        // Zipped Shapefile with .prj (EPSG:3380) via GDAL.
        $zip = $this->shapefile($this->petak($lat, $lng, 0.0004), 3380);
        $this->actingAs($gis)->post($url, ['format_fail' => 'shp', 'sistem_koordinat' => 3375, 'fail' => new UploadedFile($zip, 'ukur.zip', 'application/zip', null, true)], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('sistem_koordinat');
        $zip = $this->shapefile($this->petak($lat, $lng, 0.0004), 3380);
        $res = $this->actingAs($gis)->post($url, ['format_fail' => 'shp', 'sistem_koordinat' => 3380, 'fail' => new UploadedFile($zip, 'ukur.zip', 'application/zip', null, true), 'keluasan_dokumen' => 7850], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('shp', $res->json('data.0.format_fail'));
        $this->assertTrue($res->json('data.0.dalam_toleransi'));
        $this->assertSame(4, HasilUkur::query()->where('urusan_id', $u->id)->count());
        $this->assertNotEmpty($this->notifier->of('tsu.hasil_ukur_untuk_pengesahan'));
        $this->actingAs($officer)->postJson($url, ['format_fail' => 'geojson', 'sistem_koordinat' => 4326, 'geojson' => $this->petak($lat, $lng, 0.0005)])->assertForbidden();
    }

    private function shapefile(string $geojson, int $srid): string
    {
        $dir = storage_path('app/tmp/tsu-test-'.uniqid());
        @mkdir($dir, 0700, true);
        file_put_contents($dir.'/in.geojson', $geojson);
        $p = new Process([(string) config('gis.ogr2ogr'), '-f', 'ESRI Shapefile', '-t_srs', 'EPSG:'.$srid, $dir.'/ukur.shp', $dir.'/in.geojson']);
        $p->run();
        $this->assertTrue($p->isSuccessful(), $p->getErrorOutput());
        $zipPath = $dir.'.zip';
        $z = new ZipArchive;
        $z->open($zipPath, ZipArchive::CREATE);
        foreach (glob($dir.'/ukur.*') ?: [] as $f) {
            $z->addFile($f, basename($f));
        }
        $z->close();
        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);
        $this->beforeApplicationDestroyed(fn () => @unlink($zipPath));

        return $zipPath;
    }

    public function test_SRS_TSU_17_side_by_side_comparison_area_tolerance_and_verification_rules(): void
    {
        [$u, $l, $officer] = $this->lantikan();
        $gis = $this->user(self::GIS);
        $gis2 = $this->user(self::GIS);
        [$lat, $lng] = self::PUSAT_PETALING;
        $in = $this->actingAs($gis)->postJson('/api/v1/tsu/urusan/'.$u->id.'/hasil-ukur', ['format_fail' => 'geojson', 'sistem_koordinat' => 4326, 'geojson' => $this->petak($lat, $lng, 0.0005), 'keluasan_dokumen' => 10000, 'no_lot_cadangan' => 'PT 1', 'lantikan_id' => $l->id])->assertCreated()->json('data.0');
        $this->assertGreaterThan(5, $in['sisihan_peratus']);
        $this->assertFalse($in['dalam_toleransi']);

        $cmp = $this->actingAs($officer)->getJson('/api/v1/tsu/hasil-ukur/'.$in['id'].'/banding')->assertOk()->json('data');
        $this->assertSame('Feature', $cmp['baharu']['type']);
        $this->assertSame('Feature', $cmp['asal']['type'], 'sempadan asal untuk paparan sebelah-menyebelah');
        $this->assertEqualsWithDelta(49200, (float) $cmp['keluasan_asal_dikira'], 1500);
        $this->assertEqualsWithDelta(25.0, (float) $cmp['peratus_bertindih_asal'], 2.0);
        $this->assertEquals(5, $cmp['toleransi_peratus']);

        $sah = '/api/v1/tsu/hasil-ukur/'.$in['id'].'/sahkan';
        $this->actingAs($officer)->postJson($sah, ['status_pengesahan' => 'diterima'])->assertForbidden();
        $this->actingAs($gis)->postJson($sah, ['status_pengesahan' => 'diterima', 'catatan' => 'x'])->assertUnprocessable(); // own import
        $this->actingAs($gis2)->postJson($sah, ['status_pengesahan' => 'diterima'])->assertUnprocessable()->assertJsonValidationErrors('catatan');
        $this->dokumen($officer, $u, 'pelan_akui')->assertCreated();
        $pa = DB::table('urusan_dokumen')->where('urusan_id', $u->id)->where('kategori_dokumen', 'pelan_akui')->value('id_dokumen_dms');
        $this->actingAs($gis2)->postJson($sah, ['status_pengesahan' => 'diterima', 'catatan' => 'Sisihan disebabkan pelan lama tidak tepat; disahkan JUPEM.', 'id_dokumen_pelan_akui' => $pa])->assertOk()->assertJsonPath('data.status_pengesahan', 'diterima');
        $this->actingAs($gis2)->postJson($sah, ['status_pengesahan' => 'ditolak'])->assertUnprocessable();
        $this->assertDatabaseHas('urusan_keputusan', ['urusan_id' => $u->id, 'konteks' => 'hasil_ukur', 'jenis_keputusan' => 'diterima', 'id_dokumen_kelulusan' => $pa]);
    }

    public function test_SRS_TSU_12_13_survey_status_and_acceptance_records_fee_invoice_for_payment(): void
    {
        [$u, $l] = $this->lantikan();
        $gis = $this->user(self::GIS);
        [$lat, $lng] = self::PUSAT_PETALING;
        $h = $this->actingAs($gis)->postJson('/api/v1/tsu/urusan/'.$u->id.'/hasil-ukur', ['format_fail' => 'geojson', 'sistem_koordinat' => 4326, 'geojson' => $this->petak($lat, $lng, 0.0005), 'no_lot_cadangan' => 'PT 9', 'lantikan_id' => $l->id])->json('data.0.id');
        $this->actingAs($this->user(self::GIS))->postJson('/api/v1/tsu/hasil-ukur/'.$h.'/sahkan', ['status_pengesahan' => 'diterima'])->assertOk();
        $l->refresh();
        $this->assertSame(['siap', 100], [$l->status, $l->peratus_siap_semasa]);
        $this->assertNotNull($l->tarikh_siap);
        $fee = KosUrusan::query()->where('urusan_id', $u->id)->where('kategori_kos', 'upah_ukur')->sole();
        $this->assertSame(['20000.00', 'belum_bayar'], [(string) $fee->amaun, $fee->status_bayaran]);
        $this->assertSame('kerja_ukur', $fee->peringkat->kod);
        $ukur = $this->actingAs($this->user(self::PEGAWAI))->getJson('/api/v1/tsu/urusan/'.$u->id.'/ukur')->assertOk()->json('data');
        $this->assertSame('siap', $ukur['lantikan'][0]['status']);
        $this->assertSame('diterima', $ukur['hasil'][0]['status_pengesahan']);
        $this->assertSame('20000.00', $ukur['lantikan'][0]['nilai_kontrak']);
    }

    public function test_SRS_TSU_18_survey_results_and_active_urusan_appear_on_gis_layers_within_scope(): void
    {
        [$u, $l] = $this->lantikan($this->user(self::PEGAWAI, ['1008']));
        [$lat, $lng] = self::PUSAT_PETALING;
        $this->actingAs($this->user(self::GIS))->postJson('/api/v1/tsu/urusan/'.$u->id.'/hasil-ukur', ['format_fail' => 'geojson', 'sistem_koordinat' => 4326, 'geojson' => $this->petak($lat, $lng, 0.0005), 'lantikan_id' => $l->id])->assertCreated();
        $viewer = $this->user(['tsu.peta.lihat'], ['1008']);
        $outside = $this->user(['tsu.peta.lihat'], ['1009']);
        $hasil = app(HasilUkurLayer::class)->features($viewer);
        $this->assertCount(1, $hasil['features']);
        $this->assertSame('menunggu', $hasil['features'][0]['properties']['status_pengesahan']);
        $this->assertSame([], app(HasilUkurLayer::class)->features($outside)['features']);
        $this->assertSame([], app(HasilUkurLayer::class)->features($this->user([]))['features']);
        $urusan = app(UrusanTanahLayer::class)->features($viewer, [$lng - 0.01, $lat - 0.01, $lng + 0.01, $lat + 0.01]);
        $this->assertCount(1, $urusan['features']);
        $this->assertSame('pecah_sempadan', $urusan['features'][0]['properties']['jenis_urusan']);
        $this->assertSame('/admin/tsu/urusan/'.$u->id, $urusan['features'][0]['properties']['pautan']);
        $this->assertSame([], app(UrusanTanahLayer::class)->features($outside)['features']);
        $this->assertCount(7, app(UrusanTanahLayer::class)->legend()['entri']);
        $keys = collect($this->actingAs($this->user(['tsu.peta.lihat', 'gis.peta.lihat'], ['1008']))->getJson('/api/v1/gis/lapisan')->assertOk()->json('lapisan'))->pluck('kod')->all();
        $this->assertContains('tsu.urusan', $keys);
        $this->assertContains('tsu.hasil_ukur', $keys);
    }
}
