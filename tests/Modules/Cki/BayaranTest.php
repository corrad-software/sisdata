<?php

namespace Tests\Modules\Cki;

use App\Modules\Adm\Models\Parameter;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Kelulusan;
use App\Modules\Cki\Models\Lejar;
use App\Modules\Cki\Models\Penyelarasan;
use App\Modules\Cki\Models\Transaksi;
use App\Modules\Cki\Services\LiputanService;
use App\Modules\Cki\Services\SemakanService;
use App\Modules\Cki\Support\Aliran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

class BayaranTest extends CkiTestCase
{
    private function request(Cukai $bill, string $kaedah, ?string $amaun = null): Transaksi
    {
        $res = $this->actingAs($this->user())->postJson('/api/v1/cki/bil/'.$bill->id.'/bayaran', array_filter(['kaedah_bayaran' => $kaedah, 'amaun' => $amaun]))->assertCreated();

        return Transaksi::query()->withoutGlobalScope('access')->findOrFail($res->json('data.id'));
    }

    private function approved(Cukai $bill, string $kaedah): Transaksi
    {
        $t = $this->request($bill, $kaedah);
        $this->complete($t->id);

        return $t->refresh();
    }

    private function lowerThresholds(): void
    {
        Parameter::query()->updateOrCreate(['kunci' => 'cki.had_kelulusan'], ['modul' => 'cki', 'nilai' => json_encode([['hingga' => '1000.00', 'peringkat' => 'kewangan'], ['hingga' => null, 'peringkat' => 'pengurusan_atasan']])]);
        Cache::flush();
    }

