<?php

namespace Tests\Modules\Adm;

use App\Modules\Adm\Contracts\ReferenceCodes;
use App\Modules\Adm\Models\KodRujukan;
use Illuminate\Http\UploadedFile;

class KodRujukanTest extends AdmTestCase
{
    private function create(array $data): int
    {
        return $this->actingAs($this->admin())->postJson('/api/v1/adm/kod-rujukan', $data)->assertCreated()->json('data.id');
    }

    public function test_SRS_ADM_02_admin_adds_a_code_and_it_is_usable_immediately(): void
    {
        $this->create(['jenis_kod' => 'daerah', 'kod' => '10', 'keterangan' => 'Petaling', 'susunan' => 1]);

        $codes = $this->app->make(ReferenceCodes::class);
        $this->assertTrue($codes->valid('daerah', '10'));
        $this->assertSame('Petaling', $codes->label('daerah', '10'));
        $this->assertSame([['kod' => '10', 'keterangan' => 'Petaling', 'kod_induk' => null]], $codes->options('daerah'));
    }

    public function test_SRS_ADM_02_duplicate_codes_in_a_type_are_refused(): void
    {
        $this->create(['jenis_kod' => 'daerah', 'kod' => '10', 'keterangan' => 'Petaling']);

        $this->actingAs($this->admin())->postJson('/api/v1/adm/kod-rujukan', ['jenis_kod' => 'daerah', 'kod' => '10', 'keterangan' => 'Lain'])
            ->assertStatus(422)->assertJsonValidationErrors('kod');
        // Same code in another type is fine.
        $this->create(['jenis_kod' => 'kategori_aduan', 'kod' => '10', 'keterangan' => 'Pencerobohan']);
    }

    public function test_SRS_ADM_02_amendments_are_versioned_with_history(): void
    {
        $id = $this->create(['jenis_kod' => 'jenis_hak_milik', 'kod' => 'GRN', 'keterangan' => 'Geran']);

        $this->actingAs($this->admin())->putJson("/api/v1/adm/kod-rujukan/{$id}", ['keterangan' => 'Geran Mukim', 'justifikasi' => 'Ejaan rasmi'])
            ->assertOk()->assertJsonPath('data.versi', 2);

        $res = $this->actingAs($this->admin())->getJson("/api/v1/adm/kod-rujukan/{$id}/sejarah")->assertOk();
        $this->assertSame('Geran Mukim', $res->json('data.semasa.keterangan'));
        $this->assertSame(1, $res->json('data.versi.0.versi'));
        $this->assertSame('Geran', $res->json('data.versi.0.data.keterangan'));
    }

    public function test_SRS_ADM_02_deactivated_code_stays_valid_for_historical_records(): void
    {
        $id = $this->create(['jenis_kod' => 'mukim_lama', 'kod' => 'M1', 'keterangan' => 'Mukim Lama', 'tarikh_mula' => '2020-01-01']);

        $this->actingAs($this->admin())->postJson("/api/v1/adm/kod-rujukan/{$id}/nyahaktif", ['tarikh_tamat' => '2025-12-31', 'justifikasi' => 'Penyusunan semula'])
            ->assertOk()->assertJsonPath('data.status', 'tidak_aktif');

        $codes = $this->app->make(ReferenceCodes::class);
        $this->assertTrue($codes->valid('mukim_lama', 'M1', new \DateTime('2024-06-01')));
        $this->assertFalse($codes->valid('mukim_lama', 'M1', new \DateTime('2026-06-01')));
        $this->assertSame('Mukim Lama', $codes->label('mukim_lama', 'M1'));
        $this->assertSame([], $codes->options('mukim_lama'));
        $this->assertCount(1, $codes->options('mukim_lama', activeOnly: false));
        $this->assertDatabaseHas('adm_kod_rujukan', ['id' => $id]);

        $this->actingAs($this->admin())->postJson("/api/v1/adm/kod-rujukan/{$id}/aktif")->assertOk()->assertJsonPath('data.status', 'aktif');
    }

    public function test_SRS_ADM_02_search_by_type_text_status_and_validity_date(): void
    {
        $this->create(['jenis_kod' => 'daerah', 'kod' => '10', 'keterangan' => 'Petaling']);
        $this->create(['jenis_kod' => 'daerah', 'kod' => '11', 'keterangan' => 'Klang']);
        $this->create(['jenis_kod' => 'kategori_aduan', 'kod' => 'P', 'keterangan' => 'Petaling aduan']);

        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/v1/adm/kod-rujukan?jenis=daerah&q=peta')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.kod', '10');
        $this->actingAs($admin)->getJson('/api/v1/adm/kod-rujukan?q=petaling')->assertJsonPath('total', 2);
        $this->actingAs($admin)->getJson('/api/v1/adm/kod-rujukan?status=tidak_aktif')->assertJsonPath('total', 0);

