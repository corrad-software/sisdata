<?php

namespace Tests\Modules\Dms;

use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Services\Access\DocumentAccess;
use App\Modules\Dms\Services\Text\TextExtractor;
use App\Modules\Rbc\Contracts\AccessControl;
use Tests\Modules\Dms\Support\TestFiles;

class AccessAndSearchTest extends DmsTestCase
{
    private function makeDoc(string $classification, string $text, ?string $daerah = null): Dokumen
    {
        $uploader = $this->userWith([...self::PEGAWAI, 'dms.sulit.lihat', 'dms.rahsia.lihat']);
        $doc = $this->uploadDoc($uploader, TestFiles::pdf($text.' - dokumen ujian sistem pengurusan dokumen digital'), array_filter([
            'klasifikasi' => $classification, 'kod_daerah' => $daerah, 'tajuk' => "Dokumen {$classification}",
        ]));

        return $this->index($doc);
    }

    public function test_SRS_DMS_19_sulit_and_rahsia_need_explicit_permission(): void
    {
        $dalaman = $this->makeDoc('dalaman', 'biasa');
        $sulit = $this->makeDoc('sulit', 'sulit');
        $rahsia = $this->makeDoc('rahsia', 'rahsia');

        $officer = $this->userWith(['dms.dokumen.lihat']);
        $this->actingAs($officer)->getJson("/api/v1/dms/dokumen/{$dalaman->id}")->assertOk();
        $this->actingAs($officer)->getJson("/api/v1/dms/dokumen/{$sulit->id}")->assertNotFound();
        $this->actingAs($officer)->getJson("/api/v1/dms/dokumen/{$rahsia->id}")->assertNotFound();
        $this->actingAs($officer)->postJson("/api/v1/dms/dokumen/{$sulit->id}/pautan", ['jenis' => 'muat_turun'])->assertNotFound();

        // Per-role permission (RBC): Sulit only.
        $this->acl->grant($officer, 'dms.sulit.lihat');
        $this->actingAs($officer)->getJson("/api/v1/dms/dokumen/{$sulit->id}")->assertOk();
        $this->actingAs($officer)->getJson("/api/v1/dms/dokumen/{$rahsia->id}")->assertNotFound();

        // Per-user explicit grant on one Rahsia document.
        $owner = Dokumen::query()->find($rahsia->id)->pencipta;
        $this->actingAs($owner)->postJson("/api/v1/dms/dokumen/{$rahsia->id}/kebenaran", ['jenis_penerima' => 'pengguna', 'penerima' => (string) $officer->id])->assertCreated();
        $this->actingAs($officer)->getJson("/api/v1/dms/dokumen/{$rahsia->id}")->assertOk();

        // Per-role explicit grant.
        $legal = $this->userWith(['dms.dokumen.lihat']);
        $this->acl->roles[$legal->id] = ['pegawai_undang'];
        $this->actingAs($legal)->getJson("/api/v1/dms/dokumen/{$rahsia->id}")->assertNotFound();
        $this->actingAs($owner)->postJson("/api/v1/dms/dokumen/{$rahsia->id}/kebenaran", ['jenis_penerima' => 'peranan', 'penerima' => 'pegawai_undang'])->assertCreated();
        $this->actingAs($legal)->getJson("/api/v1/dms/dokumen/{$rahsia->id}")->assertOk();

        // Expired grants stop working; grants only apply to restricted documents.
        $temp = $this->userWith(['dms.dokumen.lihat']);
        $rahsia->kebenaran()->create(['jenis_penerima' => 'pengguna', 'penerima' => (string) $temp->id, 'tamat_pada' => now()->subMinute()]);
        $this->actingAs($temp)->getJson("/api/v1/dms/dokumen/{$rahsia->id}")->assertNotFound();
        $this->actingAs($dalaman->pencipta)->postJson("/api/v1/dms/dokumen/{$dalaman->id}/kebenaran", ['jenis_penerima' => 'pengguna', 'penerima' => (string) $temp->id])->assertStatus(422);

        // Someone who is neither uploader nor holds dms.kebenaran.kemas_kini can't grant.
        $this->actingAs($officer)->postJson("/api/v1/dms/dokumen/{$sulit->id}/kebenaran", ['jenis_penerima' => 'pengguna', 'penerima' => (string) $temp->id])->assertForbidden();
    }

