<?php

namespace Tests\Modules\Dms;

use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenKategori;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Modules\Dms\Support\TestFiles;

class UploadValidationTest extends DmsTestCase
{
    public function test_SRS_DMS_03_default_whitelist_accepts_every_listed_type(): void
    {
        $user = $this->userWith();
        $files = [
            TestFiles::pdf(), TestFiles::png(), TestFiles::jpg(), TestFiles::jpg('foto.jpeg'),
            TestFiles::fromString("II*\x00".str_repeat("\x00", 100), 'imbasan.tiff'),
            TestFiles::docx(),
            TestFiles::zip('jadual.xlsx', ['[Content_Types].xml' => '<Types/>', 'xl/workbook.xml' => '<workbook/>', 'xl/sharedStrings.xml' => '<sst><si><t>Cukai</t></si></sst>']),
            TestFiles::zip('slaid.pptx', ['[Content_Types].xml' => '<Types/>', 'ppt/presentation.xml' => '<p/>']),
            TestFiles::dwg(), TestFiles::shp(), TestFiles::shapefileZip(), TestFiles::kml(), TestFiles::kmz(), TestFiles::geojson(),
        ];

        $response = $this->upload($user, $files, ['kategori' => 'fail_gis'])->assertCreated();

        $this->assertSame(count($files), $response->json('diterima'), json_encode($response->json('keputusan')));
        $this->assertSame(0, $response->json('ditolak'));
    }

    public function test_SRS_DMS_03_type_removed_from_whitelist_by_admin_is_rejected(): void
    {
        $user = $this->userWith();
        $this->params->set('dms.senarai_putih', ['pdf']);

        $this->upload($user, TestFiles::png())
            ->assertStatus(422)
            ->assertJsonPath('keputusan.0.sebab', 'tidak_dibenarkan')
            ->assertJsonPath('keputusan.0.mesej', fn ($m) => str_contains($m, 'tidak dibenarkan') && str_contains($m, 'PDF'));
    }

    public function test_SRS_DMS_03_category_can_narrow_allowed_types(): void
    {
        $user = $this->userWith();

        // Seeded "gambar_tapak" accepts images only.
        $this->upload($user, TestFiles::pdf(), ['kategori' => 'gambar_tapak'])
            ->assertStatus(422)
            ->assertJsonPath('keputusan.0.sebab', 'tidak_dibenarkan_kategori');
        $this->upload($user, TestFiles::png(), ['kategori' => 'gambar_tapak'])->assertCreated();
    }

    public function test_SRS_DMS_04_renamed_file_is_rejected_by_content_signature(): void
    {
        $user = $this->userWith();

        // Acceptance 1: a PNG renamed to .pdf, an EXE renamed to .pdf, a text file renamed to .docx.
        foreach ([
            TestFiles::fromString((string) TestFiles::png()->getContent(), 'laporan.pdf'),
            TestFiles::fromString('plain text pretending', 'surat.docx'),
            TestFiles::fromString('%PDF-1.4 fake', 'gambar.png'),
            TestFiles::fromString('{"not":"geo"}', 'lot.geojson'),
            TestFiles::fromString('<html><body>hi</body></html>', 'peta.kml'),
            TestFiles::zip('peta.kmz', ['readme.txt' => 'no kml here']),
        ] as $file) {
            $this->upload($user, $file)
                ->assertStatus(422)
                ->assertJsonPath('keputusan.0.sebab', 'tidak_sepadan');
        }

        $this->upload($user, TestFiles::fromString((string) TestFiles::exe()->getContent(), 'laporan.pdf'))
            ->assertStatus(422)
            ->assertJsonPath('keputusan.0.sebab', 'kandungan_boleh_laksana');

        $this->assertSame(0, Dokumen::query()->count());
        $this->assertSame([], Storage::disk('dokumen')->allFiles());
    }

