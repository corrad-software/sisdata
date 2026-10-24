<?php

namespace Tests\Modules\Pjk;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adm\Services\ParameterService;
use App\Modules\Pjk\Mail\ResitMail;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\DepositCagaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\LejarTransaksi;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

class BayaranTest extends PjkTestCase
{
    private function offline(Kontrak $k, array $data, bool $proof = true): TestResponse
    {
        return $this->actingAs($this->user())->post("/api/v1/pjk/kontrak/{$k->id}/bayaran", $data + (
            $proof ? ['bukti' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')] : []
        ), ['Accept' => 'application/json']);
    }

    public function test_SRS_PJK_19_offline_payment_with_proof_is_credited_only_after_finance_confirms(): void
    {
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-09-01', 'tarikh_tamat' => '2027-08-31']);
        $before = LejarTransaksi::query()->where('kontrak_id', $k->id)->count();

        $this->offline($k, ['saluran' => 'eft', 'amaun' => '1500.00', 'tarikh_bayaran' => '2026-10-10'], false)->assertStatus(422)->assertJsonValidationErrors(['rujukan_luar']);
        $this->offline($k, ['saluran' => 'eft', 'amaun' => '1500.00', 'tarikh_bayaran' => '2026-10-10', 'rujukan_luar' => 'EFT-1'], false)->assertStatus(422)->assertJsonValidationErrors(['bukti']);
        $res = $this->offline($k, ['saluran' => 'cek', 'amaun' => '1500.00', 'tarikh_bayaran' => '2026-10-10', 'rujukan_luar' => 'CEK 001234'])->assertCreated();
        $this->assertSame('menunggu_pengesahan', $res->json('data.status'));
        $this->assertSame(1, count($this->dms->ofCategory('bukti_bayaran')));
        $this->assertSame('sulit', $this->dms->ofCategory('bukti_bayaran')[0]['classification']);
        $this->assertSame($before, LejarTransaksi::query()->where('kontrak_id', $k->id)->count());

        $id = $res->json('data.id');
        $finance = $this->user(self::KEWANGAN);
        $this->actingAs($finance)->getJson('/api/v1/pjk/bayaran')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($finance)->postJson("/api/v1/pjk/bayaran/{$id}/pengesahan", ['terima' => true])->assertOk()->assertJsonPath('data.status', 'disahkan');

        $credit = LejarTransaksi::query()->where('kontrak_id', $k->id)->where('jenis', 'bayaran')->sole();
        $this->assertSame('1500.00', $credit->kredit);
        $this->assertSame('cek', $credit->kaedah_bayaran);
        $this->assertNotNull($credit->no_resit);
        $this->assertSame('2026-10-10', $credit->tarikh_transaksi->format('Y-m-d'));
    }

    public function test_SRS_PJK_19_recorder_cannot_confirm_own_payment_and_others_need_permission(): void
    {
        $recorder = $this->user([...self::PENDAFTAR, 'pjk.bayaran.lulus']);
        $k = $this->active($recorder);
        $id = $this->actingAs($recorder)->post("/api/v1/pjk/kontrak/{$k->id}/bayaran", ['saluran' => 'kaunter', 'amaun' => '100.00', 'tarikh_bayaran' => '2026-10-14'], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $this->actingAs($recorder)->postJson("/api/v1/pjk/bayaran/{$id}/pengesahan", ['terima' => true])->assertStatus(422)->assertJsonValidationErrors(['pengesah']);
        $this->actingAs($this->user())->postJson("/api/v1/pjk/bayaran/{$id}/pengesahan", ['terima' => true])->assertForbidden();
        $this->actingAs($this->user(self::KEWANGAN, ['1009']))->postJson("/api/v1/pjk/bayaran/{$id}/pengesahan", ['terima' => true])->assertNotFound();
        $this->actingAs($this->user(self::KEWANGAN))->postJson("/api/v1/pjk/bayaran/{$id}/pengesahan", ['terima' => false, 'catatan' => 'Cek tidak laku'])->assertOk()->assertJsonPath('data.status', 'ditolak');
        $this->assertSame(0, LejarTransaksi::query()->where('kontrak_id', $k->id)->where('jenis', 'bayaran')->count());
    }

    public function test_SRS_PJK_19_manual_matching_to_a_specific_charge(): void
    {
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-08-01', 'tarikh_tamat' => '2027-07-31']);
        $oct = JadualCaj::query()->where('kontrak_id', $k->id)->where('tempoh', '2026-10')->firstOrFail();
        $id = $this->offline($k, ['saluran' => 'kaunter', 'amaun' => '1500.00', 'tarikh_bayaran' => '2026-10-14', 'agihan' => [['caj_id' => $oct->id, 'amaun' => '1500.00']]])->assertCreated()->json('data.id');
        $this->actingAs($this->user(self::KEWANGAN))->postJson("/api/v1/pjk/bayaran/{$id}/pengesahan", ['terima' => true])->assertOk();

        $this->assertSame('dibayar', $oct->refresh()->status_caj);
        $this->assertSame('belum_bayar', JadualCaj::query()->where('kontrak_id', $k->id)->where('tempoh', '2026-08')->value('status_caj'));
    }

    public function test_SRS_PJK_20_partial_payment_allocated_by_configured_priority_oldest_first(): void
    {
        $k = $this->active($this->user(), null, ['tarikh_mula' => '2026-08-01', 'tarikh_tamat' => '2027-07-31']);
        // default priority: penalti, sewa, lain, deposit
        $id = $this->offline($k, ['saluran' => 'kaunter', 'amaun' => '2000.00', 'tarikh_bayaran' => '2026-10-14'])->assertCreated()->json('data.id');
        $this->actingAs($this->user(self::KEWANGAN))->postJson("/api/v1/pjk/bayaran/{$id}/pengesahan", ['terima' => true])->assertOk();

        $c = JadualCaj::query()->where('kontrak_id', $k->id)->get()->keyBy('tempoh');
        $this->assertSame('dibayar', $c['2026-08']->status_caj);
        $this->assertSame('separa', $c['2026-09']->status_caj);
        $this->assertSame('500.00', $c['2026-09']->amaun_dibayar);
        $this->assertSame('belum_bayar', $c['deposit']->status_caj);

        // Reconfigure: deposit first.
        $params = app(Parameters::class);
        $this->assertInstanceOf(ParameterService::class, $params);
        $params->store('pjk.keutamaan_agihan', ['deposit', 'sewa'], 'Ujian SRS-PJK-20', null);
        $id2 = $this->offline($k, ['saluran' => 'kaunter', 'amaun' => '3000.00', 'tarikh_bayaran' => '2026-10-14'])->assertCreated()->json('data.id');
        $this->actingAs($this->user(self::KEWANGAN))->postJson("/api/v1/pjk/bayaran/{$id2}/pengesahan", ['terima' => true])->assertOk();
        $this->assertSame('dibayar', $c['deposit']->refresh()->status_caj);
        $this->assertSame('dipegang', DepositCagaran::query()->where('kontrak_id', $k->id)->value('status'));
    }

    public function test_SRS_PJK_17_receipts_are_sequential_immutable_emailed_and_kept_in_dms(): void
    {
        $k = $this->active($this->user());
        $finance = $this->user(self::KEWANGAN);
        $numbers = [];
        foreach (['100.00', '200.00'] as $amount) {
            $id = $this->offline($k, ['saluran' => 'kaunter', 'amaun' => $amount, 'tarikh_bayaran' => '2026-10-14', 'emel_pembayar' => 'bayar@contoh.invalid'])->json('data.id');
            $numbers[] = $this->actingAs($finance)->postJson("/api/v1/pjk/bayaran/{$id}/pengesahan", ['terima' => true])->assertOk()->json('resit.no_resit');
        }
        $this->assertSame(['PJK/R/2026/000001', 'PJK/R/2026/000002'], $numbers);
        $this->assertCount(2, $this->dms->ofCategory('resit'));
        $this->assertSame(2, DB::table('penghantaran_resit')->where('status', 'dihantar')->count());
        Mail::assertSent(ResitMail::class, 2);
        Mail::assertSent(ResitMail::class, fn ($m) => $m->hasTo('bayar@contoh.invalid') && $m->pdf !== null);

        $r = Resit::query()->where('no_resit', $numbers[0])->firstOrFail();
        $this->assertNotNull($r->id_dokumen);
        try {
            DB::transaction(fn () => DB::table('resit')->where('id', $r->id)->update(['amaun' => '1.00']));
            $this->fail('Resit boleh diubah');
        } catch (QueryException) {
            // expected
        }
        try {
            DB::transaction(fn () => DB::table('resit')->where('id', $r->id)->delete());
            $this->fail('Resit boleh dipadam');
        } catch (QueryException) {
            // expected
        }
        $this->assertSame('100.00', $r->refresh()->amaun);
    }

    public function test_SRS_PJK_13_deposit_separate_from_rent_and_settled_with_finance_approval(): void
    {
        $user = $this->user();
        $k = $this->active($user, null, ['tarikh_mula' => '2026-09-01', 'tarikh_tamat' => '2027-08-31']);
        $dep = DepositCagaran::query()->where('kontrak_id', $k->id)->sole();
        $this->assertSame('3000.00', $dep->amaun);
        $this->assertSame('belum_diterima', $dep->status);
        $this->assertSame('deposit', JadualCaj::query()->find($dep->caj_id)?->kod_caj);

        $id = $this->offline($k, ['saluran' => 'kaunter', 'amaun' => '3000.00', 'tarikh_bayaran' => '2026-10-14', 'agihan' => [['caj_id' => $dep->caj_id, 'amaun' => '3000.00']]])->json('data.id');
        $this->actingAs($this->user(self::KEWANGAN))->postJson("/api/v1/pjk/bayaran/{$id}/pengesahan", ['terima' => true])->assertOk();
        $this->assertSame('dipegang', $dep->refresh()->status);
        $this->assertSame('2026-10-14', $dep->tarikh_terima?->format('Y-m-d'));

        // Settlement only after the agreement ends.
        $this->actingAs($user)->postJson("/api/v1/pjk/deposit/{$dep->id}/penyelarasan", ['amaun_tolakan' => '0'])->assertStatus(422);
        $k->forceFill(['status' => 'ditamatkan'])->save();
        $this->actingAs($user)->postJson("/api/v1/pjk/deposit/{$dep->id}/penyelarasan", ['amaun_tolakan' => '1000.00'])->assertStatus(422)->assertJsonValidationErrors(['justifikasi_tolakan']);
        $this->actingAs($user)->postJson("/api/v1/pjk/deposit/{$dep->id}/penyelarasan", ['amaun_tolakan' => '1000.00', 'justifikasi_tolakan' => 'Tunggakan sewa Sep dan Okt'])->assertOk()->assertJsonPath('data.status', 'dalam_penyelarasan');
        $this->assertSame(Aliran::DEPOSIT, $this->workflow->last()['flow']);
        $balance = LejarTransaksi::query()->where('kontrak_id', $k->id)->orderByDesc('id')->value('baki_berjalan');

        $this->complete(Aliran::DEPOSIT, 'deposit_cagaran', $dep->id, 'lulus', $this->user(self::KEWANGAN)->id);

        $dep->refresh();
        $this->assertSame('dipulangkan', $dep->status);
        $this->assertSame('2000.00', $dep->amaun_dipulangkan);
        $tolakan = LejarTransaksi::query()->where('kontrak_id', $k->id)->where('jenis', 'tolakan_deposit')->sole();
        $this->assertSame('1000.00', $tolakan->kredit);
        $this->assertSame(Wang::sen((string) $balance) - 100000, Wang::sen($tolakan->baki_berjalan));
        $sep = JadualCaj::query()->where('kontrak_id', $k->id)->where('tempoh', '2026-09')->firstOrFail();
        $this->assertSame(['separa', '1000.00'], [$sep->status_caj, $sep->amaun_dibayar]); // oldest rent first
    }

    public function test_SRS_PJK_17_payment_without_email_records_delivery_status(): void
    {
        $k = $this->active($this->user());
        DB::table('penyewa')->where('id', $k->penyewa_id)->update(['emel' => null]);
        $id = $this->offline($k, ['saluran' => 'kaunter', 'amaun' => '50.00', 'tarikh_bayaran' => '2026-10-14'])->json('data.id');
        $this->actingAs($this->user(self::KEWANGAN))->postJson("/api/v1/pjk/bayaran/{$id}/pengesahan", ['terima' => true])->assertOk();
        $this->assertSame('tiada_emel', DB::table('penghantaran_resit')->value('status'));
        $this->assertSame(1, Bayaran::query()->where('status', 'disahkan')->count());
    }
}
