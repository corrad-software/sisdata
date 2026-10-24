<?php

namespace Tests\Modules\Kes;

use App\Modules\Dms\Events\DocumentStored;
use App\Modules\Kes\Models\DokumenKes;
use App\Modules\Kes\Models\KosGuaman;
use App\Modules\Kes\Models\TindakanSusulan;
use App\Modules\Kes\Services\DokumenService;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Services\Profil360;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class KosKeputusanTest extends KesTestCase
{
    public function test_SRS_KES_12_legal_documents_through_dms_with_category_version_and_logged_download(): void
    {
        $k = $this->kes([$this->lot()]);
        $u = $this->user();
        $id1 = $this->actingAs($u)->post('/api/v1/kes/kes/'.$k->id.'/dokumen', ['fail' => UploadedFile::fake()->create('pliding.pdf', 20, 'application/pdf'), 'kategori_dokumen' => 'pliding'])->assertCreated()->json('data.id');
        $this->actingAs($u)->post('/api/v1/kes/kes/'.$k->id.'/dokumen', ['fail' => UploadedFile::fake()->create('pliding.pdf', 22, 'application/pdf'), 'kategori_dokumen' => 'pliding'])->assertCreated()->assertJsonPath('data.versi', 2);
        $doc = DokumenKes::query()->findOrFail($id1);
        $stored = $this->documents->docs[$doc->id_dokumen_dms];
        $this->assertSame('kes_mahkamah', $stored['owner_type']);
        $this->assertSame($k->id, $stored['owner_id']);

        $url = $this->actingAs($u)->getJson('/api/v1/kes/kes/'.$k->id.'/dokumen/'.$doc->id.'/muat-turun')->assertOk()->json('data.url');
        $this->assertNotEmpty($url);
        $this->assertDatabaseHas('kes_log_capaian', ['kes_mahkamah_id' => $k->id, 'id_dokumen' => $doc->id_dokumen_dms, 'jenis_tindakan' => 'muat_turun', 'id_pengguna' => $u->id]);
        $this->assertAudited('muat_turun', ['modul' => 'kes']);

        // DMS still decides: a refused document is a 403 and logged as refused.
        $this->documents->deny[] = $u->id;
        $this->actingAs($u)->getJson('/api/v1/kes/kes/'.$k->id.'/dokumen/'.$doc->id.'/muat-turun')->assertForbidden();
        $this->assertDatabaseHas('kes_log_capaian', ['id_dokumen' => $doc->id_dokumen_dms, 'jenis_tindakan' => 'ditolak']);

        // A new version uploaded through the generic DMS panel updates the case index.
        event(new DocumentStored($doc->id_dokumen_dms, 3, 'kes_mahkamah', $k->id, 'rahsia'));
        $this->assertSame(3, $doc->refresh()->versi);
    }

    public function test_SRS_KES_15_costs_by_category_cumulative_with_bcmath(): void
    {
        $k = $this->kes([$this->lot()]);
        $u = $this->user();
        foreach ([['fi_peguam', '0.10'], ['fi_peguam', '0.20'], ['kos_mahkamah', '1500.55'], ['kos_berkaitan', '99999999.99']] as [$kat, $amaun]) {
            $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/kos', ['kategori_kos' => $kat, 'amaun' => $amaun, 'tarikh' => now()->toDateString()])->assertCreated();
        }
        $this->assertSame('100001500.84', $k->refresh()->kos_guaman, 'exact decimal sum (0.1 + 0.2 is 0.30)');
        $cat = collect($this->actingAs($u)->getJson('/api/v1/kes/kes/'.$k->id)->json('data.kos_mengikut_kategori'))->keyBy('kod');
        $this->assertSame('0.30', $cat['fi_peguam']['jumlah']);
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/kos', ['kategori_kos' => 'fi_peguam', 'amaun' => '1.234', 'tarikh' => now()->toDateString()])->assertUnprocessable();
    }

    public function test_SRS_KES_15_costs_appear_in_each_linked_lot_cost_chart_with_the_right_amount(): void
    {
        $lots = [$this->lot(), $this->lot()];
        $k = $this->kes($lots);
        $u = $this->user([...KesTestCase::PEGAWAI, 'pro.lot.lihat']);
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/kos', ['kategori_kos' => 'fi_peguam', 'amaun' => '12500.40', 'tarikh' => now()->toDateString()])->assertCreated();
        foreach ($lots as $lotId) {
            $profile = app(Profil360::class)->build(LotTanah::query()->withoutGlobalScope('access')->findOrFail($lotId), $u);
            $this->assertTrue($profile['kewangan']['tersedia']);
            $this->assertEqualsWithDelta(12500.40, array_sum(array_column($profile['kewangan']['tahunan'], 'kos_guaman')), 0.001);
            $this->assertEqualsWithDelta(12500.40, $profile['kewangan']['jumlah']['kos'], 0.001);
        }
    }

    public function test_SRS_KES_15_fee_payment_approved_in_wfe_then_paid_and_verified_in_grp(): void
    {
        $k = $this->kes([$this->lot()]);
        $u = $this->user();
        $kos = $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/kos', ['kategori_kos' => 'fi_peguam', 'amaun' => '8000.00', 'tarikh' => now()->toDateString(), 'no_resit' => 'INV-1'])->json('data.id');
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/kos/'.$kos.'/mohon-bayaran')->assertOk()->assertJsonPath('data.status_bayaran', 'menunggu_kelulusan');
        $item = KosGuaman::query()->findOrFail($kos);
        $this->assertSame('kes.bayaran_kos', $this->workflow->started[$item->id_instans_aliran]['flow']);
        $this->actingAs($u)->putJson('/api/v1/kes/kes/'.$k->id.'/kos/'.$kos, ['amaun' => '9000'])->assertUnprocessable();

        $this->selesaiAliran('kes.bayaran_kos', 'kos_guaman', $kos, (string) $item->id_instans_aliran);
        $item->refresh();
        $this->assertSame('dihantar_grp', $item->status_bayaran);
        $this->assertSame('8000.00', $this->grp->sent[$item->rujukan_grp]['amaun']);
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/kos/'.$kos.'/semak-grp')->assertOk()->assertJsonPath('data.status_bayaran', 'dibayar');

        // Rejected payment drops out of the cumulative total.
        $kos2 = $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/kos', ['kategori_kos' => 'kos_mahkamah', 'amaun' => '500', 'tarikh' => now()->toDateString()])->json('data.id');
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/kos/'.$kos2.'/mohon-bayaran')->assertOk();
        $i2 = KosGuaman::query()->findOrFail($kos2);
        $this->selesaiAliran('kes.bayaran_kos', 'kos_guaman', $kos2, (string) $i2->id_instans_aliran, 'tolak');
        $this->assertSame('ditolak', $i2->refresh()->status_bayaran);
        $this->assertSame('8000.00', $k->refresh()->kos_guaman);
    }

    public function test_SRS_KES_16_17_decision_recorded_and_follow_ups_become_wfe_tasks(): void
    {
        $officer = $this->user();
        $k = $this->kes([$this->lot()], null, ['id_pegawai_bertanggungjawab' => $officer->id]);
        $doc = app(DokumenService::class)->muatNaik($k, UploadedFile::fake()->create('penghakiman.pdf', 10, 'application/pdf'), 'penghakiman', $officer);
        $res = $this->actingAs($officer)->postJson('/api/v1/kes/kes/'.$k->id.'/keputusan', [
            'jenis_keputusan' => 'menang', 'tarikh_keputusan' => now()->toDateString(), 'ringkasan_perintah' => 'Defendan dikehendaki mengosongkan tanah dalam 30 hari.',
            'implikasi' => 'MAIS mendapat semula milikan kosong.', 'id_dokumen_penghakiman' => $doc->id_dokumen_dms,
        ])->assertCreated();
        $this->assertSame('keputusan', $k->refresh()->status);
        $this->assertNotNull($k->tarikh_keputusan);
        $tasks = collect($res->json('data.tindakan'));
        $this->assertEqualsCanonicalizing(['penguatkuasaan_keputusan', 'pengemaskinian_hak_milik'], $tasks->pluck('jenis_tindakan')->all());
        $flows = collect($this->workflow->started)->where('flow', 'kes.tindakan_susulan');
        $this->assertCount(2, $flows);
        $this->assertSame('dalam_tindakan', $tasks[0]['status']);
        $this->assertCount(2, $this->notifier->of('kes.tindakan_susulan'));

        $t = TindakanSusulan::query()->findOrFail($tasks[0]['id']);
        $this->selesaiAliran('kes.tindakan_susulan', 'kes_tindakan_susulan', $t->id, (string) $t->id_tugasan_workflow);
        $this->assertSame('selesai', $t->refresh()->status);

        $this->actingAs($officer)->postJson('/api/v1/kes/kes/'.$k->id.'/keputusan', ['jenis_keputusan' => 'menang', 'tarikh_keputusan' => now()->toDateString(), 'ringkasan_perintah' => 'x', 'id_dokumen_penghakiman' => 'bukan-dokumen-kes'])->assertUnprocessable();
    }

    public function test_SRS_KES_18_title_update_proposed_to_hkm_with_case_number_and_order_cross_reference(): void
    {
        $lot = $this->lot();
        $u = $this->user();
        $k = $this->kes([$lot], null, ['id_pegawai_bertanggungjawab' => $u->id]);
        $doc = app(DokumenService::class)->muatNaik($k, UploadedFile::fake()->create('perintah.pdf', 10, 'application/pdf'), 'perintah_mahkamah', $u);
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/keputusan', ['jenis_keputusan' => 'menang', 'tarikh_keputusan' => now()->toDateString(), 'ringkasan_perintah' => 'Kaveat dipotong.', 'tindakan' => ['pengemaskinian_hak_milik']])->assertCreated();
        $t = TindakanSusulan::query()->where('kes_mahkamah_id', $k->id)->where('jenis_tindakan', 'pengemaskinian_hak_milik')->firstOrFail();
        $this->assertSame($lot, $t->id_lot_terjejas);

        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/tindakan/'.$t->id.'/kemas-kini-rekod', [
            'no_hak_milik' => 'GRN 12345', 'kod_mukim' => '100803', 'jenis_perubahan' => 'sekatan', 'id_dokumen_perintah' => $doc->id_dokumen_dms,
            'data' => ['tindakan' => 'lupus', 'id_sekatan' => 7, 'tarikh_lupus' => now()->toDateString()],
        ])->assertCreated()->assertJsonPath('data.no_kes_mahkamah', $k->no_kes);
        $p = $this->titles->proposals[0];
        $this->assertSame('mod_kes', $p['sumber']);
        $this->assertStringContainsString($k->no_kes, $p['keterangan']);
        $this->assertStringContainsString($doc->id_dokumen_dms, $p['keterangan']);
        $this->assertSame($k->id, $p['data']['id_kes_mahkamah']);
        $row = DB::table('kes_pengemaskinian_rekod')->where('kes_mahkamah_id', $k->id)->first();
        $this->assertSame($doc->id_dokumen_dms, $row->id_dokumen_perintah);
        $this->assertSame('cadangan-1', $row->id_cadangan_hkm);

        // The order document must belong to the case.
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/tindakan/'.$t->id.'/kemas-kini-rekod', ['no_hak_milik' => 'GRN 1', 'jenis_perubahan' => 'status', 'id_dokumen_perintah' => 'lain', 'data' => []])->assertUnprocessable();
    }

    public function test_appeal_by_mais_needs_wfe_approval_and_settlement_becomes_the_decision(): void
    {
        $u = $this->user();
        $k = $this->kes([$this->lot()]);
        $r = $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/rayuan', ['pihak_merayu' => 'mais', 'alasan' => 'Hakim terkhilaf', 'tarikh_had_failan' => now()->addDays(14)->toDateString(), 'mahkamah_rayuan' => 'Mahkamah Rayuan'])
            ->assertCreated()->assertJsonPath('data.status', 'menunggu_kelulusan');
        $this->assertDatabaseHas('kes_tarikh_penting', ['kes_mahkamah_id' => $k->id, 'jenis' => 'had_rayuan']);
        $instance = DB::table('kes_rayuan')->where('id', $r->json('data.id'))->value('id_instans_aliran');
        $this->assertSame('kes.rayuan', $this->workflow->started[$instance]['flow']);
        $this->selesaiAliran('kes.rayuan', 'kes_rayuan', $r->json('data.id'), (string) $instance);
        $this->assertSame('difailkan', DB::table('kes_rayuan')->where('id', $r->json('data.id'))->value('status'));
        $this->assertSame('rayuan', $k->refresh()->status);

        $s = $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/penyelesaian', ['terma' => 'Defendan keluar dalam 60 hari; MAIS tidak menuntut kos.', 'nilai_penyelesaian' => '15000'])->assertCreated();
        $sid = $s->json('data.id');
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/penyelesaian/'.$sid.'/meterai', ['id_dokumen_perjanjian' => 'x', 'tarikh_persetujuan' => now()->toDateString()])->assertUnprocessable();
        $inst = DB::table('kes_penyelesaian')->where('id', $sid)->value('id_instans_aliran');
        $this->selesaiAliran('kes.penyelesaian', 'kes_penyelesaian', $sid, (string) $inst);
        $doc = app(DokumenService::class)->muatNaik($k, UploadedFile::fake()->create('perjanjian.pdf', 10, 'application/pdf'), 'perjanjian_penyelesaian', $u);
        $this->actingAs($u)->postJson('/api/v1/kes/kes/'.$k->id.'/penyelesaian/'.$sid.'/meterai', ['id_dokumen_perjanjian' => $doc->id_dokumen_dms, 'tarikh_persetujuan' => now()->toDateString()])->assertOk()->assertJsonPath('data.status', 'dimeterai');
        $this->assertDatabaseHas('kes_keputusan', ['kes_mahkamah_id' => $k->id, 'jenis_keputusan' => 'penyelesaian_luar_mahkamah']);
    }
}