    public function test_SRS_DMS_09_executables_scripts_macro_documents_and_extensionless_files_are_blocked(): void
    {
        $user = $this->userWith();

        foreach (['setup.exe', 'run.com', 'a.bat', 'a.cmd', 'deploy.sh', 'pakej.msi', 'skrip.js', 'skrip.vbs', 'skrip.ps1', 'makro.docm', 'makro.xlsm', 'makro.pptm'] as $name) {
            $this->upload($user, TestFiles::fromString('anything', $name))
                ->assertStatus(422)
                ->assertJsonPath('keputusan.0.sebab', 'disekat')
                ->assertJsonPath('keputusan.0.mesej', fn ($m) => str_contains($m, 'disekat'));
        }

        $this->upload($user, TestFiles::fromString('%PDF-1.4', 'tanpasambungan'))
            ->assertStatus(422)
            ->assertJsonPath('keputusan.0.sebab', 'tiada_sambungan');

        // Double extension: the real (last) extension counts.
        $this->upload($user, TestFiles::exe('laporan.pdf.exe'))->assertJsonPath('keputusan.0.sebab', 'disekat');
    }

    public function test_SRS_DMS_09_macro_inside_a_docx_named_file_is_detected(): void
    {
        $user = $this->userWith();

        $this->upload($user, TestFiles::docx(macro: true))
            ->assertStatus(422)
            ->assertJsonPath('keputusan.0.sebab', 'bermakro');
        $this->upload($user, TestFiles::docx(extraParts: ['word/vbaProject.bin' => 'x']))
            ->assertJsonPath('keputusan.0.sebab', 'bermakro');
    }

    public function test_SRS_DMS_09_archives_nested_more_than_one_level_are_blocked(): void
    {
        $user = $this->userWith();

        // One level (an embedded workbook inside a DOCX) is legitimate.
        $embedded = TestFiles::zipString(['[Content_Types].xml' => '<Types/>', 'xl/workbook.xml' => '<w/>']);
        $this->upload($user, TestFiles::docx(extraParts: ['word/embeddings/Lampiran.xlsx' => $embedded]))->assertCreated();

        // Two levels: archive inside an archive inside the DOCX.
        $deep = TestFiles::zipString(['dalam.zip' => TestFiles::zipString(['a.txt' => 'x'])]);
        $this->upload($user, TestFiles::docx(extraParts: ['word/embeddings/arkib.zip' => $deep]))
            ->assertStatus(422)
            ->assertJsonPath('keputusan.0.sebab', 'arkib_bersarang');

        // Shapefile bundles may not contain archives at all, nor executables.
        $this->upload($user, TestFiles::shapefileZip(extra: ['lagi.zip' => TestFiles::zipString(['x.shp' => 'x'])]), ['kategori' => 'fail_gis'])
            ->assertJsonPath('keputusan.0.sebab', 'arkib_bersarang');
        $this->upload($user, TestFiles::kmz('x.kmz'), ['kategori' => 'fail_gis'])->assertCreated();
        $this->upload($user, TestFiles::zip('peta.kmz', ['doc.kml' => (string) TestFiles::kml()->getContent(), 'virus.exe' => 'MZ']), ['kategori' => 'fail_gis'])
            ->assertJsonPath('keputusan.0.sebab', 'disekat');
    }

    public function test_SRS_DMS_03_shapefile_zip_must_be_a_complete_shapefile_set(): void
    {
        $user = $this->userWith();

        $this->upload($user, TestFiles::shapefileZip(complete: false), ['kategori' => 'fail_gis'])
            ->assertJsonPath('keputusan.0.sebab', 'shp_tidak_lengkap');
        $this->upload($user, TestFiles::shapefileZip(extra: ['nota.docx' => 'x']), ['kategori' => 'fail_gis'])
            ->assertJsonPath('keputusan.0.sebab', 'arkib_bersarang');
        $this->upload($user, TestFiles::zip('dokumen.zip', ['a.pdf' => '%PDF-1.4']), ['kategori' => 'fail_gis'])
            ->assertJsonPath('keputusan.0.sebab', 'shp_tidak_lengkap');
    }

