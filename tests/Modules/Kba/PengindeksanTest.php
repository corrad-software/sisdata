<?php

namespace Tests\Modules\Kba;

use App\Modules\Dms\Events\DocumentDisposed;
use App\Modules\Dms\Events\DocumentStored;
use App\Modules\Kba\Ai\HashEmbeddingProvider;
use App\Modules\Kba\Contracts\EmbeddingProvider;
use App\Modules\Kba\Models\PemetaanKebenaran;
use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\Ingest\Chunker;
use App\Modules\Kba\Services\Ingest\PenukarTeks;
use App\Modules\Kba\Services\Pengindeks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PengindeksanTest extends KbaTestCase
{
    public function test_SRS_KBA_01_baseline_documents_are_indexed_with_coverage_report_and_claude_md_is_not(): void
    {
        $this->artisan('kba:index-baseline')->assertSuccessful();

        $sources = SumberDokumen::query()->where('asal', 'baseline')->get();
        $this->assertSame(['docs/PLAN-SISDATA.md', 'docs/data/erd.md', 'docs/data/justifikasi-pangkalan-data.md', 'docs/data/kamus-data.md', 'docs/data/model-fizikal.md', 'docs/srs/SRS-SISDATA.md'],
            $sources->pluck('laluan_fail')->sort()->values()->all());
        $this->assertTrue($sources->every(fn (SumberDokumen $s) => $s->status_indeks === 'selesai' && $s->bilangan_segmen > 0 && $s->tarikh_indeks_akhir !== null));
        $this->assertFalse(SumberDokumen::query()->where('laluan_fail', 'like', '%CLAUDE.md')->exists());

        $coverage = $this->actingAs($this->user(self::PENTADBIR))->getJson('/api/v1/kba/analitik')->assertOk()->json('data.liputan');
        $this->assertCount(6, $coverage);
        $this->assertSame(['selesai'], array_values(array_unique(array_column($coverage, 'status_indeks'))));

        // SRS-KBA-08: the security chapter of the SRS is Sulit at segment level.
        $srs = $sources->firstWhere('laluan_fail', 'docs/srs/SRS-SISDATA.md');
        $this->assertGreaterThan(0, DB::table('kba_segmen')->where('id_sumber', $srs->id)->where('status', 'aktif')->where('klasifikasi', 'sulit')->where('laluan_seksyen', 'like', '13. Keperluan Keselamatan%')->count());
        $this->assertSame(0, DB::table('kba_segmen')->where('id_sumber', $srs->id)->where('klasifikasi', 'sulit')->where('laluan_seksyen', 'not like', '13. Keperluan Keselamatan%')->count());

        // Incremental: nothing changed → nothing re-embedded.
        $before = DB::table('kba_segmen')->count();
        $this->artisan('kba:index-baseline')->expectsOutputToContain('tiada perubahan')->assertSuccessful();
        $this->assertSame($before, DB::table('kba_segmen')->count());
    }

    public function test_SRS_KBA_04_chunks_keep_section_number_title_and_path(): void
    {
        $chunks = (new Chunker(400, 50))->chunk("# Dok\n\n## 5. Bab Lima\n\nPengenalan.\n\n### 5.2 Keperluan merentas modul\n\n".str_repeat('Ayat panjang tentang keperluan. ', 30)
            ."\n\n| Kod | Keperluan |\n| --- | --- |\n".implode("\n", array_map(fn ($i) => "| SRS-X-0{$i} | Baris jadual {$i} ".str_repeat('isi ', 20).'|', range(1, 6))));

        $this->assertSame('5', $chunks[0]['nombor_seksyen']);
        $this->assertSame('5. Bab Lima', $chunks[0]['laluan_seksyen']);
        $sub = array_values(array_filter($chunks, fn ($c) => $c['nombor_seksyen'] === '5.2'));
        $this->assertGreaterThan(2, count($sub), 'long section split');
        $this->assertSame('5. Bab Lima › 5.2 Keperluan merentas modul', $sub[0]['laluan_seksyen']);
        foreach ($sub as $c) {
            $this->assertLessThanOrEqual(520, mb_strlen($c['kandungan_teks']));
            if (str_contains($c['kandungan_teks'], 'Baris jadual')) {
                $this->assertStringStartsWith('| Kod | Keperluan |', $c['kandungan_teks'], 'split tables repeat their header');
            }
        }
    }

    public function test_SRS_KBA_06_embeddings_and_permission_metadata_live_in_the_same_postgres_with_pgvector(): void
    {
        $this->korpus();

        $type = DB::selectOne("SELECT format_type(atttypid, atttypmod) AS t FROM pg_attribute WHERE attrelid = 'kba_segmen'::regclass AND attname = 'embedding'")->t;
        $this->assertSame('vector(768)', $type);
        $indexes = collect(DB::select("SELECT indexdef FROM pg_indexes WHERE tablename = 'kba_segmen'"))->pluck('indexdef')->implode("\n");
        $this->assertStringContainsString('USING hnsw (embedding vector_cosine_ops)', $indexes);
        $this->assertStringContainsString('gin_trgm_ops', $indexes);
        $this->assertStringContainsString('USING gin (carian)', $indexes);

        $row = DB::selectOne("SELECT klasifikasi, senarai_peranan_dibenarkan, model_embedding, vector_dims(embedding) AS d FROM kba_segmen WHERE status = 'aktif' LIMIT 1");
        $this->assertSame(768, (int) $row->d);
        $this->assertSame('cincangan-v1', $row->model_embedding);
        $this->assertNotNull($row->klasifikasi);
    }

    public function test_SRS_KBA_08_roles_and_mapping_restrict_segments_and_metadata_changes_apply_at_once(): void
    {
        $sop = $this->sumber(self::SOP_SULIT, ['tajuk' => 'SOP Pentadbir', 'klasifikasi' => 'dalaman', 'senarai_peranan_dibenarkan' => ['pentadbir_sistem']]);
        $soalan = 'kunci penyulitan diputar';

        $this->assertFalse($this->tanya($this->user(), $soalan)['cukup_sumber'], 'role list: not for this role');
        $this->assertTrue($this->tanya($this->user(self::PENGGUNA, 'pentadbir_sistem'), $soalan)['cukup_sumber']);

        // Raise to Sulit: applied to the segments immediately (no re-index), mapping grants a role.
        $admin = $this->user(self::PENTADBIR);
        $this->actingAs($admin)->putJson("/api/v1/kba/sumber/{$sop->id}", ['klasifikasi' => 'sulit', 'senarai_peranan_dibenarkan' => []])->assertOk();
        $this->assertSame(['sulit'], DB::table('kba_segmen')->where('id_sumber', $sop->id)->where('status', 'aktif')->distinct()->pluck('klasifikasi')->all());
        $this->assertFalse($this->tanya($this->user(self::PENGGUNA, 'pegawai_operasi'), $soalan)['cukup_sumber']);

        $this->actingAs($this->user([...self::PENTADBIR]))->putJson('/api/v1/kba/pemetaan', ['pemetaan' => [
            ['klasifikasi' => 'sulit', 'peranan' => 'pegawai_operasi', 'jenis_capaian' => 'baca', 'tarikh_berkuat_kuasa' => now()->toDateString()],
        ]])->assertOk();
        $this->assertSame(1, PemetaanKebenaran::query()->count(), 'mapping replaced (seeded rows removed)');
        $this->assertTrue($this->tanya($this->user(self::PENGGUNA, 'pegawai_operasi'), $soalan)['cukup_sumber']);

        PemetaanKebenaran::query()->update(['tarikh_berkuat_kuasa' => now()->addDay()->toDateString()]);
        $this->assertFalse($this->tanya($this->user(self::PENGGUNA, 'pegawai_operasi'), $soalan)['cukup_sumber'], 'not yet in force');

        $this->actingAs($this->user())->putJson("/api/v1/kba/sumber/{$sop->id}", ['klasifikasi' => 'awam'])->assertForbidden();
        $this->actingAs($this->user(self::PENGGUNA))->putJson('/api/v1/kba/pemetaan', ['pemetaan' => []])->assertForbidden();
    }

    public function test_SRS_KBA_08_klasifikasi_and_district_scopes_from_rbc_apply(): void
    {
        $this->sumber("## Prosedur Petaling\n\nProsedur khas pejabat tanah Petaling untuk tukar syarat.", ['tajuk' => 'SOP Petaling', 'kod_daerah' => '1008']);
        $this->sumber("## Prosedur Sepang\n\nProsedur khas pejabat tanah Sepang untuk tukar syarat.", ['tajuk' => 'SOP Sepang', 'kod_daerah' => '1009']);

        $sepang = $this->user(self::PENGGUNA, null, ['daerah' => ['1009']]);
        $hits = $this->actingAs($sepang)->getJson('/api/v1/kba/carian?q=prosedur+tukar+syarat')->json('data');
        $this->assertSame(['SOP Sepang'], array_column($hits, 'dokumen'));

        $awamOnly = $this->user([...self::PENGGUNA, 'kba.sulit.lihat'], null, ['klasifikasi' => ['awam']]);
        $this->assertSame([], $this->actingAs($awamOnly)->getJson('/api/v1/kba/carian?q=prosedur+tukar+syarat')->json('data'));
    }

    public function test_SRS_KBA_02_dms_document_is_indexed_incrementally_on_store_and_new_version(): void
    {
        $owner = $this->user(['dms.dokumen.lihat', 'kba.soalan.lihat']);
        $id = $this->dmsDokumen($owner->id, "1. PENGENALAN\nLaporan pemantauan tanah wakaf Sepang.\n\n2. DAPATAN\nPencerobohan dikesan di lot 1234 pada bulan Mac.", ['kod_daerah' => '1009']);
        event(new DocumentStored($id, 1, 'lot_tanah', 'x', 'dalaman'));

        $sumber = SumberDokumen::query()->where('kod', 'dms:'.$id)->firstOrFail();
        $this->assertSame('selesai', $sumber->status_indeks);
        $this->assertSame('v1', $sumber->versi);
        $this->assertSame('dalaman', $sumber->klasifikasi);
        $first = DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('status', 'aktif')->orderBy('urutan')->get();
        $this->assertGreaterThanOrEqual(2, $first->count());
        $this->assertSame([$id], $first->pluck('id_dokumen_dms')->unique()->values()->all());
        $this->assertSame('2. DAPATAN', $first->last()->tajuk_seksyen);

        // New version: only the changed section is embedded again; old segments go "lapuk".
        DB::table('dokumen')->where('id', $id)->update(['versi_semasa' => 2, 'teks_kandungan' => "1. PENGENALAN\nLaporan pemantauan tanah wakaf Sepang.\n\n2. DAPATAN\nPencerobohan dikesan di lot 1234 dan lot 1240 pada bulan April."]);
        event(new DocumentStored($id, 2, 'lot_tanah', 'x', 'dalaman'));
        $sumber->refresh();
        $this->assertSame('v2', $sumber->versi);
        $this->assertSame($first->count(), DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('status', 'lapuk')->count());
        $this->assertSame(1, DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('status', 'aktif')->where('kandungan_teks', 'like', '%April%')->count());
        $reused = DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('status', 'aktif')->where('tajuk_seksyen', '1. PENGENALAN')->value('cincangan');
        $this->assertSame($first->first()->cincangan, $reused, 'unchanged section reused (no full re-index)');

        // Visible through DMS's own rule: the uploader yes; a DMS reader outside Sepang no.
        $this->assertNotEmpty($this->actingAs($owner)->getJson('/api/v1/kba/carian?q=pencerobohan')->json('data'));
        $petaling = $this->user(['dms.dokumen.lihat', 'kba.soalan.lihat'], null, ['daerah' => ['1008']]);
        $this->assertSame([], $this->actingAs($petaling)->getJson('/api/v1/kba/carian?q=pencerobohan')->json('data'));
        $noDms = $this->user(['kba.soalan.lihat']);
        $this->assertSame([], $this->actingAs($noDms)->getJson('/api/v1/kba/carian?q=pencerobohan')->json('data'));

        event(new DocumentDisposed($id, 'lot_tanah', 'x'));
        $this->assertSame(0, DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('status', 'aktif')->count());
        $this->assertFalse($sumber->refresh()->aktif);
    }

    public function test_SRS_KBA_02_dms_documents_outside_the_auto_categories_or_still_being_extracted_wait(): void
    {
        $u = $this->user(['dms.dokumen.lihat', 'kba.soalan.lihat']);
        $geran = $this->dmsDokumen($u->id, 'Geran hak milik', ['kategori' => 'geran']);
        event(new DocumentStored($geran, 1, 'hak_milik', 'x', 'sulit'));
        $this->assertFalse(SumberDokumen::query()->where('kod', 'dms:'.$geran)->exists());

        $pending = $this->dmsDokumen($u->id, null, ['status_indeks' => 'menunggu']);
        event(new DocumentStored($pending, 1, 'lot_tanah', 'x', 'dalaman'));
        $this->assertSame(0, DB::table('kba_segmen')->join('kba_sumber_dokumen as d', 'd.id', '=', 'kba_segmen.id_sumber')->where('d.kod', 'dms:'.$pending)->count());

        // Explicit registration by a content admin works for any category.
        $this->actingAs($this->user(self::PENTADBIR))->postJson('/api/v1/kba/sumber/dms', ['id_dokumen_dms' => $geran, 'jenis_dokumen' => 'dokumen_dms'])->assertCreated();
        $this->assertSame('selesai', SumberDokumen::query()->where('kod', 'dms:'.$geran)->value('status_indeks'));
    }

    public function test_SRS_KBA_01_admin_uploads_markdown_and_docx_manuals(): void
    {
        $admin = $this->user(self::PENTADBIR);
        $md = UploadedFile::fake()->createWithContent('manual.md', "# Manual Pentadbir\n\n## 4. Sandaran\n\nSandaran penuh dijalankan setiap malam pada jam 1.00 pagi.");
        $res = $this->actingAs($admin)->post('/api/v1/kba/sumber', [
            'fail' => $md, 'tajuk' => 'Manual Pentadbir', 'jenis_dokumen' => 'manual_pentadbir', 'klasifikasi' => 'dalaman', 'versi' => '1.0',
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame('selesai', $res['status_indeks']);
        $this->assertSame('1.0', $res['versi']);
        $r = $this->tanya($this->user(), 'Bilakah sandaran penuh dijalankan?');
        $this->assertTrue($r['cukup_sumber']);
        $this->assertSame('§4 Sandaran', $r['sumber'][0]['seksyen']);

        $this->actingAs($this->user())->post('/api/v1/kba/sumber', ['fail' => $md, 'tajuk' => 'x', 'jenis_dokumen' => 'SOP', 'klasifikasi' => 'awam'], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($admin)->post('/api/v1/kba/sumber', ['fail' => UploadedFile::fake()->create('x.exe', 1), 'tajuk' => 'x', 'jenis_dokumen' => 'SOP', 'klasifikasi' => 'awam'], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_SRS_KBA_01_docx_headings_become_sections(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kba').'.docx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .'<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>2. Pendaftaran Lot</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Lot baharu didaftarkan oleh Pegawai Operasi.</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $md = app(PenukarTeks::class)->fromFile($path, 'docx');
        @unlink($path);
        $this->assertStringContainsString('## 2. Pendaftaran Lot', $md);
        $chunks = app(Chunker::class)->chunk($md);
        $this->assertSame('2', $chunks[0]['nombor_seksyen']);
    }

    public function test_SRS_KBA_02_admin_reindex_and_index_status(): void
    {
        $s = $this->sumber(self::MANUAL);
        $admin = $this->user(self::PENTADBIR);
        $this->actingAs($admin)->postJson("/api/v1/kba/sumber/{$s->id}/indeks-semula")->assertAccepted();
        $this->assertSame(2, $s->refresh()->no_versi, 'forced re-index makes a new index version');
        $this->assertSame('selesai', $s->status_indeks);

        $status = $this->actingAs($admin)->getJson('/api/v1/kba/status-indeks')->assertOk()->json('data');
        $this->assertSame(3, $status['segmen_aktif']);
        $this->assertSame(3, $status['segmen_berembedding']);
        $this->assertSame('ekstraktif', $status['mod_jawapan']);
        $this->assertCount(1, $this->actingAs($admin)->getJson('/api/v1/kba/sumber')->json('data'));
        $this->actingAs($this->user())->getJson('/api/v1/kba/sumber')->assertForbidden();
        $this->actingAs($this->user())->postJson("/api/v1/kba/sumber/{$s->id}/indeks-semula")->assertForbidden();
    }

    public function test_SRS_KBA_06_index_works_without_the_embedding_model_and_catches_up_later(): void
    {
        $down = new class implements EmbeddingProvider
        {
            public function model(): string
            {
                return 'nomic-embed-text';
            }

            public function dimensions(): int
            {
                return 768;
            }

            public function embed(array $texts, string $purpose = 'dokumen'): array
            {
                throw new \RuntimeException('model tidak dapat dihubungi');
            }
        };
        $this->app->instance(EmbeddingProvider::class, $down);
        $s = $this->sumber(self::MANUAL);
        $this->assertSame('selesai', $s->status_indeks);
        $this->assertNotNull($s->mesej_indeks);
        $this->assertSame(0, DB::table('kba_segmen')->where('id_sumber', $s->id)->whereNotNull('embedding')->count());
        $this->assertTrue($this->tanya($this->user(), 'had saiz fail dokumen pejabat')['cukup_sumber'], 'keyword legs still answer');

        $this->app->instance(EmbeddingProvider::class, new HashEmbeddingProvider(768));
        app(Pengindeks::class)->indeks($s->refresh(), self::MANUAL);
        $this->assertSame(3, DB::table('kba_segmen')->where('id_sumber', $s->id)->where('status', 'aktif')->whereNotNull('embedding')->count());
        $this->assertNull($s->refresh()->mesej_indeks);
    }

    /** @param array<string, mixed> $attrs */
    private function dmsDokumen(int $pencipta, ?string $teks, array $attrs = []): string
    {
        $id = (string) Str::uuid();
        $kategori = DB::table('dokumen_kategori')->where('kod', $attrs['kategori'] ?? 'laporan')->value('id');
        unset($attrs['kategori']);
        DB::table('dokumen')->insert($attrs + [
            'id' => $id, 'no_rujukan' => 'DMS-T-'.Str::random(8), 'kategori_id' => $kategori, 'tajuk' => 'Laporan Pemantauan Sepang',
            'nama_fail' => 'laporan.pdf', 'jenis_mime' => 'application/pdf', 'sambungan' => 'pdf', 'saiz_bait' => 1000, 'cincangan_fail' => str_repeat('a', 64),
            'klasifikasi_keselamatan' => 'dalaman', 'status' => 'aktif', 'id_pencipta' => $pencipta, 'versi_semasa' => 1,
            'teks_kandungan' => $teks, 'status_indeks' => $teks === null ? 'menunggu' : 'selesai', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }
}
