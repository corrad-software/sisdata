<?php

namespace Tests\Modules\Ast;

use App\Modules\Ast\Integrasi\AsetFizikalSection;
use App\Modules\Ast\Integrasi\AsetSearchProvider;
use App\Modules\Ast\Integrasi\Kpi\BilanganAset;
use App\Modules\Ast\Integrasi\Kpi\KerjaTertunggak;
use App\Modules\Ast\Integrasi\Kpi\KosPenyelenggaraan;
use App\Modules\Ast\Integrasi\Kpi\NilaiBuku;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetPelupusan;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Support\AliranAst;
use Illuminate\Support\Facades\DB;

class LaporanIntegrasiTest extends AstTestCase
{
    private function tigaAset(): array
    {
        $u = $this->unitAset();
        $a = $this->daftar($u, $this->lot(), ['nama_aset' => 'Masjid A', 'kos_perolehan' => 100000]);
        $b = $this->daftar($u, $this->lot(), ['nama_aset' => 'Kedai B', 'id_kategori' => $this->kategori('KEDAI')->id, 'kos_perolehan' => 50000.25]);
        $c = $this->daftar($u, $this->lot('100901'), ['nama_aset' => 'Pagar C', 'id_kategori' => $this->kategori('STRUKTUR')->id, 'kos_perolehan' => 1000]);
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$b->id.'/kondisi', ['status_kondisi' => 'kritikal'])->assertOk();