    private function upload(Transaksi $t, string $no = 'R-1'): void
    {
        $this->actingAs($this->user())->post('/api/v1/cki/transaksi/'.$t->id.'/resit', ['no_resit' => $no, 'fail' => UploadedFile::fake()->create('resit.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_SRS_CKI_14_value_threshold_decides_approval_level_through_the_central_workflow(): void
    {
        $small = $this->request($this->bil(null, ['amaun_pokok' => '4000.00']), 'eft');
        $this->assertSame(Aliran::BAYARAN, $this->workflow->last()['flow']);
        $this->assertSame(1, $this->workflow->last()['context']['tahap']);
        $mid = $this->request($this->bil(null, ['amaun_pokok' => '20000.00']), 'eft');
        $this->assertSame('ketua_bahagian', $this->workflow->last()['context']['peringkat']);
        $big = $this->request($this->bil(null, ['amaun_pokok' => '80000.00']), 'eft');
        $this->assertSame(3, $this->workflow->last()['context']['tahap']);
        $this->assertSame(['kewangan', 'ketua_bahagian', 'pengurusan_atasan'], [$small->kelulusan->peringkat_pelulus, $mid->kelulusan->peringkat_pelulus, $big->kelulusan->peringkat_pelulus]);
        $this->assertSame('dalam_proses', $small->bil->status_bayaran);

        // Thresholds are a configurable (critical) parameter.
        $this->lowerThresholds();
        $this->request($this->bil(null, ['amaun_pokok' => '4000.00']), 'eft');
        $this->assertSame('pengurusan_atasan', $this->workflow->last()['context']['peringkat']);

        // A second request while one is active is refused.
        $this->actingAs($this->user())->postJson('/api/v1/cki/bil/'.$small->id_bil.'/bayaran', ['kaedah_bayaran' => 'eft'])->assertStatus(422);
    }

    public function test_SRS_CKI_14_payment_cannot_execute_without_correct_level_approval_and_the_attempt_is_audited(): void
    {
        $user = $this->user();
        $t = $this->request($this->bil(null, ['amaun_pokok' => '30000.00']), 'eft');
        $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$t->id.'/laksana')->assertStatus(422)->assertJsonValidationErrors('kelulusan');
        $this->assertAudited('laksana_bayaran', ['modul' => 'cki', 'id_rekod' => $t->id, 'status_transaksi' => 'gagal']);

        $this->complete($t->id, 'tolak');
        $this->assertSame('ditolak', $t->refresh()->status_transaksi);
        $this->assertSame('tolak', Kelulusan::query()->find($t->id_kelulusan)->status);
        $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$t->id.'/rekod', ['no_rujukan_eft' => 'X'])->assertStatus(422);
        $this->assertNotEmpty($this->notifier->ofType('cki.keputusan_kelulusan'));

        // Approved at level 2, then the threshold is lowered so the amount now needs level 3 → refused.
        $t2 = $this->approved($this->bil(null, ['amaun_pokok' => '30000.00']), 'eft');
        $this->lowerThresholds();
        $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$t2->id.'/laksana')->assertStatus(422);
        $audit = $this->assertAudited('laksana_bayaran', ['modul' => 'cki', 'id_rekod' => $t2->id, 'status_transaksi' => 'gagal']);
        $this->assertStringContainsString('Pengurusan Atasan', (string) $audit->row->sebab_perubahan);
    }

    public function test_SRS_CKI_11_eft_and_bank_draft_go_to_grp_mais_with_a_request_document_and_status_back(): void
    {
        $user = $this->user();
        $t = $this->approved($this->bil(null, ['amaun_pokok' => '800.00']), 'eft');
        $this->assertNotNull($t->id_dokumen_permohonan);
        $this->assertSame('permohonan', $this->dms->docs[$t->id_dokumen_permohonan]['metadata']['jenis_dokumen_cki']);
        $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$t->id.'/laksana')->assertOk()
            ->assertJsonPath('data.status_transaksi', 'dibayar')->assertJsonPath('data.status_grp', 'dibayar');
        $t->refresh();
        $this->assertStringStartsWith('GRP-PV-', (string) $t->rujukan_grp);
        $this->assertStringStartsWith('EFT', (string) $t->no_rujukan_eft);

        // GRP still processing → status polled later.
        $this->grp->holdPending(true);
        $d = $this->approved($this->bil(null, ['amaun_pokok' => '500.00']), 'bank_draf');
        $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$d->id.'/laksana')->assertOk()->assertJsonPath('data.status_transaksi', 'dihantar_grp');
        $this->grp->holdPending(false);
        $this->grp->tamper((string) $d->refresh()->rujukan_grp, '500.00');
        $rows = Cache::get('cki:mockgrp:semua');
        $rows[$d->rujukan_grp]['status'] = 'dibayar';
        Cache::put('cki:mockgrp:semua', $rows);
        $this->artisan('cki:semak-grp')->assertSuccessful();
        $d->refresh();
        $this->assertSame('dibayar', $d->status_transaksi);
        $this->assertStringStartsWith('BD', (string) $d->no_bank_draf);
    }

    public function test_SRS_CKI_12_each_of_the_five_methods_requires_its_own_fields(): void
    {
        $user = $this->user();
        $cases = [
            'eft' => [['no_rujukan_eft'], ['no_rujukan_eft' => 'EFT-1']],
            'bank_draf' => [['no_bank_draf', 'bank_penerbit'], ['no_bank_draf' => 'BD-1', 'bank_penerbit' => 'Bank Islam']],
            'fpx' => [['rujukan_fpx'], ['rujukan_fpx' => 'FPX-1']],
            'kaunter_ptd' => [['no_resit', 'nama_kaunter'], ['no_resit' => 'PTD-1', 'nama_kaunter' => 'Kaunter PTD Petaling']],
            'kaunter_pbt' => [['no_resit', 'nama_kaunter'], ['no_resit' => 'PBT-1', 'nama_kaunter' => 'Kaunter MBPJ']],
        ];
        foreach ($cases as $kaedah => [$required, $fields]) {
            $t = $this->approved($this->bil(), $kaedah);
            $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$t->id.'/rekod', [])->assertStatus(422)->assertJsonValidationErrors($required);
            $res = $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$t->id.'/rekod', $fields + ['tarikh_bayaran' => '2026-10-14'])->assertOk();
            $res->assertJsonPath('data.status_transaksi', 'dibayar')->assertJsonPath('data.tarikh_bayaran', '2026-10-14');
            foreach ($fields as $k => $v) {
                $res->assertJsonPath('data.'.$k, $v);
            }
        }
    }

    public function test_SRS_CKI_13_no_transaction_completes_without_a_receipt_or_proof_for_every_method(): void
    {
        $user = $this->user();
        foreach (['eft' => ['no_rujukan_eft' => 'E'], 'bank_draf' => ['no_bank_draf' => 'B', 'bank_penerbit' => 'X'], 'fpx' => ['rujukan_fpx' => 'F'],
            'kaunter_ptd' => ['no_resit' => 'P', 'nama_kaunter' => 'K'], 'kaunter_pbt' => ['no_resit' => 'Q', 'nama_kaunter' => 'K']] as $kaedah => $fields) {
            $bill = $this->bil(null, ['amaun_pokok' => '300.00']);
            $t = $this->approved($bill, $kaedah);
            $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$t->id.'/rekod', $fields)->assertOk();
            $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$t->id.'/selesai')->assertStatus(422)->assertJsonValidationErrors('resit');
            $this->upload($t, 'R-'.$kaedah);
            $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$t->id.'/selesai')->assertOk()->assertJsonPath('data.status_transaksi', 'selesai');
            $bill->refresh();
            $this->assertSame('dibayar', $bill->status_bayaran, $kaedah);
            $this->assertSame($kaedah, $bill->kaedah_bayaran);
        }
        $this->assertAudited('selesai_bayaran', ['modul' => 'cki', 'status_transaksi' => 'gagal']);
    }

    public function test_SRS_CKI_15_direct_api_payment_saves_receipt_to_dms_updates_ledger_and_creates_grp_reconciliation_record(): void
    {
        $user = $this->user();
        $o = $this->obligasi('taksiran_pbt', null, 'TK-555');
        app(SemakanService::class)->jalankan('MBPJ');
        $bill = Cukai::query()->withoutGlobalScope('access')->where('obligasi_id', $o->id)->sole();
        $t = $this->approved($bill, 'fpx');
        $this->actingAs($user)->postJson('/api/v1/cki/transaksi/'.$t->id.'/laksana')->assertOk()->assertJsonPath('data.status_transaksi', 'selesai')->assertJsonPath('data.melalui_api', true);
        $t->refresh();
        $this->assertStringStartsWith('MBI-R-', (string) $t->no_resit);
        $this->assertSame('api', $this->dms->docs[$t->id_dokumen_resit]['metadata']['sumber']);
        $bill->refresh();
        $this->assertSame('dibayar', $bill->status_bayaran);
        $this->assertSame($t->no_resit, $bill->no_resit);
        $this->assertSame($t->amaun, Lejar::query()->where('id_bil', $bill->id)->where('jenis', 'bayaran')->value('kredit'));
        $this->assertDatabaseHas('penyelarasan_grp_cukai', ['id_transaksi' => $t->id, 'jenis' => 'penyelarasan', 'status_padanan' => 'menunggu']);
        $this->assertSame(1, Penyelarasan::query()->withoutGlobalScope('access')->count());
        // The next enquiry sees the bill paid; nothing changes.
        $this->assertSame(1, app(SemakanService::class)->jalankan('MBPJ')['tanpa_ubah']);
    }

    public function test_SRS_CKI_15_fpx_through_api_is_refused_when_the_agency_has_no_payment_service(): void
    {
        $t = $this->approved($this->bil(), 'fpx');
        app(LiputanService::class)->kemaskini($this->agensi('PTD-1008'), ['perkhidmatan_bayaran' => false]);
        $this->actingAs($this->user())->postJson('/api/v1/cki/transaksi/'.$t->id.'/laksana')->assertStatus(422)->assertJsonValidationErrors('kaedah_bayaran');
    }
}