    public function test_SRS_DMS_19_restricted_upload_and_reclassification_need_the_clearance(): void
    {
        $officer = $this->userWith();
        $this->upload($officer, TestFiles::pdf(), ['klasifikasi' => 'rahsia'])->assertForbidden();

        $doc = $this->uploadDoc($officer);
        $this->actingAs($officer)->putJson("/api/v1/dms/dokumen/{$doc->id}", ['klasifikasi' => 'sulit'])->assertForbidden();
        $this->acl->grant($officer, 'dms.sulit.lihat');
        $this->actingAs($officer)->putJson("/api/v1/dms/dokumen/{$doc->id}", ['klasifikasi' => 'sulit'])->assertOk()->assertJsonPath('data.klasifikasi', 'sulit');
    }

    public function test_SRS_DMS_13_search_results_and_counts_exclude_documents_out_of_scope(): void
    {
        $this->makeDoc('dalaman', 'Geran tanah wakaf Selangor', '10');
        $this->makeDoc('dalaman', 'Geran tanah wakaf Petaling', '11');
        $this->makeDoc('sulit', 'Geran tanah wakaf sulit', '10');
        $this->makeDoc('rahsia', 'Geran tanah wakaf rahsia', '10');

        $officer = $this->userWith(['dms.dokumen.lihat']);
        $this->acl->scopeTo($officer, AccessControl::SCOPE_DAERAH, ['10']);

        $response = $this->actingAs($officer)->getJson('/api/v1/dms/dokumen?q=wakaf')->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('10', $response->json('data.0.kod_daerah'));

        $this->acl->grant($officer, 'dms.sulit.lihat');
        $this->actingAs($officer)->getJson('/api/v1/dms/dokumen?q=wakaf')->assertJsonPath('meta.total', 2);

        $this->acl->scopeTo($officer, AccessControl::SCOPE_DAERAH, null);
        $this->actingAs($officer)->getJson('/api/v1/dms/dokumen?q=wakaf')->assertJsonPath('meta.total', 3);

        // Klasifikasi scope from RBC narrows further.
        $this->acl->scopeTo($officer, AccessControl::SCOPE_KLASIFIKASI, ['dalaman']);
        $this->actingAs($officer)->getJson('/api/v1/dms/dokumen?q=wakaf')->assertJsonPath('meta.total', 2);

        // No base permission: nothing, not even a count.
        $this->actingAs($this->userWith([]))->getJson('/api/v1/dms/dokumen?q=wakaf')->assertForbidden();
    }

    public function test_SRS_DMS_13_php_and_sql_access_rules_agree(): void
    {
        $docs = [
            $this->makeDoc('awam', 'a', '10'), $this->makeDoc('dalaman', 'b', '11'), $this->makeDoc('sulit', 'c', '10'),
            $this->makeDoc('rahsia', 'd', null), $this->makeDoc('dalaman', 'e', null),
        ];
        $user = $this->userWith(['dms.dokumen.lihat', 'dms.sulit.lihat']);
        $this->acl->scopeTo($user, AccessControl::SCOPE_DAERAH, ['10']);
        $docs[3]->kebenaran()->create(['jenis_penerima' => 'pengguna', 'penerima' => (string) $user->id]);

        $access = app(DocumentAccess::class);
        $sql = $access->scopeVisible(Dokumen::query(), $user)->pluck('id')->sort()->values()->all();
        $php = collect($docs)->filter(fn (Dokumen $d) => $access->canView($user, $d->refresh()))->pluck('id')->sort()->values()->all();

        $this->assertSame($php, $sql);
        $this->assertCount(4, $sql); // awam/10, sulit/10, rahsia via grant, dalaman/no-daerah
    }