    public function test_SRS_DMS_05_category_size_limit_with_clear_message_and_admin_change_without_release(): void
    {
        $user = $this->userWith();

        // Default 25 MB for office/images.
        $this->upload($user, TestFiles::sized(26 * 1048576))
            ->assertStatus(422)
            ->assertJsonPath('keputusan.0.sebab', 'terlalu_besar')
            ->assertJsonPath('keputusan.0.mesej', fn ($m) => str_contains($m, '25 MB') && str_contains($m, 'Laporan'));

        // Acceptance 3: the Application Administrator raises the category limit — no code release.
        DokumenKategori::query()->where('kod', 'laporan')->update(['had_saiz_mb' => 30]);
        $this->upload($user, TestFiles::sized(26 * 1048576))->assertCreated();

        // And through the API.
        $admin = $this->userWith(['dms.dokumen.lihat', 'dms.konfigurasi.kemas_kini']);
        $kategori = DokumenKategori::query()->where('kod', 'laporan')->firstOrFail();
        $this->actingAs($admin)->putJson("/api/v1/dms/kategori/{$kategori->id}", ['had_saiz_mb' => 10])->assertOk();
        $this->upload($user, TestFiles::sized(11 * 1048576))
            ->assertJsonPath('keputusan.0.mesej', fn ($m) => str_contains($m, '10 MB'));
    }

    public function test_SRS_DMS_05_spatial_files_get_the_larger_default_limit(): void
    {
        $user = $this->userWith();
        $this->params->set('dms.had_saiz_spatial_mb', 1);
        $this->params->set('dms.had_saiz_pejabat_mb', 50);

        $big = TestFiles::fromString(str_pad('AC1032', 2 * 1048576, "\x00"), 'pelan.dwg');
        $this->upload($user, $big, ['kategori' => 'fail_gis'])
            ->assertJsonPath('keputusan.0.sebab', 'terlalu_besar')
            ->assertJsonPath('keputusan.0.mesej', fn ($m) => str_contains($m, '1 MB'));
    }

    public function test_SRS_DMS_06_cumulative_transaction_limit_across_requests(): void
    {
        $user = $this->userWith();
        $this->params->set('dms.had_transaksi_mb', 2);
        $txn = (string) Str::uuid();

        $this->upload($user, TestFiles::sized(1048576 + 1000, 'a.pdf'), ['id_transaksi' => $txn])->assertCreated();
        $this->upload($user, TestFiles::sized(1048576 + 1000, 'b.pdf'), ['id_transaksi' => $txn])
            ->assertStatus(422)
            ->assertJsonPath('keputusan.0.sebab', 'had_transaksi')
            ->assertJsonPath('keputusan.0.mesej', fn ($m) => str_contains($m, '2 MB'));

        // Same limit inside one multi-file request.
        $this->upload($user, [TestFiles::sized(1048576 + 1000, 'c.pdf'), TestFiles::sized(1048576 + 1000, 'd.pdf')])
            ->assertCreated()
            ->assertJsonPath('diterima', 1)
            ->assertJsonPath('keputusan.1.sebab', 'had_transaksi');
    }

    public function test_SRS_DMS_02_batch_upload_of_twenty_files_reports_each_file(): void
    {
        $user = $this->userWith();
        $files = [];
        for ($i = 1; $i <= 19; $i++) {
            $files[] = TestFiles::pdf("Dokumen nombor {$i}", "dokumen-{$i}.pdf");
        }
        $files[] = TestFiles::exe();

        $response = $this->upload($user, $files)->assertCreated();

        $response->assertJsonPath('diterima', 19)->assertJsonPath('ditolak', 1);
        $this->assertCount(20, $response->json('keputusan'));
        $this->assertSame('ditolak', $response->json('keputusan.19.status'));
        $this->assertSame(19, Dokumen::query()->count());
    }

    public function test_SRS_DMS_02_batch_limit_is_enforced(): void
    {
        $user = $this->userWith();
        $this->params->set('dms.had_fail_kelompok', 20);
        $files = array_map(fn ($i) => TestFiles::pdf('x', "f{$i}.pdf"), range(1, 21));

        $this->upload($user, $files)->assertStatus(422)->assertJsonValidationErrors('fail');
    }
}