        return [$u, $a, $b, $c];
    }

    public function test_SRS_AST_24_inventory_report_by_category_district_condition_and_value(): void
    {
        [$u] = $this->tigaAset();
        $r = $this->actingAs($u)->getJson('/api/v1/ast/laporan/inventori')->assertOk()->json('data');
        $this->assertCount(3, $r['baris']);
        $this->assertSame('151000.25', $r['ringkasan'][1]['nilai']);
        $daerah = collect($r['kumpulan']['daerah'])->keyBy('kod');
        $this->assertSame(['150000.25', 2], [$daerah['1008']['nilai'], $daerah['1008']['bilangan']]);
        $this->assertSame(1, collect($r['kumpulan']['kondisi'])->firstWhere('kod', 'kritikal')['bilangan']);
        $this->assertCount(1, $this->actingAs($u)->getJson('/api/v1/ast/laporan/inventori?kod_daerah=1009')->json('data.baris'));
        $this->assertCount(1, $this->actingAs($u)->getJson('/api/v1/ast/laporan/inventori?id_kategori='.$this->kategori('KEDAI')->id)->json('data.baris'));
        // District officer sees only their district.
        $this->assertCount(1, $this->actingAs($this->user(['ast.laporan.lihat'], ['1009']))->getJson('/api/v1/ast/laporan/inventori')->json('data.baris'));
        $this->actingAs($this->user(['ast.aset.lihat']))->getJson('/api/v1/ast/laporan/inventori')->assertForbidden();
    }

    public function test_SRS_AST_25_portfolio_value_by_category_district_and_year_comparison(): void
    {
        [$u, $a] = $this->tigaAset();
        $kew = $this->user(['ast.aset.lihat', 'ast.sulit.kemas_kini']);
        $y = (int) now()->year;
        $this->actingAs($kew)->postJson('/api/v1/ast/aset/'.$a->id.'/nilai', ['nilai' => 80000, 'tarikh_penilaian' => ($y - 1).'-06-30', 'kaedah_penilaian' => 'laporan_jpph'])->assertCreated();
        $this->actingAs($kew)->postJson('/api/v1/ast/aset/'.$a->id.'/nilai', ['nilai' => 120000, 'tarikh_penilaian' => now()->toDateString(), 'kaedah_penilaian' => 'laporan_jpph'])->assertCreated();
        $r = $this->actingAs($u)->getJson('/api/v1/ast/laporan/nilai_portfolio?bilangan_tahun=2')->assertOk()->json('data');
        $years = collect($r['baris'])->keyBy('tahun');
        $this->assertSame('80000.00', $years[$y - 1]['nilai'], 'end of last year: only the back-dated valuation existed');
        $this->assertSame('171000.25', $years[$y]['nilai']);
        $this->assertSame('91000.25', $years[$y]['perubahan']);
        $this->assertNotEmpty($r['kumpulan']['kategori']);
        $this->assertSame('171000.25', $r['ringkasan'][0]['nilai']);
    }

    public function test_SRS_AST_26_critical_assets_annual_cost_by_asset_and_category_and_export(): void
    {
        [$u, $a, $b] = $this->tigaAset();
        foreach ([[$a, 1000.10], [$a, 2000.20], [$b, 500]] as [$x, $kos]) {
            $id = $this->actingAs($u)->postJson('/api/v1/ast/kerja', ['id_aset' => $x->id, 'jenis_kerja' => 'pembaikan', 'jenis_penyelenggaraan' => 'cat', 'keterangan' => 'k'])->json('data.id');
            $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$id, ['status' => 'selesai', 'kos' => $kos])->assertOk();
        }
        $kritikal = $this->actingAs($u)->getJson('/api/v1/ast/laporan/aset_kritikal')->json('data.baris');
        $this->assertSame(['Kedai B'], array_column($kritikal, 'nama_aset'));
        $k = $this->actingAs($u)->getJson('/api/v1/ast/laporan/kos_penyelenggaraan?tahun='.now()->year)->assertOk()->json('data');
        $this->assertSame('3500.30', $k['ringkasan'][0]['nilai']);
        $this->assertSame(['3000.30', 2], [$k['baris'][0]['kos'], $k['baris'][0]['bilangan_kerja']]);
        $this->assertSame('3000.30', collect($k['kumpulan']['kategori'])->firstWhere('kod', (string) $a->id_kategori)['nilai']);
        foreach (['jadual_penyelenggaraan', 'sejarah_nilai', 'log_imbasan'] as $kod) {
            $this->actingAs($u)->getJson('/api/v1/ast/laporan/'.$kod)->assertOk()->assertJsonStructure(['data' => ['tajuk', 'lajur', 'baris', 'ringkasan']]);
        }
        $csv = $this->actingAs($u)->get('/api/v1/ast/laporan/kos_penyelenggaraan/eksport?tahun='.now()->year)->assertOk();
        $this->assertStringContainsString('Masjid A', $csv->streamedContent());
        $this->assertAudited('eksport', ['modul' => 'ast', 'nama_entiti' => 'laporan', 'id_rekod' => 'kos_penyelenggaraan']);
        $this->actingAs($this->user(['ast.laporan.lihat']))->get('/api/v1/ast/laporan/inventori/eksport')->assertForbidden();
    }

    public function test_SRS_AST_24_dashboard_kpis_respect_scope(): void
    {
        [$u, $a, $b, $c] = $this->tigaAset();
        $j = JadualPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $c->id)->first();
        $j->forceFill(['tarikh_jatuh_tempoh' => now()->subDays(3)->toDateString()])->save();
        $id = $this->actingAs($u)->postJson('/api/v1/ast/kerja', ['id_aset' => $a->id, 'jenis_kerja' => 'pembaikan', 'jenis_penyelenggaraan' => 'cat', 'keterangan' => 'k'])->json('data.id');
        $this->actingAs($u)->putJson('/api/v1/ast/kerja/'.$id, ['status' => 'selesai', 'kos' => 700])->assertOk();

        $all = $this->user(['ast.laporan.lihat']);
        $petaling = $this->user(['ast.laporan.lihat'], ['1008']);
        $bil = app(BilanganAset::class);
        $this->assertSame(3, $bil->compute($all)['nilai']);
        $this->assertSame(2, $bil->compute($petaling)['nilai']);
        $this->assertSame(1, collect($bil->compute($all)['pecahan'])->firstWhere('kod', 'kondisi:kritikal')['nilai']);
        $this->assertSame(1, $bil->records($all, [], 'kondisi:kritikal')['jumlah']);
        $this->assertGreaterThan(0, app(NilaiBuku::class)->compute($all)['nilai']);
        $this->assertEqualsWithDelta(700.0, app(KosPenyelenggaraan::class)->compute($all)['nilai'], 0.001);
        $this->assertSame(1, app(KerjaTertunggak::class)->compute($all)['nilai']);
        $this->assertSame(0, app(KerjaTertunggak::class)->compute($petaling)['nilai']);
        // Registered with MOD-DSH (drill-down endpoint resolves the key).
        $this->actingAs($this->user(['dsh.dashboard.lihat', 'ast.laporan.lihat']))->getJson('/api/v1/dsh/kpi/ast.bilangan_aset/senarai')->assertOk();
    }

    public function test_SRS_AST_01_disposal_through_wfe_archives_without_deleting(): void
    {
        $u = $this->unitAset();
        $lot = $this->lot();
        $induk = $this->daftar($u, $lot, ['nama_aset' => 'Kompleks Lama', 'id_kategori' => $this->kategori('KOMPLEKS')->id]);
        $sub = $this->daftar($u, $lot, ['nama_aset' => 'Unit 1', 'id_aset_induk' => $induk->id]);
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$induk->id.'/pelupusan', ['jenis' => 'roboh', 'sebab' => 'Struktur tidak selamat menurut laporan jurutera'])->assertCreated();
        $this->actingAs($u)->postJson('/api/v1/ast/aset/'.$induk->id.'/pelupusan', ['jenis' => 'roboh', 'sebab' => 'Permohonan kedua serentak'])->assertStatus(422);
        $p = AsetPelupusan::query()->withoutGlobalScope('access')->where('id_aset', $induk->id)->sole();
        $this->assertCount(1, $this->workflow->of(AliranAst::PELUPUSAN));
        $this->assertFalse($induk->refresh()->status_arkib, 'nothing changes before approval');

        $this->complete(AliranAst::PELUPUSAN, AliranAst::SUBJEK_PELUPUSAN, (string) $p->id, 'lulus', $u->id, 'Lulus mesyuarat');
        foreach ([$induk, $sub] as $x) {
            $x->refresh();
            $this->assertTrue($x->status_arkib);
            $this->assertSame('dirobohkan', $x->status_aset);
        }
        $this->assertSame(2, AsetFizikal::query()->withoutGlobalScope('access')->where('lot_id', $lot)->count(), 'archived, not deleted');
        $this->assertSame(0, JadualPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $induk->id)->where('status', 'aktif')->count());
        $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$induk->id)->assertOk()->assertJsonPath('data.status_arkib', true);
        $this->actingAs($u)->putJson('/api/v1/ast/aset/'.$induk->id, ['nama_aset' => 'X'])->assertStatus(422);
        $this->actingAs($u)->putJson('/api/v1/ast/aset/'.$sub->id, ['status_aset' => 'dilupuskan'])->assertStatus(422);
        $this->actingAs($this->user(['ast.aset.lihat', 'ast.aset.kemas_kini']))->postJson('/api/v1/ast/aset/'.$sub->id.'/pelupusan', ['jenis' => 'pelupusan', 'sebab' => 'xxxxxxxxxxxx'])->assertForbidden();
    }

    public function test_SRS_AST_04_asset_linked_to_pjk_premise_without_duplication(): void
    {
        $u = $this->unitAset();
        $lot = $this->lot();
        $premis = DB::table('premis_aset')->insertGetId([
            'lot_id' => $lot, 'kod_premis' => 'PRM-T-1', 'nama_premis' => 'Kedai Wakaf 1', 'jenis_aset' => 'unit_kedai', 'kategori_hartanah' => 'premis_perniagaan',
            'status_premis' => 'disewa', 'kod_daerah' => '1008', 'kod_mukim' => '100803', 'klasifikasi' => 'dalaman', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame([$premis], array_column($this->actingAs($u)->getJson('/api/v1/ast/premis?lot_id='.$lot)->json('data'), 'id'));
        $a = $this->daftar($u, $lot, ['id_kategori' => $this->kategori('KEDAI')->id, 'nama_aset' => 'Kedai Wakaf 1', 'id_premis_pjk' => $premis]);
        $this->actingAs($u)->getJson('/api/v1/ast/aset/'.$a->id)->assertJsonPath('data.premis_pjk.kod_premis', 'PRM-T-1');
        $this->actingAs($u)->postJson('/api/v1/ast/aset', ['lot_id' => $lot, 'id_kategori' => $a->id_kategori, 'nama_aset' => 'Dup', 'id_premis_pjk' => $premis])->assertStatus(422)->assertJsonValidationErrors('id_premis_pjk');
        $this->actingAs($u)->postJson('/api/v1/ast/aset', ['lot_id' => $this->lot(), 'id_kategori' => $a->id_kategori, 'nama_aset' => 'Lot lain', 'id_premis_pjk' => $premis])->assertStatus(422);
        $this->assertSame([], $this->actingAs($u)->getJson('/api/v1/ast/premis?lot_id='.$lot)->json('data'));
    }

    public function test_SRS_AST_13_import_interim_gis_points_once(): void
    {
        $admin = $this->unitAset();
        $lot = $this->lot();
        DB::statement("INSERT INTO gis_aset_titik (id_lot, nama, kategori, status_keadaan, kod_daerah, kod_mukim, klasifikasi, geom, created_at, updated_at) VALUES (?, 'Dewan Lama', 'bangunan', 'perlu_perhatian', '1008', '100803', 'dalaman', ST_SetSRID(ST_MakePoint(101.6003, 3.1002), 4326), now(), now())", [$lot]);
        DB::statement("INSERT INTO gis_aset_titik (id_lot, nama, kategori, status_keadaan, klasifikasi, geom, created_at, updated_at) VALUES (NULL, 'Tanpa lot', 'lain', 'baik', 'dalaman', ST_SetSRID(ST_MakePoint(101.6, 3.1), 4326), now(), now())");
        $this->artisan('ast:import-gis-titik')->assertSuccessful();
        $this->artisan('ast:import-gis-titik')->assertSuccessful();
        $rows = AsetFizikal::query()->withoutGlobalScope('access')->whereNotNull('id_sumber_gis')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(['Dewan Lama', 'perlu_perhatian', $lot], [$rows[0]->nama_aset, $rows[0]->status_kondisi, $rows[0]->lot_id]);
        $this->assertEqualsWithDelta(101.6003, (float) $rows[0]->longitud, 1e-6);
    }

    public function test_SRS_AST_02_profile_section_search_and_timeline_respect_scope(): void
    {
        [$u, $a, $b] = $this->tigaAset();
        $sec = app(AsetFizikalSection::class);
        $this->assertSame(['aset_fizikal', 'ruang'], [$sec->key(), $sec->tab()]);
        $out = $sec->forLot($b->lot_id, $u);
        $this->assertSame('danger', $out['flags'][0]['level']);
        $this->assertNull($sec->forLot($b->lot_id, $this->user([])));
        $this->assertSame([], $sec->forLot($b->lot_id, $this->user(['ast.aset.lihat'], ['1009']))['items']);
        $this->assertNotEmpty($sec->timeline($a->lot_id, $u));

        $s = app(AsetSearchProvider::class);
        $this->assertSame([$a->id], array_column($s->search('Masjid A', $u), 'id'));
        $this->assertSame([$a->id], array_column($s->search($a->qr_ref, $u), 'id'));
        $this->assertSame([], $s->search('Masjid A', $this->user(['ast.aset.lihat'], ['1009'])));
        $this->assertSame('/admin/ast/aset/'.$a->id, $s->link($a->id, $u));
        $this->assertNull($s->link('bukan-uuid', $u));
    }
}