    public function test_SRS_DMS_12_full_text_search_over_pdf_content_and_metadata_filters(): void
    {
        $extractor = app(TextExtractor::class);
        if (! $extractor->available('pdftotext')) {
            $this->markTestSkipped('pdftotext not installed');
        }
        $user = $this->userWith();
        $a = $this->index($this->uploadDoc($user, TestFiles::pdf('Perjanjian pajakan tanah di Mukim Kapar untuk tapak sekolah agama'), ['kategori' => 'perjanjian', 'kod_daerah' => '10', 'tahun' => 2024, 'kata_kunci' => 'sekolah']));
        $this->index($this->uploadDoc($user, TestFiles::pdf('Resit cukai tanah tahun 2023'), ['kategori' => 'resit', 'kod_daerah' => '11', 'tahun' => 2023]));

        $this->assertSame('selesai', $a->status_indeks);
        $this->assertSame('teks', $a->kaedah_indeks);

        // Content word, prefix match ("kapa" → Kapar), Malay without stemming.
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?q=kapa')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $a->id)
            ->assertJsonPath('data.0.petikan', fn ($p) => str_contains((string) $p, '«'));
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?q=tanah')->assertJsonPath('meta.total', 2);
        // Any combination of metadata filters.
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?q=tanah&kategori=perjanjian&kod_daerah=10&tahun=2024')->assertJsonPath('meta.total', 1);
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?q=tanah&kod_daerah=11')->assertJsonPath('meta.total', 1);
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?kata_kunci[]=sekolah')->assertJsonPath('meta.total', 1);
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?q=tiadaperkataanini')->assertJsonPath('meta.total', 0);
        // Reference number and typo-tolerant title.
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?q='.$a->no_rujukan)->assertJsonPath('data.0.id', $a->id);
    }

    public function test_SRS_DMS_12_scanned_pdf_is_ocrd_and_found_by_its_words_but_not_by_unauthorised_users(): void
    {
        $extractor = app(TextExtractor::class);
        if (! $extractor->available('tesseract') || ! $extractor->available('pdftoppm')) {
            $this->markTestSkipped('tesseract / pdftoppm not installed');
        }
        $file = TestFiles::scannedPdf('PERJANJIAN WAKAF BERNAS');
        if ($file === null) {
            $this->markTestSkipped('no TTF font to render the test scan');
        }

        $uploader = $this->userWith([...self::PEGAWAI, 'dms.sulit.lihat']);
        $doc = $this->index($this->uploadDoc($uploader, $file, ['klasifikasi' => 'sulit']));

        $this->assertSame('ocr', $doc->kaedah_indeks);
        $this->assertStringContainsStringIgnoringCase('wakaf', (string) $doc->teks_kandungan);

        // Acceptance 4.
        $this->actingAs($uploader)->getJson('/api/v1/dms/dokumen?q=bernas')->assertJsonPath('meta.total', 1);
        $this->actingAs($this->userWith(['dms.dokumen.lihat']))->getJson('/api/v1/dms/dokumen?q=bernas')
            ->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
    }

    public function test_SRS_DMS_12_office_and_spatial_text_is_extracted(): void
    {
        $user = $this->userWith();
        $docx = $this->index($this->uploadDoc($user, TestFiles::docx('Surat kelulusan tukar syarat')));
        $kml = $this->index($this->uploadDoc($user, TestFiles::kml(), ['kategori' => 'fail_gis']));
        $geo = $this->index($this->uploadDoc($user, TestFiles::geojson(), ['kategori' => 'fail_gis']));
        $dwg = $this->index($this->uploadDoc($user, TestFiles::dwg(), ['kategori' => 'fail_gis']));

        $this->assertStringContainsString('tukar syarat', (string) $docx->teks_kandungan);
        $this->assertStringContainsString('Lot 1234 Mukim Kapar', (string) $kml->teks_kandungan);
        $this->assertStringNotContainsString('101.4', (string) $kml->teks_kandungan);
        $this->assertStringContainsString('PT 5678', (string) $geo->teks_kandungan);
        $this->assertSame('tiada', $dwg->status_indeks);

        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?q=syarat')->assertJsonPath('data.0.id', $docx->id);
        $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$docx->id}/teks")->assertOk()->assertJsonPath('data.teks', fn ($t) => str_contains($t, 'kelulusan'));
    }
}
