<?php

namespace Tests\Modules\Tsu;

use App\Models\User;
use App\Modules\Tsu\Events\LotBaharuDicipta;
use App\Modules\Tsu\Models\LantikanJurukur;
use App\Modules\Tsu\Models\PanelJurukur;
use App\Modules\Tsu\Models\PemindahanRekod;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Profile\UrusanTanahSection;
use App\Modules\Tsu\Support\AliranTsu;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class PecahSempadanTest extends TsuTestCase
{
    private User $officer;

    private User $kb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->officer = $this->user(self::PEGAWAI);
        $this->kb = $this->user(['wfe.tugasan.lulus']);
    }

    /** @param list<array{0: float, 1: string}> $parts [dx offset, no_lot] */
    private function sampaiLotBaharu(UrusanTanah $u, array $parts): void
    {
        $this->sampaiKe($u, 'jemputan_ukur', $this->officer, $this->kb);
        $j = PanelJurukur::query()->create(['nama_firma' => 'Firma Ukur', 'no_lesen_jurukur' => 'JTB-'.Str::random(5), 'nama_jurukur_bertanggungjawab' => 'J', 'status_panel' => 'aktif', 'tarikh_tamat_lesen' => today()->addYear()]);
        $l = new LantikanJurukur;
        $l->forceFill(['urusan_id' => $u->id, 'jurukur_id' => $j->id, 'no_surat_setuju_terima' => 'SST-9', 'nilai_kontrak' => '8000.00', 'tarikh_mula' => today(), 'tempoh_siap_hari' => 30, 'tarikh_jangka_siap' => today()->addDays(30), 'skop_kerja' => 'Ukur', 'status' => 'aktif'])->save();
        $this->selesaikan($u, $this->officer, $this->kb); // jemputan_ukur
        $this->actingAs($this->officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertUnprocessable(); // no accepted result yet

        [$lat, $lng] = self::PUSAT_PETALING;
        $features = [];
        foreach ($parts as [$dx, $no]) {
            $c = $lng + $dx;
            $features[] = ['type' => 'Feature', 'properties' => ['no_lot' => $no], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[$c - 0.0004, $lat - 0.0009], [$c + 0.0004, $lat - 0.0009], [$c + 0.0004, $lat + 0.0009], [$c - 0.0004, $lat + 0.0009], [$c - 0.0004, $lat - 0.0009]]]]];
        }
        $gis = $this->user(self::GIS);
        $rows = $this->actingAs($gis)->postJson('/api/v1/tsu/urusan/'.$u->id.'/hasil-ukur', ['format_fail' => 'geojson', 'sistem_koordinat' => 4326, 'geojson' => json_encode(['type' => 'FeatureCollection', 'features' => $features]), 'lantikan_id' => $l->id])->assertCreated()->json('data');
        $gis2 = $this->user(self::GIS);
        foreach ($rows as $r) {
            $this->actingAs($gis2)->postJson('/api/v1/tsu/hasil-ukur/'.$r['id'].'/sahkan', ['status_pengesahan' => 'diterima'])->assertOk();
        }
        $this->sampaiKe($u, 'daftar_lot_baharu', $this->officer, $this->kb);
    }

    public function test_SRS_TSU_19_approved_subdivision_with_verified_survey_creates_new_lots_with_geometry(): void
    {
        Event::fake([LotBaharuDicipta::class]);
        $asal = $this->lot();
        $u = $this->daftar($this->officer, 'pecah_sempadan', [$asal]);
        $this->sampaiLotBaharu($u, [[-0.0005, 'PT 7001'], [0.0005, 'PT 7002']]);
        $this->selesaikan($u, $this->officer, $this->kb);

        $new = DB::table('urusan_lot')->where('urusan_id', $u->id)->where('peranan_lot', 'baharu')->pluck('lot_id')->all();
        $this->assertCount(2, $new);
        $lots = DB::table('lot_tanah')->whereIn('id', $new)->orderBy('no_lot')->get();
        $this->assertSame(['PT 7001', 'PT 7002'], $lots->pluck('no_lot')->all());
        $this->assertSame(['100803', 'aktif', 'hartanah', 'MOD-TSU'], [$lots[0]->kod_mukim, $lots[0]->status_lot, $lots[0]->bahagian_pemilik, $lots[0]->sumber_sistem]);
        $this->assertEqualsWithDelta(17700, (float) $lots[0]->keluasan, 300);
        $spatial = DB::table('data_spatial')->whereIn('lot_id', $new)->where('semasa', true)->get();
        $this->assertCount(2, $spatial);
        $this->assertSame('disahkan_juruukur', $spatial[0]->status_pengesahan);
        $this->assertSame(1, DB::table('versi_rekod_induk')->where('id_rekod', $new[0])->count(), 'versi 1 rekod induk');
        $this->assertSame('arkib', DB::table('lot_tanah')->where('id', $asal)->value('status_lot'));
        $this->assertSame('pemindahan_rekod', $this->semasa($u)->kod);
        Event::assertDispatched(LotBaharuDicipta::class, fn ($e) => $e->lotAsal === [$asal] && count($e->lotBaharu) === 2);
        // New lots are reachable through MOD-PRO's registry.
        $this->actingAs($this->user(['pro.lot.lihat']))->getJson('/api/v1/pro/lot/'.$new[0])->assertOk();
    }

    public function test_SRS_TSU_19_amalgamation_of_two_lots_creates_one_new_lot_and_archives_both(): void
    {
        [$a, $b] = [$this->lot(d: 0.0005), $this->lot(pusat: [3.1000, 101.6010], d: 0.0005)];
        $u = $this->daftar($this->officer, 'penyatuan', [$a, $b]);
        $this->sampaiLotBaharu($u, [[0.0005, 'PT 8001']]);
        $this->selesaikan($u, $this->officer, $this->kb);
        $new = DB::table('urusan_lot')->where('urusan_id', $u->id)->where('peranan_lot', 'baharu')->pluck('lot_id')->all();
        $this->assertCount(1, $new);
        $this->assertSame(['arkib', 'arkib'], DB::table('lot_tanah')->whereIn('id', [$a, $b])->pluck('status_lot')->all());
        $this->assertSame(2, DB::table('lot_sejarah_pecahan')->where('id_lot_baharu', $new[0])->count());
    }

    public function test_SRS_TSU_20_records_move_to_new_lot_only_after_two_level_confirmation_by_two_other_officers(): void
    {
        $asal = $this->lot();
        $kontrak = (string) Str::uuid();
        DB::table('kontrak_pajakan')->insert(['id' => $kontrak, 'lot_id' => $asal, 'no_kontrak' => 'PJK/2026/0001', 'status' => 'aktif', 'kod_daerah' => '1008', 'created_at' => now(), 'updated_at' => now()]);
        $akaun = (string) Str::uuid();
        DB::table('obligasi_cukai')->insert(['id' => $akaun, 'lot_id' => $asal, 'jenis_cukai' => 'tanah', 'pihak_pengeluar' => 'ptd', 'kod_agensi' => 'PTDPTG', 'no_akaun_cukai' => 'CT-1', 'kod_daerah' => '1008', 'kod_mukim' => '100803', 'created_at' => now(), 'updated_at' => now()]);
        $bil = (string) Str::uuid();
        DB::table('cukai')->insert(['id' => $bil, 'lot_id' => $asal, 'obligasi_id' => $akaun, 'jenis' => 'tanah', 'no_bil' => 'B-1', 'jumlah' => 100, 'created_at' => now(), 'updated_at' => now()]);

        $u = $this->daftar($this->officer, 'pecah_sempadan', [$asal]);
        $this->sampaiLotBaharu($u, [[-0.0005, 'PT 9001'], [0.0005, 'PT 9002']]);
        $mapper = $this->user(self::PEGAWAI);
        $this->actingAs($mapper)->postJson('/api/v1/tsu/urusan/'.$u->id.'/pemindahan', ['pemetaan' => [['jenis_rekod' => 'kontrak_pajakan', 'id_rekod' => $kontrak, 'id_lot_asal' => $asal, 'id_lot_baharu' => $asal]]])->assertUnprocessable(); // no new lots yet
        $this->selesaikan($u, $this->officer, $this->kb); // creates the lots
        [$n1, $n2] = DB::table('urusan_lot')->where('urusan_id', $u->id)->where('peranan_lot', 'baharu')->orderBy('id')->pluck('lot_id')->all();

        $screen = $this->actingAs($mapper)->getJson('/api/v1/tsu/urusan/'.$u->id.'/pemindahan')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['kontrak_pajakan', 'obligasi_cukai'], array_column($screen['lot_asal'][0]['rekod'], 'jenis_rekod'));
        $this->assertSame('PJK/2026/0001 (aktif)', collect($screen['lot_asal'][0]['rekod'])->firstWhere('jenis_rekod', 'kontrak_pajakan')['label']);
        $this->assertCount(2, $screen['lot_baharu']);
        $this->actingAs($this->user(['tsu.urusan.lihat']))->postJson('/api/v1/tsu/urusan/'.$u->id.'/pemindahan', ['pemetaan' => []])->assertForbidden();
        $this->actingAs($mapper)->postJson('/api/v1/tsu/urusan/'.$u->id.'/pemindahan', ['pemetaan' => [['jenis_rekod' => 'kontrak_pajakan', 'id_rekod' => (string) Str::uuid(), 'id_lot_asal' => $asal, 'id_lot_baharu' => $n1]]])->assertUnprocessable();
        $this->actingAs($mapper)->postJson('/api/v1/tsu/urusan/'.$u->id.'/pemindahan', ['pemetaan' => [
            ['jenis_rekod' => 'kontrak_pajakan', 'id_rekod' => $kontrak, 'id_lot_asal' => $asal, 'id_lot_baharu' => $n1],
            ['jenis_rekod' => 'obligasi_cukai', 'id_rekod' => $akaun, 'id_lot_asal' => $asal, 'id_lot_baharu' => $n2],
        ]])->assertOk();
        $this->assertSame($asal, DB::table('kontrak_pajakan')->where('id', $kontrak)->value('lot_id'), 'belum dipindah sebelum pengesahan');
        $this->actingAs($this->officer)->postJson('/api/v1/tsu/urusan/'.$u->id.'/peringkat/selesai')->assertUnprocessable(); // pemindahan draf
        $instance = $this->actingAs($mapper)->postJson('/api/v1/tsu/urusan/'.$u->id.'/pemindahan/hantar')->assertOk()->json('data.id_aliran_kerja');
        $this->assertCount(1, $this->workflow->of(AliranTsu::PEMINDAHAN));

        // Same officer at both levels → rejected, nothing moved.
        $p1 = $this->user([]);
        $this->step($instance, AliranTsu::PEMINDAHAN, 'pengesahan_1', 'lulus', $p1->id, 'Semak 1');
        $this->step($instance, AliranTsu::PEMINDAHAN, 'pengesahan_2', 'lulus', $p1->id, 'Semak 2');
        $this->complete(AliranTsu::PEMINDAHAN, AliranTsu::SUBJEK_PEMINDAHAN, $u->id, 'lulus', $p1->id, 'OK', $instance);
        $this->assertSame(['ditolak'], PemindahanRekod::query()->where('urusan_id', $u->id)->where('id_aliran_kerja', $instance)->pluck('status_pemindahan')->unique()->values()->all());
        $this->assertSame($asal, DB::table('kontrak_pajakan')->where('id', $kontrak)->value('lot_id'));

        // Re-map, two different confirmers → executed.
        $this->actingAs($mapper)->postJson('/api/v1/tsu/urusan/'.$u->id.'/pemindahan', ['pemetaan' => [
            ['jenis_rekod' => 'kontrak_pajakan', 'id_rekod' => $kontrak, 'id_lot_asal' => $asal, 'id_lot_baharu' => $n1],
            ['jenis_rekod' => 'obligasi_cukai', 'id_rekod' => $akaun, 'id_lot_asal' => $asal, 'id_lot_baharu' => $n2],
        ]])->assertOk();
        $instance = $this->actingAs($mapper)->postJson('/api/v1/tsu/urusan/'.$u->id.'/pemindahan/hantar')->assertOk()->json('data.id_aliran_kerja');
        $p2 = $this->user([]);
        $this->step($instance, AliranTsu::PEMINDAHAN, 'pengesahan_1', 'lulus', $p1->id, 'Semak 1');
        $this->step($instance, AliranTsu::PEMINDAHAN, 'pengesahan_2', 'lulus', $p2->id, 'Semak 2');
        $this->complete(AliranTsu::PEMINDAHAN, AliranTsu::SUBJEK_PEMINDAHAN, $u->id, 'lulus', $p2->id, 'OK', $instance);
        $rows = PemindahanRekod::query()->where('id_aliran_kerja', $instance)->get();
        $this->assertSame(['dilaksanakan'], $rows->pluck('status_pemindahan')->unique()->values()->all());
        $this->assertSame([$p1->id, $p2->id], [$rows[0]->id_pengesah_1, $rows[0]->id_pengesah_2]);
        $this->assertSame($n1, DB::table('kontrak_pajakan')->where('id', $kontrak)->value('lot_id'));
        $this->assertSame($n2, DB::table('obligasi_cukai')->where('id', $akaun)->value('lot_id'));
        $this->assertSame($n2, DB::table('cukai')->where('id', $bil)->value('lot_id'), 'bil cukai ikut akaun');
        $this->assertAudited('pindah_lot', ['nama_entiti' => 'kontrak_pajakan', 'id_rekod' => $kontrak, 'modul' => 'pjk']);

        $this->selesaikan($u, $this->officer, $this->kb);
        $this->assertSame('selesai', $u->refresh()->status);
        $rep = $this->actingAs($this->user(['tsu.laporan.lihat']))->getJson('/api/v1/tsu/laporan/ringkasan')->assertOk()->json('data');
        $this->assertCount(2, $rep['rekod_dipindah']);
        $this->assertCount(2, $rep['sejarah_pecahan']);
    }

    public function test_SRS_TSU_21_two_way_lineage_on_both_lots_timeline_and_original_kept_archived(): void
    {
        $asal = $this->lot();
        $u = $this->daftar($this->officer, 'pecah_bahagian', [$asal]);
        $this->sampaiLotBaharu($u, [[0.0, 'PT 5501']]);
        $this->selesaikan($u, $this->officer, $this->kb);
        $new = (string) DB::table('urusan_lot')->where('urusan_id', $u->id)->where('peranan_lot', 'baharu')->value('lot_id');

        $section = app(UrusanTanahSection::class);
        $tAsal = collect($section->timeline($asal, $this->officer))->where('type', 'sejarah_lot')->values();
        $tBaru = collect($section->timeline($new, $this->officer))->where('type', 'sejarah_lot')->values();
        $this->assertCount(1, $tAsal);
        $this->assertCount(1, $tBaru);
        $this->assertSame('/admin/pro/lot/'.$new, $tAsal[0]['link']);
        $this->assertSame('/admin/pro/lot/'.$asal, $tBaru[0]['link']);
        $this->assertStringContainsString('PT 5501', $tAsal[0]['title']);
        $flags = $section->forLot($asal, $this->officer)['flags'];
        $this->assertContains('Lot asal — telah dipecah / disatukan dan diarkibkan', array_column($flags, 'label'));
        // Through MOD-PRO's 360° profile timeline too.
        $p = $this->actingAs($this->user([...self::PEGAWAI, 'pro.lot.lihat']))->getJson('/api/v1/pro/lot/'.$new.'/profil')->assertOk();
        $this->assertContains('sejarah_lot', array_column($p->json('data.garis_masa'), 'type'));

        // Original lot keeps all data, archived; lineage and lot cannot be deleted.
        $row = DB::table('lot_tanah')->where('id', $asal)->first();
        $this->assertSame(['arkib', 'aktif'], [$row->status_lot, $row->status_sebelum_arkib]);
        $this->assertSame(1, DB::table('data_spatial')->where('lot_id', $asal)->count());
        foreach ([fn () => DB::table('lot_sejarah_pecahan')->delete(), fn () => DB::table('lot_tanah')->where('id', $asal)->update(['deleted_at' => now()]), fn () => DB::table('lot_tanah')->where('id', $new)->update(['deleted_at' => now()])] as $w) {
            try {
                DB::transaction($w);
                $this->fail('Dipadam');
            } catch (QueryException $e) {
                $this->assertStringContainsString('P0001', $e->getMessage());
            }
        }
    }
}