        $types = collect($this->actingAs($admin)->getJson('/api/v1/adm/kod-rujukan/jenis')->json('data'))->keyBy('jenis_kod');
        foreach (['daerah', 'mukim', 'kategori_guna_tanah', 'jenis_hak_milik', 'jenis_dokumen', 'kategori_aduan', 'jenis_urusan', 'status_transaksi'] as $t) {
            $this->assertArrayHasKey($t, $types->all());
        }
        $this->assertSame(2, $types['daerah']['jumlah']);
        $this->assertSame('daerah', $types['mukim']['induk']);
    }

    public function test_SRS_ADM_02_mukim_parent_must_be_an_existing_daerah(): void
    {
        $this->actingAs($this->admin())->postJson('/api/v1/adm/kod-rujukan', ['jenis_kod' => 'mukim', 'kod' => '1001', 'keterangan' => 'Damansara', 'kod_induk' => '99'])
            ->assertStatus(422)->assertJsonValidationErrors('kod_induk');

        $this->create(['jenis_kod' => 'daerah', 'kod' => '10', 'keterangan' => 'Petaling']);
        $this->create(['jenis_kod' => 'mukim', 'kod' => '1001', 'keterangan' => 'Damansara', 'kod_induk' => '10']);
        $this->assertCount(1, $this->app->make(ReferenceCodes::class)->options('mukim', '10'));
    }

    public function test_SRS_ADM_02_permissions_are_enforced_per_action(): void
    {
        $this->getJson('/api/v1/adm/kod-rujukan')->assertUnauthorized();
        $id = $this->create(['jenis_kod' => 'daerah', 'kod' => '10', 'keterangan' => 'Petaling']);
        $viewer = $this->userWith(['adm.kod_rujukan.lihat']);

        $this->actingAs($viewer)->getJson('/api/v1/adm/kod-rujukan')->assertOk();
        $this->actingAs($viewer)->postJson('/api/v1/adm/kod-rujukan', ['jenis_kod' => 'daerah', 'kod' => '12', 'keterangan' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/v1/adm/kod-rujukan/{$id}/nyahaktif")->assertForbidden();
        $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/kod-rujukan')->assertForbidden();
    }

    public function test_SRS_ADM_05_bulk_import_validates_each_row_and_reports_rejections(): void
    {
        $this->create(['jenis_kod' => 'jenis_dokumen', 'kod' => 'GRN', 'keterangan' => 'Geran']);

        $res = $this->actingAs($this->admin())->postJson('/api/v1/adm/kod-rujukan/import', [
            'jenis_kod' => 'jenis_dokumen',
            'rekod' => [
                ['kod' => 'SPA', 'keterangan' => 'Perjanjian Jual Beli'],
                ['kod' => 'GRN', 'keterangan' => 'Duplikat'],
                ['kod' => 'BAD CODE!', 'keterangan' => 'Kod tak sah'],
                ['kod' => 'PLN', 'keterangan' => 'Pelan', 'susunan' => 3],
            ],
        ])->assertOk();

        $this->assertSame(2, $res->json('data.diterima'));
        $this->assertSame(2, $res->json('data.ditolak'));
        $this->assertSame([2, 3], array_column($res->json('data.ralat'), 'baris'));
        $this->assertSame(3, KodRujukan::query()->where('jenis_kod', 'jenis_dokumen')->count());
    }

    public function test_SRS_ADM_05_bulk_import_from_csv_with_upsert(): void
    {
        $this->create(['jenis_kod' => 'status_transaksi', 'kod' => 'B', 'keterangan' => 'Baru']);
        $csv = "\xEF\xBB\xBFkod,keterangan,susunan\nB,Baharu,1\nL,Lulus,2\n";
        $file = UploadedFile::fake()->createWithContent('kod.csv', $csv);

        $res = $this->actingAs($this->admin())->post('/api/v1/adm/kod-rujukan/import', [
            'jenis_kod' => 'status_transaksi', 'kemas_kini_sedia_ada' => '1', 'fail' => $file,
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, $res->json('data.diterima'));
        $this->assertSame(1, $res->json('data.dikemas_kini'));
        $this->assertSame('Baharu', KodRujukan::query()->where('jenis_kod', 'status_transaksi')->where('kod', 'B')->value('keterangan'));
    }
}
