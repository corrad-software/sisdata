<?php

namespace Tests\Modules\Ast;

use App\Modules\Ast\Integrasi\AsetFizikalLayer;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetQr;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Services\AsetService;
use App\Modules\Ast\Support\AliranAst;
use App\Modules\Gis\Contracts\MapLayers;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DaftarAsetTest extends AstTestCase
{
    public function test_SRS_AST_01_02_registration_records_full_asset_details_linked_to_the_lot(): void
    {
        $u = $this->unitAset();
        $lot = $this->lot();
        $a = $this->daftar($u, $lot, ['alamat' => 'Jalan Masjid, Damansara', 'latitud' => 3.1001, 'longitud' => 101.6002, 'id_pegawai_bertanggungjawab' => $u->id]);

        $this->assertSame($lot, $a->lot_id);
        $this->assertSame('1008', $a->kod_daerah);
        $this->assertSame('100803', $a->kod_mukim);
        $this->assertSame('baik', $a->status_kondisi);
        $this->assertSame('250000.00', $a->nilai_semasa);
        $this->assertSame($u->id, $a->id_pegawai_bertanggungjawab);
        $r = $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$a->id)->assertOk();
        $r->assertJsonPath('data.alamat', 'Jalan Masjid, Damansara')->assertJsonPath('data.tahun_pembinaan', 2005)->assertJsonPath('data.saiz_keluasan', '450.00')
            ->assertJsonPath('data.pegawai.id', $u->id)->assertJsonPath('data.lot.id', $lot)->assertJsonPath('data.daerah', 'Petaling');
        $this->assertAudited('cipta', ['modul' => 'ast', 'nama_entiti' => 'aset_fizikal', 'id_rekod' => $a->id]);
    }

    public function test_SRS_AST_01_lot_outside_scope_or_missing_is_rejected(): void
    {
        $u = $this->unitAset(['1008']);
        $sepang = $this->lot('100901');
        $this->actingAs($u)->postJson('/api/v1/ast/aset', ['lot_id' => $sepang, 'id_kategori' => $this->kategori()->id, 'nama_aset' => 'X'])->assertStatus(422)->assertJsonValidationErrors('lot_id');
    }

    public function test_SRS_AST_03_categories_are_configurable(): void
    {
        $admin = $this->user(['ast.konfigurasi.lihat', 'ast.konfigurasi.kemas_kini']);
        $this->actingAs($admin)->postJson('/api/v1/ast/kategori', [
            'kod' => 'GERAI', 'nama_kategori' => 'Gerai', 'jenis_lalai' => 'bangunan', 'kitaran_penyelenggaraan_bulan' => 3, 'tempoh_ambang_penilaian_bulan' => 24,
            'tempoh_pemeriksaan_bulan' => 6, 'hayat_guna_tahun' => 15, 'kadar_nilai_sisa' => 5, 'jenis_penyelenggaraan_lalai' => ['pembersihan'],
        ])->assertCreated();
        $id = DB::table('aset_kategori')->where('kod', 'GERAI')->value('id');
        $this->actingAs($admin)->putJson('/api/v1/ast/kategori/'.$id, ['kitaran_penyelenggaraan_bulan' => 4])->assertOk();
        $this->assertSame(4, (int) DB::table('aset_kategori')->where('id', $id)->value('kitaran_penyelenggaraan_bulan'));
        $kods = collect($this->actingAs($admin)->getJson('/api/v1/ast/kategori')->assertOk()->json('data'))->pluck('kod');
        foreach (['BANGUNAN_PEJABAT', 'MASJID_SURAU', 'SEKOLAH', 'KEDAI', 'KOMPLEKS', 'KEDIAMAN', 'KEMUDAHAN_AWAM', 'INFRASTRUKTUR', 'GERAI'] as $k) {
            $this->assertContains($k, $kods);
        }
        $this->actingAs($this->unitAset())->postJson('/api/v1/ast/kategori', ['kod' => 'X'])->assertForbidden();
    }

    public function test_SRS_AST_04_lot_holds_many_assets_and_parent_sub_asset_hierarchy(): void
    {
        $u = $this->unitAset();
        $lot = $this->lot();
        $kompleks = $this->daftar($u, $lot, ['id_kategori' => $this->kategori('KOMPLEKS')->id, 'nama_aset' => 'Bazar Wakaf']);
        $unit1 = $this->daftar($u, $lot, ['id_kategori' => $this->kategori('KEDAI')->id, 'nama_aset' => 'Unit 1', 'id_aset_induk' => $kompleks->id]);
        $this->daftar($u, $lot, ['id_kategori' => $this->kategori('KEDAI')->id, 'nama_aset' => 'Unit 2', 'id_aset_induk' => $kompleks->id]);

        $this->assertSame(3, AsetFizikal::query()->withoutGlobalScope('access')->where('lot_id', $lot)->count());
        $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$kompleks->id)->assertOk()->assertJsonCount(2, 'data.subaset');
        $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$unit1->id)->assertOk()->assertJsonPath('data.induk.id', $kompleks->id);
        // The tree cannot become a cycle.
        $this->actingAs($u)->putJson('/api/v1/ast/aset/'.$kompleks->id, ['id_aset_induk' => $unit1->id])->assertStatus(422)->assertJsonValidationErrors('id_aset_induk');
    }

    public function test_SRS_AST_06_unique_qr_reference_generated_and_never_reused(): void
    {
        $u = $this->unitAset();
        $lot = $this->lot();
        $a = $this->daftar($u, $lot);
        $b = $this->daftar($u, $lot, ['nama_aset' => 'Pagar']);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{12}$/', $a->qr_ref);
        $this->assertNotSame($a->qr_ref, $b->qr_ref);
        $this->assertMatchesRegularExpression('/^AST-1008-\d{4}-00001$/', $a->no_rujukan_aset);
        $this->assertMatchesRegularExpression('/^AST-1008-\d{4}-00002$/', $b->no_rujukan_aset);
        $q = AsetQr::query()->findOrFail($a->qr_ref);
        $this->assertStringEndsWith('/aset/q/'.$a->qr_ref, $q->pautan_awam);
        $this->assertStringNotContainsString($a->id, $q->pautan_awam, 'QR link must not expose the internal id');

        // Regenerating keeps the old reference reserved (inactive), never reassigned or deleted.
        $old = $a->qr_ref;
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$a->id.'/qr/jana-semula', ['sebab' => 'Label rosak'])->assertCreated();
        $this->assertFalse(AsetQr::query()->findOrFail($old)->status_aktif);
        $this->assertNotSame($old, $a->refresh()->qr_ref);
        $this->expectException(QueryException::class);
        DB::table('aset_qr')->where('qr_ref', $old)->delete();
    }

    public function test_SRS_AST_06_archived_asset_qr_stays_reserved(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        app(AsetService::class)->arkib($a, 'dilupuskan', 'Ujian');
        $this->assertTrue(AsetQr::query()->whereKey($a->qr_ref)->exists());
        $this->assertFalse(AsetQr::query()->findOrFail($a->qr_ref)->status_aktif);
        $this->expectException(QueryException::class);
        DB::table('aset_qr')->where('qr_ref', $a->qr_ref)->update(['id_aset' => $this->daftar($u, $a->lot_id)->id]);
    }

    public function test_SRS_AST_13_registered_asset_appears_on_the_gis_layer_at_its_coordinates(): void
    {
        $u = $this->user([...self::UNIT_ASET]);
        $a = $this->daftar($u, $this->lot(), ['latitud' => 3.10012, 'longitud' => 101.60034]);

        $layer = app(AsetFizikalLayer::class);
        $f = $layer->features($u, [101.5, 3.0, 101.7, 3.2]);
        $hit = collect($f['features'])->firstWhere('id', $a->id);
        $this->assertNotNull($hit);
        $this->assertEqualsWithDelta(101.60034, $hit['geometry']['coordinates'][0], 1e-6);
        $this->assertEqualsWithDelta(3.10012, $hit['geometry']['coordinates'][1], 1e-6);
        $this->assertSame('/admin/pro/lot/'.$a->lot_id, $hit['properties']['pautan_lot']);
        $this->assertSame('/admin/ast/aset/'.$a->id, $hit['properties']['pautan']);
        // Filter by category / condition / district.
        $this->assertCount(1, $layer->features($u, null, ['kategori' => 'MASJID_SURAU', 'status_kondisi' => 'baik', 'kod_daerah' => '1008'])['features']);
        $this->assertCount(0, $layer->features($u, null, ['status_kondisi' => 'kritikal'])['features']);
        // Out of scope district → not on the map.
        $this->assertCount(0, $layer->features($this->user(['ast.peta.lihat'], ['1009']))['features']);
        // Fills GIS's mandatory slot "aset_fizikal" (key prefix ast.).
        $this->assertStringStartsWith('ast.', $layer->key());
        $this->assertInstanceOf(MapLayers::class, app(MapLayers::class));
    }

    public function test_SRS_AST_13_asset_without_gps_is_placed_on_its_lot(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot());
        $this->assertNotNull($a->latitud);
        $inside = DB::selectOne('SELECT ST_Contains(ds.geometri, a.koordinat) AS ok FROM aset_fizikal a JOIN data_spatial ds ON ds.lot_id = a.lot_id WHERE a.id = ?', [$a->id]);
        $this->assertTrue((bool) $inside->ok);
    }

    public function test_SRS_AST_02_maintenance_schedules_generated_from_category(): void
    {
        $a = $this->daftar($this->unitAset(), $this->lot());
        $jenis = JadualPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->pluck('jenis_penyelenggaraan')->all();
        $this->assertEqualsCanonicalizing($this->kategori()->jenis_penyelenggaraan_lalai, $jenis);
    }

    public function test_SRS_AST_01_high_value_acquisition_goes_through_wfe(): void
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot(), ['kos_perolehan' => 2_000_000]);
        $this->assertSame('menunggu', $a->status_kelulusan);
        $this->assertCount(1, $this->workflow->of(AliranAst::PEROLEHAN));
        $this->complete(AliranAst::PEROLEHAN, AliranAst::SUBJEK_ASET, $a->id, 'lulus', $u->id);
        $this->assertSame('lulus', $a->refresh()->status_kelulusan);

        $b = $this->daftar($u, $this->lot(), ['kos_perolehan' => 900_000]);
        $this->complete(AliranAst::PEROLEHAN, AliranAst::SUBJEK_ASET, $b->id, 'tolak', $u->id, 'Tiada peruntukan');
        $b->refresh();
        $this->assertSame('ditolak', $b->status_kelulusan);
        $this->assertTrue($b->status_arkib);
    }

    public function test_SRS_AST_01_permission_and_district_scope_enforced(): void
    {
        $owner = $this->unitAset();
        $a = $this->daftar($owner, $this->lot());
        $this->actingAs($this->user([]))->getJson('/api/v1/ast/aset')->assertForbidden();
        $this->actingAs($this->user(['ast.aset.lihat']))->postJson('/api/v1/ast/aset', [])->assertForbidden();
        $sepang = $this->user(['ast.aset.lihat', 'ast.aset.kemas_kini'], ['1009']);
        $this->actingAs($sepang)->getJson('/api/v1/ast/aset/'.$a->id)->assertNotFound();
        $this->actingAs($sepang)->putJson('/api/v1/ast/aset/'.$a->id, ['nama_aset' => 'X'])->assertNotFound();
        $this->assertSame(0, $this->actingAs($sepang)->getJson('/api/v1/ast/aset')->assertOk()->json('meta.total'));
        $this->assertSame(1, $this->actingAs($this->user(['ast.aset.lihat'], ['1008']))->getJson('/api/v1/ast/aset')->assertOk()->json('meta.total'));
    }
}
