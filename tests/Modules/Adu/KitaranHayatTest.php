<?php

namespace Tests\Modules\Adu;

use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\JejakSla;
use App\Modules\Adu\Models\KronologiAduan;
use App\Modules\Adu\Models\MaklumanPengadu;
use App\Modules\Adu\Models\PeraturanPenugasan;
use App\Modules\Adu\Models\ResolusiAduan;
use App\Modules\Adu\Profile\AduanSection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

class KitaranHayatTest extends AduTestCase
{
    private function ditugaskan(): array
    {
        $officer = $this->user(self::PEGAWAI);
        PeraturanPenugasan::query()->create(['id_pegawai' => $officer->id]);

        return [$officer, $this->aduan(['lot_id' => $this->lot()])];
    }

    public function test_SRS_ADU_15_complaint_is_linked_to_its_lot_and_listed_on_the_lot_profile(): void
    {
        [$officer, $aduan] = $this->ditugaskan();
        $this->aduan(['lot_id' => $aduan->lot_id, 'kategori' => 'PEMBUANGAN_SAMPAH', 'keterangan' => 'Sampah dibuang']);

        $section = app(AduanSection::class);
        $block = $section->forLot((string) $aduan->lot_id, $officer);
        $this->assertSame('risiko', $section->tab());
        $this->assertSame('aduan', $section->key());
        $this->assertCount(2, $block['items']);
        $this->assertSame('danger', $block['flags'][0]['level'], 'open high-priority complaint');
        $this->assertNotEmpty($section->timeline((string) $aduan->lot_id, $officer));
        $this->assertNull($section->forLot((string) $aduan->lot_id, $this->user(['pro.lot.lihat'])), 'no adu permission → nothing');

        $this->actingAs($officer)->getJson('/api/v1/adu/aduan?lot_id='.$aduan->lot_id)->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_SRS_ADU_17_follow_up_actions_visits_referral_reassignment_and_escalation(): void
    {
        [$officer, $aduan] = $this->ditugaskan();
        $supervisor = $this->user(self::PEGAWAI);
        $this->supervise($supervisor, $officer);
        $base = "/api/v1/adu/aduan/{$aduan->id}";

        $this->actingAs($officer)->postJson("{$base}/mula-siasatan")->assertOk()->assertJsonPath('data.status', 'dalam_siasatan');
        $this->assertNotNull(JejakSla::query()->where('aduan_id', $aduan->id)->value('masa_respons_sebenar'));
        $this->actingAs($officer)->postJson("{$base}/tindakan", ['jenis' => 'tindakan', 'keterangan' => 'Notis keluar diserahkan kepada penghuni.'])->assertOk();
        $visit = $this->actingAs($officer)->postJson("{$base}/lawatan", ['tarikh_jadual' => now()->addDays(2)->toDateString()])->assertCreated()->json('data.lawatan.0.id');
        $this->actingAs($officer)->postJson("{$base}/lawatan/{$visit}/selesai", ['penemuan' => 'Pondok kayu didapati di sudut timur lot.'])->assertOk()
            ->assertJsonPath('data.lawatan.0.status', 'selesai');
        $this->actingAs($officer)->postJson("{$base}/rujuk", ['jenis_rujukan' => 'penguatkuasaan', 'keterangan' => 'Perlu tindakan roboh.'])->assertOk()
            ->assertJsonPath('data.status', 'dirujuk');
        $this->assertNotEmpty($this->notifier->of('adu.rujukan'));
        $this->actingAs($officer)->postJson("{$base}/eskalasi", ['sebab' => 'Penghuni enggan keluar, perlu keputusan penyelia.'])->assertOk();
        $this->assertSame([$supervisor->id], $this->notifier->of('adu.eskalasi')[0]['to']);

        // A different officer who is not assigned may not act.
        $stranger = $this->user(self::PEGAWAI);
        $this->actingAs($stranger)->postJson("{$base}/tindakan", ['jenis' => 'catatan', 'keterangan' => 'Cuba'])->assertForbidden();
        // The supervisor may.
        $this->actingAs($supervisor)->postJson("{$base}/tindakan", ['jenis' => 'catatan', 'keterangan' => 'Disemak penyelia'])->assertOk();

        $types = KronologiAduan::query()->where('aduan_id', $aduan->id)->orderBy('id')->pluck('jenis_tindakan')->all();
        foreach (['tugas', 'mula_siasatan', 'tindakan', 'lawatan_dijadual', 'lawatan_selesai', 'rujuk', 'eskalasi', 'catatan'] as $t) {
            $this->assertContains($t, $types);
        }
        $this->assertSame($officer->id, KronologiAduan::query()->where('aduan_id', $aduan->id)->where('jenis_tindakan', 'tindakan')->value('id_pegawai'));
    }

    public function test_SRS_ADU_18_timeline_and_sla_trail_cannot_be_changed_or_deleted(): void
    {
        [, $aduan] = $this->ditugaskan();
        $entry = KronologiAduan::query()->where('aduan_id', $aduan->id)->firstOrFail();

        try {
            $entry->update(['keterangan_tindakan' => 'ubah']);
            $this->fail('model allowed update');
        } catch (LogicException) {
        }
        foreach (['UPDATE kronologi_aduan SET keterangan_tindakan = \'x\'', 'DELETE FROM kronologi_aduan'] as $sql) {
            try {
                DB::transaction(fn () => DB::statement($sql));
                $this->fail("DB allowed: {$sql}");
            } catch (QueryException $e) {
                $this->assertStringContainsString('hanya-tambah', $e->getMessage());
            }
        }
        try {
            DB::transaction(fn () => DB::statement("UPDATE jejak_sla_aduan SET masa_sasaran_selesai = now() + interval '1 year'"));
            $this->fail('SLA target rewritten');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Jejak SLA', $e->getMessage());
        }

        // Corrections are added as entries.
        $this->actingAs($this->superuser())->postJson("/api/v1/adu/aduan/{$aduan->id}/tindakan", ['jenis' => 'pembetulan', 'keterangan' => 'Pembetulan: tarikh lawatan sebenar 3 Nov.'])->assertOk();
        $this->assertSame('pembetulan', KronologiAduan::query()->where('aduan_id', $aduan->id)->latest('id')->value('jenis_tindakan'));
    }

    public function test_SRS_ADU_19_closure_needs_resolution_justification_and_supervisor_approval_then_reporter_notice(): void
    {
        [$officer, $aduan] = $this->ditugaskan();
        $base = "/api/v1/adu/aduan/{$aduan->id}";

        $this->actingAs($officer)->postJson("{$base}/penutupan", ['kategori_resolusi' => 'selesai_tindakan', 'keterangan_resolusi' => 'Selesai'])->assertUnprocessable()
            ->assertJsonValidationErrors(['keterangan_resolusi', 'justifikasi']);
        $this->actingAs($officer)->postJson("{$base}/penutupan", ['kategori_resolusi' => 'selesai_tindakan', 'keterangan_resolusi' => 'Penghuni haram telah dipindahkan.', 'justifikasi' => 'Disahkan melalui lawatan tapak 2 Nov.'])
            ->assertOk()->assertJsonPath('data.status', 'menunggu_kelulusan');
        $started = array_values($this->workflow->started)[0];
        $this->assertSame(['adu.penutupan', 'aduan', $aduan->id, $officer->id], [$started['flow'], $started['subjectType'], $started['subjectId'], $started['initiator']]);
        $this->assertSame('menunggu_kelulusan', $aduan->refresh()->status, 'not closed until WFE approves');

        // Rejected → back to investigation.
        $this->completeWorkflow($aduan, 'tolak', $this->user()->id);
        $this->assertSame('dalam_siasatan', $aduan->refresh()->status);
        $this->assertSame('tolak', ResolusiAduan::query()->where('aduan_id', $aduan->id)->value('status'));
        $this->assertNotEmpty($this->notifier->of('adu.penutupan_ditolak'));

        // Approved → reporter told, then closed; SLA finished.
        $this->actingAs($officer)->postJson("{$base}/penutupan", ['kategori_resolusi' => 'selesai_tindakan', 'keterangan_resolusi' => 'Penghuni haram telah dipindahkan.', 'justifikasi' => 'Lawatan susulan mengesahkan.'])->assertOk();
        $approver = $this->user();
        $this->completeWorkflow($aduan->refresh(), 'lulus', $approver->id);
        $aduan->refresh();
        $this->assertSame('ditutup', $aduan->status);
        $this->assertNotNull($aduan->tarikh_tutup);
        $res = ResolusiAduan::query()->where('aduan_id', $aduan->id)->where('status', 'lulus')->firstOrFail();
        $this->assertSame([$approver->id, 'dihantar'], [$res->id_penyelia_pelulus, $res->status_makluman_pengadu]);
        $this->assertTrue(collect($this->messenger->sent)->contains(fn ($m) => str_contains($m['teks'], 'telah diselesaikan')));
        $this->assertContains(JejakSla::query()->where('aduan_id', $aduan->id)->value('status_sla'), ['dipenuhi', 'tidak_dipenuhi']);

        // No active flow → clear error, nothing changes.
        $this->workflow->noFlow = true;
        [$o2, $a2] = [$officer, $this->aduan(['lot_id' => $this->lot('100803', [3.4, 101.4])])];
        $this->actingAs($o2)->postJson("/api/v1/adu/aduan/{$a2->id}/penutupan", ['kategori_resolusi' => 'tiada_asas', 'keterangan_resolusi' => 'Tiada bukti ditemui.', 'justifikasi' => 'Lawatan tapak tiada penemuan.'])->assertUnprocessable();
        $this->assertSame(0, ResolusiAduan::query()->where('aduan_id', $a2->id)->count());
    }

    public function test_reopen_starts_a_new_sla_cycle_and_duplicate_merge_closes_the_copy(): void
    {
        [$officer, $aduan] = $this->ditugaskan();
        $this->actingAs($officer)->postJson("/api/v1/adu/aduan/{$aduan->id}/penutupan", ['kategori_resolusi' => 'selesai_tindakan', 'keterangan_resolusi' => 'Telah diselesaikan.', 'justifikasi' => 'Lawatan mengesahkan.'])->assertOk();
        $this->completeWorkflow($aduan->refresh(), 'lulus', $this->user()->id);

        $this->actingAs($officer)->postJson("/api/v1/adu/aduan/{$aduan->id}/buka-semula", ['sebab' => 'Penghuni kembali semula.'])->assertOk()
            ->assertJsonPath('data.status', 'ditugaskan')->assertJsonPath('data.bilangan_buka_semula', 1);
        $this->assertSame([1, 2], JejakSla::query()->where('aduan_id', $aduan->id)->orderBy('kitaran')->pluck('kitaran')->all());
        $this->assertSame(2, JejakSla::query()->where('aduan_id', $aduan->id)->where('semasa', true)->value('kitaran'));

        $copy = $this->aduan(['lot_id' => $aduan->lot_id]);
        $this->assertSame($aduan->id, $copy->id_aduan_pendua_dicadang);
        $unit = $this->user(self::UNIT_ADUAN);
        $this->actingAs($unit)->postJson("/api/v1/adu/aduan/{$copy->id}/pendua", ['id_aduan_induk' => $aduan->id])->assertOk()->assertJsonPath('data.status', 'pendua');
        $this->assertSame($aduan->id, Aduan::query()->find($copy->id)->id_aduan_induk);
        $this->actingAs($officer)->postJson("/api/v1/adu/aduan/{$copy->id}/pendua", ['id_aduan_induk' => $aduan->id])->assertForbidden();
    }

    public function test_SRS_ADU_05_reporter_is_notified_on_each_main_status_change(): void
    {
        [$officer, $aduan] = $this->ditugaskan();
        $base = "/api/v1/adu/aduan/{$aduan->id}";
        $this->actingAs($officer)->postJson("{$base}/mula-siasatan")->assertOk();
        $this->actingAs($officer)->postJson("{$base}/penutupan", ['kategori_resolusi' => 'selesai_tindakan', 'keterangan_resolusi' => 'Penghuni haram telah dipindahkan.', 'justifikasi' => 'Disahkan melalui lawatan tapak.'])->assertOk();
        $this->completeWorkflow($aduan->refresh(), 'lulus', $this->user()->id);

        $texts = array_column($this->messenger->sent, 'teks');
        $this->assertCount(3, $texts, 'diterima, siasatan, selesai (auto-assigned at intake: no separate notice)');
        $this->assertStringContainsString('telah diterima', $texts[0]);
        $this->assertStringContainsString('sedang dijalankan', $texts[1]);
        $this->assertStringContainsString('telah diselesaikan', $texts[2]);
        foreach ($this->messenger->sent as $m) {
            $this->assertSame('ali@contoh.test', $m['ke']);
            $this->assertStringContainsString((string) $aduan->no_rujukan_aduan, $m['teks']);
        }
        $this->assertSame(3, MaklumanPengadu::query()->where('status', 'dihantar')->count());

        // Manual assignment of an unassigned complaint sends the "ditugaskan" notice; SMS when preferred, e-mail fails over.
        PeraturanPenugasan::query()->delete();
        $unassigned = $this->aduan(['lot_id' => $this->lot('100803', [3.6, 101.6])], 'telefon', ['nama' => 'Siti', 'no_telefon' => '013-9998888', 'saluran_makluman' => 'sms']);
        $this->actingAs($this->user(self::UNIT_ADUAN))->postJson("/api/v1/adu/aduan/{$unassigned->id}/tugaskan", ['id_pegawai' => $officer->id])->assertOk();
        $last = end($this->messenger->sent);
        $this->assertSame(['sms', '013-9998888'], [$last['saluran'], $last['ke']]);
        $this->assertStringContainsString('ditugaskan', $last['teks']);
    }
}
