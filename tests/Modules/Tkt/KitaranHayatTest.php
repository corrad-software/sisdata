<?php

namespace Tests\Modules\Tkt;

use App\Modules\Tkt\Models\Kronologi;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\TiketService;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Database\Seeders\TktAliranSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class KitaranHayatTest extends TktTestCase
{
    public function test_SRS_TKT_05_lifecycle_records_timestamp_and_officer_for_every_transition(): void
    {
        $tech = $this->ahli();
        $reporter = $this->user();
        $t = $this->tiket([], $reporter);
        $this->assertSame($tech->id, $t->id_juruteknik);

        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'disiasat'])->assertOk()->assertJsonPath('data.status', 'disiasat');
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'dalam_tindakan'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'selesai'])->assertStatus(422)->assertJsonValidationErrors('penyelesaian');
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'selesai', 'punca_akar' => 'Templat invois rosak.', 'penyelesaian' => 'Templat dipulihkan.'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'tutup'])->assertStatus(422);
        $this->actingAs($reporter)->postJson("/api/v1/tkt/tiket/{$t->id}/sahkan")->assertOk()->assertJsonPath('data.status', 'disahkan');
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'tutup'])->assertOk()->assertJsonPath('data.status', 'tutup');
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'baharu'])->assertStatus(422);

        $rows = Kronologi::query()->where('id_tiket', $t->id)->where('jenis_catatan', 'perubahan_status')->orderBy('id')->get();
        $this->assertSame(['disiasat', 'dalam_tindakan', 'selesai', 'disahkan', 'tutup'], $rows->pluck('status_ke')->all());
        $this->assertSame([$tech->id, $tech->id, $tech->id, $reporter->id, $tech->id], $rows->pluck('id_pengguna')->all());
        $t->refresh();
        $this->assertNotNull($t->tarikh_respons_pertama);
        $this->assertNotNull($t->tarikh_selesai);
        $this->assertNotNull($t->tarikh_disahkan);
        $this->assertNotNull($t->tarikh_tutup);
    }

    public function test_SRS_TKT_11_internal_notes_are_hidden_from_the_reporter(): void
    {
        $tech = $this->ahli();
        $reporter = $this->user();
        $t = $this->tiket([], $reporter);
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/catatan", ['jenis' => 'dalaman', 'kandungan' => 'Syak pepijat dalam modul PJK — semak log.'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/catatan", ['jenis' => 'kelihatan_pelapor', 'kandungan' => 'Kami sedang menyemak.'])->assertOk();
        $this->actingAs($reporter)->postJson("/api/v1/tkt/tiket/{$t->id}/balas", ['kandungan' => 'Masih berlaku pagi ini.'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/ubah", ['keterukan' => 2, 'sebab' => 'Lebih ramai pengguna terjejas'])->assertOk();

        $mine = $this->actingAs($reporter)->getJson("/api/v1/tkt/tiket/{$t->id}")->assertOk()->json('data');
        $texts = array_column($mine['kronologi'], 'kandungan');
        $this->assertContains('Kami sedang menyemak.', $texts);
        $this->assertContains('Masih berlaku pagi ini.', $texts);
        $this->assertNotContains('Syak pepijat dalam modul PJK — semak log.', $texts);
        $this->assertNotContains('perubahan_medan', array_column($mine['kronologi'], 'jenis'));
        $this->assertArrayNotHasKey('eskalasi', $mine);

        $support = $this->actingAs($tech)->getJson("/api/v1/tkt/tiket/{$t->id}")->assertOk()->json('data');
        $this->assertContains('Syak pepijat dalam modul PJK — semak log.', array_column($support['kronologi'], 'kandungan'));
        $change = collect($support['kronologi'])->firstWhere('jenis', 'perubahan_medan');
        $this->assertSame([3, 2], $change['meta']['perubahan']['keterukan']);
        $this->assertSame(1, $this->notifier->of('tkt.balasan_sokongan') !== [] ? 1 : 0);
        $this->assertNotNull($t->refresh()->tarikh_respons_pertama, 'a visible reply is the first response');
    }

    public function test_SRS_TKT_11_timeline_is_append_only(): void
    {
        $t = $this->tiket();
        $row = Kronologi::query()->where('id_tiket', $t->id)->firstOrFail();
        $this->expectException(QueryException::class);
        DB::table('tkt_kronologi')->where('id', $row->id)->update(['kandungan' => 'diubah']);
    }

    public function test_SRS_TKT_12_waiting_for_reporter_pauses_and_reporter_reply_resumes(): void
    {
        $this->isnin('09:00');
        $tech = $this->ahli();
        $reporter = $this->user();
        $t = $this->tiket(['keterukan' => 3], $reporter);
        $target = $t->tarikh_sasaran_selesai;
        app(TiketService::class)->tukarStatus($t, 'disiasat', $tech);
        app(TiketService::class)->tukarStatus($t, 'menunggu_pelapor', $tech, 'Mohon tangkapan skrin.');
        $this->assertNotNull($t->sla_jeda_mula);

        Carbon::setTestNow(Carbon::parse('2026-11-02 11:00', 'Asia/Kuala_Lumpur'));
        $this->actingAs($reporter)->postJson("/api/v1/tkt/tiket/{$t->id}/balas", ['kandungan' => 'Tangkapan skrin dilampirkan.'])->assertOk()->assertJsonPath('data.status', 'disiasat');
        $t->refresh();
        $this->assertNull($t->sla_jeda_mula);
        $this->assertSame(120, $t->sla_jeda_minit);
        $this->assertSame(120, (int) $target->diffInMinutes($t->tarikh_sasaran_selesai), 'target moved by the paused working time');
    }

    public function test_SRS_TKT_12_reopen_within_window_and_auto_close_without_feedback(): void
    {
        $this->isnin('09:00');
        $tech = $this->ahli();
        $reporter = $this->user();
        $t = $this->selesai($this->tiket([], $reporter), $tech);
        $this->assertCount(1, $this->notifier->of('tkt.tiket_selesai'));

        $this->actingAs($reporter)->postJson("/api/v1/tkt/tiket/{$t->id}/buka-semula", ['sebab' => 'Masalah berulang selepas log masuk semula'])->assertOk()
            ->assertJsonPath('data.status', 'dalam_tindakan')->assertJsonPath('data.bilangan_buka_semula', 1);
        $this->selesai($t->refresh(), $tech);

        // Past the reopen window → refused.
        Carbon::setTestNow(Carbon::parse('2026-11-20 09:00', 'Asia/Kuala_Lumpur'));
        $this->actingAs($reporter)->postJson("/api/v1/tkt/tiket/{$t->id}/buka-semula", ['sebab' => 'Masih berlaku lagi'])->assertStatus(422);

        // Reminder then auto-close.
        $u = $this->selesai($this->tiket(['tajuk' => 'Isu kedua yang berbeza sama sekali'], $reporter), $tech);
        Carbon::setTestNow(now()->addDays(4));
        $out = app(TiketService::class)->automatik();
        $this->assertSame(1, $out['peringatan']);
        $this->assertCount(1, $this->notifier->of('tkt.peringatan_pengesahan'));
        Carbon::setTestNow(now()->addDays(2));
        $out = app(TiketService::class)->automatik();
        $this->assertGreaterThanOrEqual(1, $out['ditutup']);
        $u->refresh();
        $this->assertSame(['tutup', true], [$u->status, $u->ditutup_automatik]);
        $this->assertNotEmpty($this->notifier->of('tkt.tinjauan_kepuasan'));
    }

    public function test_SRS_TKT_12_satisfaction_survey_once_by_reporter(): void
    {
        $tech = $this->ahli();
        $reporter = $this->user();
        $t = $this->tiket([], $reporter);
        $this->actingAs($reporter)->postJson("/api/v1/tkt/tiket/{$t->id}/kepuasan", ['skor' => 5])->assertStatus(422);
        $this->selesai($t, $tech);
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/kepuasan", ['skor' => 5])->assertForbidden();
        $this->actingAs($reporter)->postJson("/api/v1/tkt/tiket/{$t->id}/kepuasan", ['skor' => 4, 'ulasan' => 'Pantas'])->assertOk()->assertJsonPath('data.kepuasan_skor', 4);
        $this->actingAs($reporter)->postJson("/api/v1/tkt/tiket/{$t->id}/kepuasan", ['skor' => 1])->assertStatus(422);
    }

    public function test_SRS_TKT_13_resolved_ticket_produces_one_kb_draft_problem_cause_solution(): void
    {
        $tech = $this->ahli();
        $reporter = $this->user();
        $t = $this->selesai($this->tiket(['modul' => 'dms', 'tajuk' => 'Gagal muat naik PDF'], $reporter), $tech);
        app(TiketService::class)->sahkan($t, $reporter);
        app(TiketService::class)->tutup($t, $tech);

        $this->assertCount(1, $this->drafts->drafts, 'one draft per ticket, updated on close');
        $draft = $this->drafts->drafts[$t->id];
        $this->assertSame(2, $draft['calls']);
        $this->assertSame(['Penyelesaian: Gagal muat naik PDF', 'Cache konfigurasi lapuk.', 'Cache dikosongkan dan konfigurasi dimuat semula.', 'dms', '1.0.0'], [$draft['data']['tajuk'], $draft['data']['punca'], $draft['data']['penyelesaian'], $draft['data']['modul'], $draft['data']['versi_sistem']]);
        $this->assertNotEmpty($draft['data']['masalah']);
        $this->assertSame($draft['id'], $t->refresh()->id_artikel_kb);
        $this->actingAs($tech)->getJson("/api/v1/tkt/tiket/{$t->id}")->assertOk()->assertJsonPath('data.artikel_kb.0.status', 'draf');
    }

    public function test_SRS_TKT_05_change_request_needs_wfe_approval_before_action(): void
    {
        $tech = $this->ahli();
        $t = $this->tiket(['kategori' => 'permintaan_perubahan', 'tajuk' => 'Tambah lajur mukim dalam laporan']);
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'disiasat'])->assertOk()->assertJsonMissing(['peralihan' => ['dalam_tindakan']]);
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'dalam_tindakan'])->assertStatus(422);
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/mohon-kelulusan", ['justifikasi' => 'Perubahan kecil pada laporan, tiada kesan pangkalan data.'])->assertOk()->assertJsonPath('data.status', 'menunggu_kelulusan');
        $this->assertSame(['tkt.permintaan_perubahan', 'tkt_tiket', $t->id], array_values(array_intersect_key(reset($this->workflow->started), array_flip(['flow', 'subjectType', 'subjectId']))));

        event(new WorkflowCompleted('wf-1', 'tkt.permintaan_perubahan', 'tkt_tiket', $t->id, 'lulus', null, 'Diluluskan JK'));
        $t->refresh();
        $this->assertSame(['dalam_tindakan', 'lulus'], [$t->status, $t->status_kelulusan]);
    }

    public function test_SRS_TKT_05_seeded_change_request_flow_runs_on_the_real_workflow_engine(): void
    {
        $tech = $this->ahli();
        $this->seed(TktAliranSeeder::class);
        $this->app->forgetInstance(WorkflowEngine::class);
        $t = $this->tiket(['kategori' => 'permintaan_perubahan', 'tajuk' => 'Tambah medan baharu pada borang']);
        app(TiketService::class)->tukarStatus($t, 'disiasat', $tech);
        app(TiketService::class)->mohonKelulusan($t, 'Perubahan kecil pada borang sedia ada.', $tech);

        $this->assertSame('menunggu_kelulusan', $t->refresh()->status);
        $this->assertSame($t->id_instans_aliran, app(WorkflowEngine::class)->activeInstanceFor('tkt_tiket', $t->id));
    }

    public function test_SRS_TKT_05_land_complaint_is_routed_to_adu_and_duplicates_are_closed(): void
    {
        $tech = $this->ahli();
        $reporter = $this->user();
        $t = $this->tiket(['tajuk' => 'Tanah wakaf diceroboh', 'kategori' => 'soalan'], $reporter);
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/rujuk-aduan", ['kategori' => 'PENCEROBOHAN'])->assertOk()->assertJsonPath('data.status', 'dirujuk_aduan');
        $this->assertSame('tkt', $this->complaints->opened['tkt_tiket:'.$t->id]['channel']);
        $this->assertSame($reporter->id, $this->complaints->opened['tkt_tiket:'.$t->id]['reporter']);
        $this->assertNotNull($t->refresh()->id_aduan_adu);

        $a = $this->tiket(['tajuk' => 'Laporan kutipan tidak lengkap']);
        $b = $this->tiket(['tajuk' => 'Laporan kutipan bulanan tidak lengkap']);
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$b->id}/pendua", ['id_tiket_induk' => $a->id])->assertOk()->assertJsonPath('data.status', 'pendua');
        $this->assertSame($a->id, Tiket::query()->find($b->id)?->id_tiket_induk);
    }
}
