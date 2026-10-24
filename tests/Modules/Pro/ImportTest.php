<?php

namespace Tests\Modules\Pro;

use App\Modules\Pro\Models\DataSpatial;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\TugasanKualitiData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportTest extends ProTestCase
{
    private const HEADER = ['no_lot', 'kod_mukim', 'keluasan', 'unit_keluasan', 'bahagian_pemilik', 'kategori_guna_tanah', 'kunci_asal'];

    /** Rows 2..7 of the file: 3 valid, 1 bad area, 1 unknown mukim, 1 duplicate of row 2, plus an empty row. */
    private function rows(): array
    {
        return [
            ['Lot 101', '100803', '1.5', 'hektar', 'wakaf', 'PERTANIAN', 'A-1'],     // 2 ok
            ['PT 202', '100803', '850', 'm2', 'baitulmal', 'BANGUNAN', 'A-2'],      // 3 ok
            ['Lot 303', '100803', '-4', 'm2', 'hartanah', '', 'A-3'],               // 4 keluasan < 0
            ['Lot 404', '999999', '10', 'm2', 'hartanah', '', 'A-4'],               // 5 mukim not in MOD-REF
            ['L101', '100803', '1.5', 'hektar', 'wakaf', '', 'A-5'],                // 6 same lot as row 2
            ['', '', '', '', '', '', ''],                                            // 7 empty → ignored
            ['No. Lot 505', '100801', '2', 'ekar', 'hartanah', 'PERUSAHAAN', 'A-7'], // 8 ok
        ];
    }

    private function csv(array $header, array $rows, string $name = 'lot.csv'): UploadedFile
    {
        $lines = array_map(fn ($r) => implode(',', $r), [$header, ...$rows]);

        return UploadedFile::fake()->createWithContent($name, implode("\n", $lines)."\n");
    }

    private function xlsx(array $header, array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([$header, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'pro').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'lot.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_SRS_PRO_15_downloadable_templates_per_core_entity(): void
    {
        $user = $this->officer();

        $csv = $this->actingAs($user)->get('/api/v1/pro/import/templat/lot_tanah?format=csv')->assertOk();
        $this->assertStringContainsString('no_lot,kod_mukim,kod_daerah,keluasan', $csv->streamedContent());

        $xlsx = $this->actingAs($user)->get('/api/v1/pro/import/templat/data_spatial?format=xlsx')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $xlsx->streamedContent());
        $book = IOFactory::load($path);
        $this->assertSame('kod_mukim', $book->getSheet(0)->getCell('A1')->getValue());
        $this->assertSame('Panduan', $book->getSheet(1)->getTitle());

        $this->actingAs($user)->get('/api/v1/pro/import/templat/hak_milik')->assertNotFound();
        $this->actingAs($this->officer(['pro.lot.lihat']))->get('/api/v1/pro/import/templat/lot_tanah')->assertForbidden();
    }

    public function test_SRS_PRO_16_rows_are_validated_and_previewed_before_anything_is_committed(): void
    {
        $user = $this->officer();

        $response = $this->actingAs($user)->post('/api/v1/pro/import', ['fail' => $this->csv(self::HEADER, $this->rows()), 'entiti' => 'lot_tanah'], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.ringkasan.status_sesi', 'pratonton')
            ->assertJsonPath('data.ringkasan.bil_baris_dibaca', 7)
            ->assertJsonPath('data.ringkasan.bil_sah', 3)
            ->assertJsonPath('data.ringkasan.bil_ditolak', 3)
            ->assertJsonPath('data.ringkasan.bil_diabaikan', 1);
        $this->assertSame(0, LotTanah::query()->count(), 'nothing committed before confirmation');

        $rejected = collect($response->json('data.ditolak'))->groupBy('no_baris');
        $this->assertSame([4, 5, 6], $rejected->keys()->sort()->values()->all());
        $this->assertSame('keluasan', $rejected[4][0]['medan']);
        $this->assertSame('kod_mukim', $rejected[5][0]['medan']);
        $this->assertSame('duplikasi_fail', $rejected[6][0]['kod_ralat']);
        $this->assertStringContainsString('baris 2', $rejected[6][0]['sebab_penolakan']);
    }

    public function test_SRS_DAT_03_csv_with_mixed_rows_commits_only_valid_rows_with_full_report(): void
    {
        $user = $this->officer();
        $this->makeLot(['no_lot' => 'PT 202']); // makes row 3 a duplicate of an existing record

        $id = $this->actingAs($user)->post('/api/v1/pro/import', ['fail' => $this->csv(self::HEADER, $this->rows()), 'entiti' => 'lot_tanah'], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.ringkasan.id');
        $commit = $this->actingAs($user)->postJson("/api/v1/pro/import/{$id}/komit")->assertOk();

        $commit->assertJsonPath('data.ringkasan.status_sesi', 'dikomit')->assertJsonPath('data.ringkasan.bil_berjaya', 2)
            ->assertJsonPath('data.ringkasan.bil_ditolak', 4)->assertJsonPath('data.ringkasan.bil_diabaikan', 1);
        $this->assertEqualsCanonicalizing(['Lot 101', 'Lot 505', 'PT 202'], LotTanah::query()->pluck('no_lot')->all());
        $imported = LotTanah::query()->where('no_lot', 'Lot 101')->firstOrFail();
        $this->assertSame([$id, 'import:csv', 'lot.csv', 'A-1'], [$imported->id_sesi_import, $imported->sumber_sistem, $imported->fail_asal, $imported->kunci_asal]);
        $this->assertSame('Lot 505', LotTanah::query()->where('kunci_asal', 'A-7')->value('no_lot'));
        $this->assertContains(3, array_column($commit->json('data.ditolak'), 'no_baris'));

        // Committing twice is refused.
        $this->actingAs($user)->postJson("/api/v1/pro/import/{$id}/komit")->assertStatus(422);
    }

    public function test_SRS_DAT_03_excel_with_mixed_rows_commits_only_valid_rows(): void
    {
        $user = $this->officer();
        $id = $this->actingAs($user)->post('/api/v1/pro/import', ['fail' => $this->xlsx(self::HEADER, $this->rows()), 'entiti' => 'lot_tanah'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.ringkasan.bil_sah', 3)->json('data.ringkasan.id');

        $this->actingAs($user)->postJson("/api/v1/pro/import/{$id}/komit")->assertOk()->assertJsonPath('data.ringkasan.bil_berjaya', 3);
        $this->assertSame(3, LotTanah::query()->count());
        $this->assertSame('import:xlsx', LotTanah::query()->value('sumber_sistem'));
    }

    public function test_SRS_PRO_17_report_lists_rejected_rows_is_exportable_and_original_file_is_kept(): void
    {
        $user = $this->officer();
        $id = $this->actingAs($user)->post('/api/v1/pro/import', ['fail' => $this->xlsx(self::HEADER, $this->rows()), 'entiti' => 'lot_tanah'], ['Accept' => 'application/json'])->json('data.ringkasan.id');
        $this->actingAs($user)->postJson("/api/v1/pro/import/{$id}/komit")->assertOk();

        $report = $this->actingAs($user)->getJson("/api/v1/pro/import/{$id}")->assertOk();
        $report->assertJsonPath('data.ringkasan.pengguna', $user->name)->assertJsonPath('data.ringkasan.nama_fail', 'lot.xlsx');
        $this->assertNotNull($report->json('data.ringkasan.tarikh_masa'));
        $docId = $report->json('data.ringkasan.lokasi_fail_asal');
        $this->assertSame(['sesi_import', $id], [$this->documents->stored[$docId]['owner_type'], $this->documents->stored[$docId]['owner_id']]);

        $csv = $this->actingAs($user)->get("/api/v1/pro/import/{$id}/laporan?format=csv")->assertOk()->streamedContent();
        $this->assertStringContainsString('No. Baris', $csv);
        $this->assertStringContainsString('dibaca 7', $csv);
        $this->assertMatchesRegularExpression('/\n4,Keluasan,-4,/', $csv);
        $this->actingAs($user)->get("/api/v1/pro/import/{$id}/laporan?format=pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        // Another officer can't read someone else's session without a governance permission.
        $this->actingAs($this->officer())->getJson("/api/v1/pro/import/{$id}")->assertNotFound();
        $this->actingAs($this->officer(['pro.kualiti.lihat']))->getJson("/api/v1/pro/import/{$id}")->assertOk();
    }

    public function test_SRS_PRO_17_rejected_original_file_is_reported_not_lost_silently(): void
    {
        $this->documents->reject = ['csv']; // e.g. CSV not yet on the DMS whitelist
        $response = $this->actingAs($this->officer())->post('/api/v1/pro/import', ['fail' => $this->csv(self::HEADER, $this->rows()), 'entiti' => 'lot_tanah'], ['Accept' => 'application/json'])->assertCreated();

        $this->assertNull($response->json('data.ringkasan.lokasi_fail_asal'));
        $this->assertStringContainsString('Fail asal tidak disimpan dalam DMS', (string) $response->json('data.ringkasan.catatan_fail_asal'));
    }

    public function test_SRS_PRO_15_column_mapping_is_configurable_and_saved(): void
    {
        $user = $this->officer();
        $header = ['Nombor', 'Kod Kawasan', 'Luas (m2)', 'Pemilik MAIS'];
        $rows = [['Lot 9', '100803', '500', 'hartanah']];

        $response = $this->actingAs($user)->post('/api/v1/pro/import', ['fail' => $this->csv($header, $rows), 'entiti' => 'lot_tanah'], ['Accept' => 'application/json']);
        $response->assertStatus(422)->assertJsonValidationErrors(['pemetaan']);

        $mapping = ['Nombor' => 'no_lot', 'Kod Kawasan' => 'kod_mukim', 'Luas (m2)' => 'keluasan', 'Pemilik MAIS' => 'bahagian_pemilik'];
        $saved = $this->actingAs($user)->postJson('/api/v1/pro/import/pemetaan', ['entiti_sasaran' => 'lot_tanah', 'nama' => 'Sistem lama', 'pemetaan' => $mapping])->assertCreated()->json('data.id');

        $this->actingAs($user)->post('/api/v1/pro/import', ['fail' => $this->csv($header, $rows), 'entiti' => 'lot_tanah', 'id_pemetaan' => $saved], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.ringkasan.bil_sah', 1)->assertJsonPath('data.baris.0.nilai.no_lot', 'Lot 9');
    }

    public function test_SRS_PRO_16_spatial_import_checks_lot_existence_and_geometry(): void
    {
        $user = $this->officer();
        $this->makeLot(['no_lot' => 'Lot 1']);
        $header = ['kod_mukim', 'no_lot', 'format', 'geometri', 'srid', 'sumber_data'];
        $rows = [
            ['100803', 'Lot 1', 'wkt', '"'.$this->square().'"', '4326', 'juruukur'],
            ['100803', 'Lot 77', 'wkt', '"'.$this->square(101.7).'"', '4326', 'juruukur'],
            ['100803', 'Lot 1', 'wkt', 'POLYGON((x', '4326', 'juruukur'],
        ];
        $id = $this->actingAs($user)->post('/api/v1/pro/import', ['fail' => $this->csv($header, $rows, 'geo.csv'), 'entiti' => 'data_spatial'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.ringkasan.bil_sah', 1)->assertJsonPath('data.ringkasan.bil_ditolak', 2)->json('data.ringkasan.id');

        $this->actingAs($user)->postJson("/api/v1/pro/import/{$id}/komit")->assertOk()->assertJsonPath('data.ringkasan.bil_berjaya', 1);
        $this->assertSame(1, DataSpatial::query()->count());
    }

    public function test_SRS_PRO_12_rejected_import_rows_are_routed_to_the_steward(): void
    {
        $steward = $this->officer(['pro.kualiti.lihat']);
        DB::table('daftar_stewardship_data')->where('komponen_data', 'Lot tanah dan geometri')->update(['id_data_steward' => $steward->id]);
        $user = $this->officer();
        $id = $this->actingAs($user)->post('/api/v1/pro/import', ['fail' => $this->csv(self::HEADER, $this->rows()), 'entiti' => 'lot_tanah'], ['Accept' => 'application/json'])->json('data.ringkasan.id');
        $this->actingAs($user)->postJson("/api/v1/pro/import/{$id}/komit")->assertOk();

        $task = TugasanKualitiData::query()->where('entiti', 'sesi_import')->where('id_rekod', $id)->firstOrFail();
        $this->assertSame($steward->id, $task->id_data_steward);
        $this->assertNotEmpty($this->notifier->of('pro.kualiti_data_gagal'));
    }

    public function test_SRS_DAT_08_staging_import_keeps_lineage_and_is_idempotent(): void
    {
        $run = (string) Str::uuid();
        DB::table('migrasi_larian')->insert(['id' => $run, 'jenis' => 'sampel_sintetik', 'sumber_sistem' => 'penjana_sintetik', 'status' => 'selesai', 'mula_pada' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $rows = [
            ['LOT-000001', 'Lot 11', '100803', 1.2, 'hektar', true, false, $this->square(101.60, 3.10)],
            ['LOT-000002', 'PT No. 22', '100803', 900, 'meter_persegi', false, true, $this->square(101.62, 3.10)],
            ['LOT-000003', 'Lot 33', '100803', 500, 'meter_persegi', false, false, null],
            ['LOT-000004', 'L11', '100803', 1.2, 'hektar', true, false, null], // same lot as LOT-000001 → rejected
        ];
        foreach ($rows as [$key, $no, $mukim, $area, $unit, $wakaf, $baitulmal, $wkt]) {
            DB::table('staging_lot_tanah')->insert([
                'id' => (string) Str::uuid(), 'migrasi_larian_id' => $run, 'no_lot' => $no, 'jenis_lot' => 'LOT', 'negeri_kod' => '10', 'daerah_kod' => '1008',
                'mukim_kod' => $mukim, 'keluasan' => $area, 'unit_keluasan' => $unit, 'kategori_guna_tanah_kod' => 'PERTANIAN', 'wakaf' => $wakaf, 'baitulmal' => $baitulmal,
                'geometri' => $wkt ? DB::raw("ST_GeomFromText('{$wkt}', 4326)") : null,
                'sintetik' => true, 'sumber_sistem' => 'penjana_sintetik', 'fail_asal' => 'sampel.json', 'kunci_asal' => $key, 'dimuat_pada' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->artisan('pro:import-staging')->assertSuccessful();

        $this->assertSame(3, LotTanah::query()->count());
        $lot = LotTanah::query()->where('kunci_asal', 'LOT-000002')->firstOrFail();
        $this->assertSame(['PT 22', 'baitulmal', 'm2', true, 'penjana_sintetik', 'sampel.json'], [$lot->no_lot, $lot->bahagian_pemilik, $lot->unit_keluasan, $lot->sintetik, $lot->sumber_sistem, $lot->fail_asal]);
        $this->assertNotNull($lot->id_migrasi_larian);
        $this->assertSame('selesai', DB::table('migrasi_larian')->where('id', $lot->id_migrasi_larian)->value('status'));
        $this->assertSame(1, (int) DB::table('migrasi_larian')->where('id', $lot->id_migrasi_larian)->value('bilangan_ditolak'));
        $this->assertSame('wakaf', LotTanah::query()->where('kunci_asal', 'LOT-000001')->value('bahagian_pemilik'));
        $this->assertSame(['sintetik', 'LOT-000001'], [DataSpatial::query()->where('lot_id', LotTanah::query()->where('kunci_asal', 'LOT-000001')->value('id'))->value('sumber_data'), DataSpatial::query()->where('kunci_asal', 'LOT-000001')->value('kunci_asal')]);
        $this->assertSame(2, DataSpatial::query()->count());

        $this->artisan('pro:import-staging')->assertSuccessful();
        $this->assertSame(3, LotTanah::query()->count(), 'second run imports nothing new');
    }
}
