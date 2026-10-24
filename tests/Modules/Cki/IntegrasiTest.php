<?php

namespace Tests\Modules\Cki;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Konflik;
use App\Modules\Cki\Models\LogIntegrasiCukai;
use App\Modules\Cki\Models\ModOperasi;
use App\Modules\Cki\Services\LiputanService;
use App\Modules\Cki\Services\SemakanService;
use App\Modules\Cki\Support\Wang;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class IntegrasiTest extends CkiTestCase
{
    public function test_SRS_CKI_04_central_provider_serves_land_office_and_pbt_bills_and_direct_payment_with_receipt(): void
    {
        $tanah = $this->mbi->semakBil(['kod_agensi' => 'PTD-1008', 'jenis_cukai' => 'tanah', 'no_akaun' => 'CT-1', 'tahun' => 2026])[0];
        $taksiran = $this->mbi->semakBil(['kod_agensi' => 'MBPJ', 'jenis_cukai' => 'taksiran_pbt', 'no_akaun' => 'TK-1', 'tahun' => 2026])[0];
        $this->assertSame('2026-05-31', $tanah['tarikh_akhir']);
        $this->assertSame('2026-11-30', $taksiran['tarikh_akhir']);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $tanah['amaun_pokok']);
        $this->assertSame('belum_bayar', $taksiran['status_bayaran']);

        $r = $this->mbi->bayar(['kod_agensi' => 'MBPJ', 'no_akaun' => 'TK-1', 'no_bil' => $taksiran['no_bil'], 'amaun' => $taksiran['amaun_pokok'], 'rujukan' => 'CKI/PB/2026/00001']);
        $this->assertStringStartsWith('MBI-R-', $r['no_resit']);
        $after = $this->mbi->semakBil(['kod_agensi' => 'MBPJ', 'jenis_cukai' => 'taksiran_pbt', 'no_akaun' => 'TK-1', 'tahun' => 2026])[0];
        $this->assertSame('dibayar', $after['status_bayaran']);
        $this->assertSame($r['no_resit'], $after['no_resit']);
    }

    public function test_SRS_CKI_05_daily_check_updates_100_lots_in_coverage_without_manual_entry(): void
    {
        $obligations = [];
        for ($i = 0; $i < 100; $i++) {
            $obligations[] = $this->obligasi($i % 3 === 0 ? 'taksiran_pbt' : 'tanah', null, 'AKN'.str_pad((string) $i, 5, '0', STR_PAD_LEFT));
        }
        $this->assertSame(0, Artisan::call('cki:semak-bil'));
        $bills = Cukai::query()->withoutGlobalScope('access')->whereIn('obligasi_id', array_map(fn ($o) => $o->id, $obligations))->get();
        $this->assertCount(100, $bills);
        $this->assertSame(['api'], $bills->pluck('sumber_rekod')->unique()->values()->all());
        $this->assertTrue($bills->every(fn (Cukai $c) => $c->disegerak_pada !== null && $c->tahun_taksiran === 2026));
        // Land tax due 31 May is overdue on 15 Oct: the API's penalty is carried, status tertunggak.
        $land = $bills->firstWhere('jenis', 'tanah');
        $this->assertSame('tertunggak', $land->status_bayaran);
        $this->assertFalse(Wang::isZero($land->amaun_denda));

        // Next day: the provider shows a payment made elsewhere → updated automatically.
        $o = $obligations[1];
        $bill = $bills->firstWhere('obligasi_id', $o->id);
        $this->mbi->override($o->no_akaun_cukai, 2026, ['status_bayaran' => 'dibayar', 'tarikh_bayar' => '2026-10-14', 'no_resit' => 'PTD-R-9']);
        $r = app(SemakanService::class)->jalankan();
        $this->assertSame(1, $r['dikemas_kini']);
        $this->assertSame(99, $r['tanpa_ubah']);
        $bill->refresh();
        $this->assertSame('dibayar', $bill->status_bayaran);
        $this->assertSame('PTD-R-9', $bill->no_resit);
        $this->assertDatabaseHas('resit_cukai', ['id_bil' => $bill->id, 'no_resit' => 'PTD-R-9', 'sumber' => 'api']);
        $this->assertSame(0, Konflik::query()->withoutGlobalScope('access')->count());
    }

    public function test_SRS_CKI_08_outage_switches_to_fallback_notifies_officers_and_recovers_automatically(): void
    {
        $officer = $this->user(self::PEGAWAI, ['1008']);
        $o = $this->obligasi('tanah');
        $this->mbi->setDown(true);
        $r = app(SemakanService::class)->jalankan();
        $this->assertContains('PTD-1008', $r['gagal']);
        $agensi = $this->agensi('PTD-1008');
        $this->assertSame('sandaran', $agensi->mod_semasa);
        $this->assertDatabaseHas('mod_operasi_integrasi', ['kod_agensi' => 'PTD-1008', 'mod' => 'sandaran', 'tarikh_tamat' => null]);
        $sent = $this->notifier->ofType('cki.mod_sandaran');
        $this->assertNotEmpty($sent);
        $this->assertContains($officer->id, $sent[0]['to']);
        $this->assertAudited('tukar_mod', ['modul' => 'cki', 'nama_entiti' => 'daftar_liputan_integrasi']);

        // Manual entry is now permitted for the in-coverage lot.
        $this->actingAs($officer)->postJson('/api/v1/cki/bil', ['obligasi_id' => $o->id, 'tahun_taksiran' => 2026, 'no_bil' => 'MAN-1', 'amaun_pokok' => '10', 'tarikh_akhir' => '2026-05-31'])->assertCreated();

        $this->mbi->setDown(false);
        $r = app(SemakanService::class)->jalankan();
        $this->assertContains('PTD-1008', $r['dipulihkan']);
        $this->assertSame('api', $agensi->refresh()->mod_semasa);
        $this->assertNotNull(ModOperasi::query()->where('kod_agensi', 'PTD-1008')->first()->tarikh_tamat);
        $this->assertNotEmpty($this->notifier->ofType('cki.mod_pulih'));
    }

    public function test_SRS_CKI_09_resync_conflict_needs_officer_confirmation_and_keeps_both_versions(): void
    {
        $officer = $this->user();
        $o = $this->obligasi('tanah', null, 'CT-CONFLICT');
        $this->mbi->setDown(true);
        app(SemakanService::class)->jalankan();
        $manual = $this->bil($o, ['no_bil' => 'MAN-77', 'amaun_pokok' => '99.00', 'tarikh_akhir' => '2026-05-31']);

        $this->mbi->setDown(false);
        $r = app(SemakanService::class)->jalankan();
        $this->assertSame(1, $r['konflik']);
        $manual->refresh();
        $this->assertSame('99.00', $manual->amaun_pokok);   // not overwritten
        $this->assertSame('manual', $manual->sumber_rekod);
        $k = Konflik::query()->withoutGlobalScope('access')->sole();
        $this->assertContains('no_bil', $k->medan_berkonflik);
        $this->assertContains('amaun_pokok', $k->medan_berkonflik);
        $this->assertSame('99.00', $k->nilai_manual['amaun_pokok']);
        $this->assertNotEmpty($this->notifier->ofType('cki.konflik'));

        $list = $this->actingAs($officer)->getJson('/api/v1/cki/konflik')->assertOk()->json('data');
        $this->assertSame($k->id, $list[0]['id']);
        $this->actingAs($officer)->postJson('/api/v1/cki/konflik/'.$k->id.'/selesai', ['keputusan' => 'terima_api', 'catatan' => 'Disahkan dengan PTD'])
            ->assertOk()->assertJsonPath('data.status_resolusi', 'terima_api');
        $manual->refresh();
        $this->assertSame('api', $manual->sumber_rekod);
        $this->assertSame($k->nilai_api['amaun_pokok'], $manual->amaun_pokok);
        $audit = $this->assertAudited('resolusi_konflik', ['modul' => 'cki', 'id_rekod' => $manual->id]);
        $audit->assertFieldChanged('amaun_pokok', '99.00', $k->nilai_api['amaun_pokok']);
        $this->actingAs($officer)->postJson('/api/v1/cki/konflik/'.$k->id.'/selesai', ['keputusan' => 'kekal_manual', 'catatan' => 'Ulang'])->assertStatus(422);
    }

    public function test_SRS_CKI_09_officer_may_keep_the_manual_version(): void
    {
        $officer = $this->user();
        $o = $this->obligasi('tanah', null, 'CT-KEEP');
        app(LiputanService::class)->gagal($this->agensi('PTD-1008'), 'ujian');
        $manual = $this->bil($o, ['no_bil' => 'MAN-1', 'amaun_pokok' => '50.00', 'tarikh_akhir' => '2026-05-31']);
        app(SemakanService::class)->jalankan();
        $k = Konflik::query()->withoutGlobalScope('access')->sole();
        $this->actingAs($officer)->postJson('/api/v1/cki/konflik/'.$k->id.'/selesai', ['keputusan' => 'kekal_manual', 'catatan' => 'Bil PTD sebenar dilihat'])->assertOk();
        $this->assertSame('50.00', $manual->refresh()->amaun_pokok);
        $this->assertAudited('resolusi_konflik', ['modul' => 'cki', 'id_rekod' => $manual->id]);
    }

    public function test_SRS_CKI_10_every_call_is_logged_retried_masked_and_persistent_failure_escalated(): void
    {
        $admin = $this->user(['cki.integrasi.lihat', 'cki.integrasi.kemas_kini']);
        app(Parameters::class)->get('cki.cuba_semula'); // declared default 3
        $this->obligasi('tanah', null, 'CT-123456789');
        app(SemakanService::class)->jalankan('PTD-1008');
        $ok = LogIntegrasiCukai::query()->where('jenis_panggilan', 'semak_bil')->sole();
        $this->assertTrue($ok->berjaya);
        $this->assertSame('********6789', $ok->ringkasan_payload['no_akaun']);
        $this->assertStringNotContainsString('CT-123456789', json_encode($ok->ringkasan_payload));
        $this->assertNotNull($ok->rujukan_transaksi);
        $this->assertNotNull($ok->masa_tindak_balas);
        $this->assertSame(0, $ok->bilangan_cuba_semula);
        $this->assertDatabaseHas('adm_log_integrasi', ['kod_integrasi' => 'cki_mbi', 'berjaya' => true]);

        $this->mbi->setDown(true);
        foreach (range(1, 3) as $_) {
            app(SemakanService::class)->jalankan('PTD-1008');
        }
        $fail = LogIntegrasiCukai::query()->where('berjaya', false)->latest('id')->first();
        $this->assertSame(2, $fail->bilangan_cuba_semula);    // 3 attempts
        $this->assertSame('MBI_TIDAK_TERSEDIA', $fail->kod_ralat);
        $this->assertSame(503, $fail->status_http);
        $escalated = $this->notifier->ofType('cki.kegagalan_integrasi');
        $this->assertNotEmpty($escalated);
        $this->assertContains($admin->id, $escalated[0]['to']);

        $log = $this->actingAs($admin)->getJson('/api/v1/cki/integrasi/log?berjaya=0')->assertOk()->json('data');
        $this->assertSame(3, count($log));
        $this->assertSame(0, DB::table('log_integrasi_cukai')->where('ringkasan_payload', 'like', '%CT-123456789%')->count());
    }

    public function test_SRS_CKI_10_on_demand_check_and_outage_simulation_via_api(): void
    {
        $admin = $this->user(['cki.integrasi.lihat', 'cki.integrasi.kemas_kini']);
        $this->obligasi('tanah');
        $this->actingAs($admin)->postJson('/api/v1/cki/integrasi/simulasi', ['sasaran' => 'mbi', 'turun' => true])->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/cki/integrasi')->assertJsonPath('data.mock', true)->assertJsonPath('data.mock_turun', true);
        $this->actingAs($admin)->postJson('/api/v1/cki/integrasi/semak', ['kod_agensi' => 'PTD-1008'])->assertOk()->assertJsonPath('data.gagal.0', 'PTD-1008');
        $this->actingAs($admin)->postJson('/api/v1/cki/integrasi/simulasi', ['sasaran' => 'mbi', 'turun' => false])->assertOk();
        $this->actingAs($admin)->postJson('/api/v1/cki/integrasi/semak', ['kod_agensi' => 'PTD-1008'])->assertOk()->assertJsonPath('data.baharu', 2); // recovery re-syncs last year too
        $this->assertAudited('simulasi_integrasi', ['modul' => 'cki']);
    }
}
