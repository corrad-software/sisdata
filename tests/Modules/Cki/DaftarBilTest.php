<?php

namespace Tests\Modules\Cki;

use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Lejar;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Models\Resit;
use App\Modules\Cki\Services\LiputanService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DaftarBilTest extends CkiTestCase
{
    public function test_SRS_CKI_01_obligation_is_linked_to_a_lot_in_the_master_register(): void
    {
        $user = $this->user();
        $lot = $this->lot();
        $res = $this->actingAs($user)->postJson('/api/v1/cki/obligasi', ['lot_id' => $lot, 'jenis_cukai' => 'tanah', 'no_akaun_cukai' => 'CT-0001'])->assertCreated();
        $res->assertJsonPath('data.lot_id', $lot)->assertJsonPath('data.kod_agensi', 'PTD-1008')->assertJsonPath('data.pihak_pengeluar', 'ptd')
            ->assertJsonPath('data.kod_daerah', '1008')->assertJsonPath('data.status_liputan_api', 'dalam');

        // Assessment tax goes to the district's PBT (MOD-REF ref_pbt.daerah_kod).
        $this->actingAs($user)->postJson('/api/v1/cki/obligasi', ['lot_id' => $lot, 'jenis_cukai' => 'taksiran_pbt', 'no_akaun_cukai' => 'TK-1', 'id_premis' => 7])
            ->assertCreated()->assertJsonPath('data.kod_agensi', 'MBPJ')->assertJsonPath('data.pihak_pengeluar', 'pbt')->assertJsonPath('data.id_premis', 7);
        // premium / fi are obligations too.
        $this->actingAs($user)->postJson('/api/v1/cki/obligasi', ['lot_id' => $lot, 'jenis_cukai' => 'premium', 'no_akaun_cukai' => 'PR-1'])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/cki/obligasi', ['lot_id' => '00000000-0000-4000-8000-000000000000', 'jenis_cukai' => 'tanah', 'no_akaun_cukai' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('lot_id');
        $this->actingAs($user)->postJson('/api/v1/cki/obligasi', ['lot_id' => $lot, 'jenis_cukai' => 'tanah', 'no_akaun_cukai' => 'CT-0001'])
            ->assertStatus(422)->assertJsonValidationErrors('no_akaun_cukai');
        $this->assertSame(3, Obligasi::query()->withoutGlobalScope('access')->where('lot_id', $lot)->count());
    }

    public function test_SRS_CKI_01_district_officer_cannot_reach_another_districts_lot_or_bill(): void
    {
        $petaling = $this->user(self::PEGAWAI, ['1008']);
        $sepangLot = $this->lot(['kod_mukim' => '100901']);
        $this->actingAs($petaling)->postJson('/api/v1/cki/obligasi', ['lot_id' => $sepangLot, 'jenis_cukai' => 'tanah', 'no_akaun_cukai' => 'S-1'])
            ->assertStatus(422)->assertJsonValidationErrors('lot_id');

        $sepangBill = $this->bil($this->obligasi('tanah', null, null, '100901'));
        $mine = $this->bil();
        $this->actingAs($petaling)->getJson('/api/v1/cki/bil/'.$sepangBill->id)->assertNotFound();
        $this->actingAs($petaling)->getJson('/api/v1/cki/bil/'.$mine->id)->assertOk();
        $ids = collect($this->actingAs($petaling)->getJson('/api/v1/cki/bil')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($sepangBill->id));
    }

    public function test_SRS_CKI_01_endpoints_require_cki_permissions(): void
    {
        $nobody = $this->user(['pro.lot.lihat']);
        $bill = $this->bil();
        $this->actingAs($nobody)->getJson('/api/v1/cki/bil')->assertForbidden();
        $this->actingAs($nobody)->getJson('/api/v1/cki/bil/'.$bill->id)->assertForbidden();
        $this->actingAs($nobody)->postJson('/api/v1/cki/obligasi', [])->assertForbidden();
        $this->actingAs($nobody)->postJson('/api/v1/cki/bil/'.$bill->id.'/bayaran', ['kaedah_bayaran' => 'eft'])->assertForbidden();
        $this->actingAs($nobody)->getJson('/api/v1/cki/integrasi')->assertForbidden();
        $this->actingAs($nobody)->getJson('/api/v1/cki/dashboard')->assertForbidden();
        $this->actingAs($nobody)->postJson('/api/v1/cki/rekonsiliasi', [])->assertForbidden();
    }

    public function test_SRS_CKI_02_bill_records_type_issuer_account_year_principal_penalty_due_date_and_status(): void
    {
        $user = $this->user();
        $o = $this->obligasi('tanah', null, 'CT-77');
        app(LiputanService::class)->kemaskini($this->agensi('PTD-1008'), ['perkhidmatan_semakan' => false]);
        $res = $this->actingAs($user)->postJson('/api/v1/cki/bil', [
            'obligasi_id' => $o->id, 'tahun_taksiran' => 2026, 'no_bil' => 'TA-2026-1', 'amaun_pokok' => '350.40', 'amaun_denda' => '35.04',
            'tarikh_bil' => '2026-01-02', 'tarikh_akhir' => '2026-05-31',
        ])->assertCreated();
        $res->assertJsonPath('data.jenis_cukai', 'tanah')->assertJsonPath('data.pihak_pengeluar', 'ptd')->assertJsonPath('data.kod_agensi', 'PTD-1008')
            ->assertJsonPath('data.no_bil', 'TA-2026-1')->assertJsonPath('data.tahun_taksiran', 2026)
            ->assertJsonPath('data.amaun_pokok.nilai', '350.40')->assertJsonPath('data.amaun_denda.nilai', '35.04')->assertJsonPath('data.jumlah.nilai', '385.44')
            ->assertJsonPath('data.tarikh_akhir', '2026-05-31')->assertJsonPath('data.status_bayaran', 'tertunggak')->assertJsonPath('data.sumber_rekod', 'manual');
        $detail = $this->actingAs($user)->getJson('/api/v1/cki/bil/'.$res->json('data.id'))->assertOk();
        $detail->assertJsonPath('data.obligasi.no_akaun_cukai', 'CT-77');
        $this->assertSame(['bil', 'denda'], array_column($detail->json('data.lejar'), 'jenis'));
        $this->assertSame('385.44', DB::table('cukai')->where('id', $res->json('data.id'))->value('jumlah'));
    }

    public function test_SRS_CKI_02_bills_and_receipts_cannot_be_deleted_and_corrections_are_justified_adjustments(): void
    {
        $user = $this->user();
        $bill = $this->bil(null, ['amaun_pokok' => '100.00']);
        $this->actingAs($user)->postJson('/api/v1/cki/bil/'.$bill->id.'/pelarasan', ['medan' => 'amaun_pokok', 'nilai' => '120.00', 'justifikasi' => 'Bil pindaan daripada PTD'])
            ->assertOk()->assertJsonPath('data.amaun_pokok.nilai', '120.00')->assertJsonPath('data.versi', 2);
        $this->actingAs($user)->postJson('/api/v1/cki/bil/'.$bill->id.'/pelarasan', ['medan' => 'amaun_pokok', 'nilai' => '1', 'justifikasi' => 'x'])->assertStatus(422);
        $this->assertDatabaseHas('pelarasan_cukai', ['id_bil' => $bill->id, 'medan' => 'amaun_pokok', 'nilai_lama' => '100.00', 'nilai_baharu' => '120.00']);
        $this->assertSame('20.00', Lejar::query()->where('id_bil', $bill->id)->where('jenis', 'pelarasan')->value('debit'));

        foreach (['cukai' => $bill->id, 'resit_cukai' => Resit::query()->create(['id_bil' => $bill->id, 'no_resit' => 'R1', 'amaun' => '1.00', 'tarikh_resit' => '2026-01-01', 'sumber' => 'manual'])->id] as $table => $id) {
            DB::beginTransaction(); // savepoint — the refused statement must not abort the test transaction
            try {
                DB::table($table)->where('id', $id)->delete();
                $this->fail("Delete on {$table} should be refused");
            } catch (QueryException $e) {
                $this->assertStringContainsString('tidak boleh', $e->getMessage());
            } finally {
                DB::rollBack();
            }
        }
        $this->assertDatabaseHas('cukai', ['id' => $bill->id]);
    }

    public function test_SRS_CKI_06_coverage_register_per_land_office_and_pbt_flags_every_obligation(): void
    {
        $admin = $this->user(['cki.integrasi.lihat', 'cki.integrasi.kemas_kini']);
        $agencies = collect($this->actingAs($admin)->getJson('/api/v1/cki/integrasi')->assertOk()->json('data.agensi'))->keyBy('kod_agensi');
        $this->assertEqualsCanonicalizing(['PTD-1008', 'PTD-1009', 'MBPJ', 'MPSEPANG'], $agencies->keys()->all());
        $this->assertSame('ptd', $agencies['PTD-1008']['jenis_agensi']);
        $this->assertTrue($agencies['MBPJ']['perkhidmatan_semakan']);

        $o = $this->obligasi('taksiran_pbt');
        $this->assertSame('dalam', $o->status_liputan_api);
        $this->actingAs($admin)->putJson('/api/v1/cki/integrasi/'.$agencies['MBPJ']['id'], ['perkhidmatan_semakan' => false, 'perkhidmatan_bayaran' => false])
            ->assertOk()->assertJsonPath('data.perkhidmatan_semakan', false);
        $this->assertSame('luar', $o->refresh()->status_liputan_api);
        $this->assertAudited('kemas_kini', ['modul' => 'cki', 'nama_entiti' => 'daftar_liputan_integrasi']);
    }

    public function test_SRS_CKI_07_manual_fallback_only_outside_coverage_or_in_fallback_mode_with_bill_image(): void
    {
        $user = $this->user();
        $o = $this->obligasi('tanah');
        $payload = ['obligasi_id' => $o->id, 'tahun_taksiran' => 2026, 'no_bil' => 'M-1', 'amaun_pokok' => '200', 'tarikh_akhir' => '2026-12-31'];
        // In coverage, API up → automatic records only.
        $this->actingAs($user)->postJson('/api/v1/cki/bil', $payload)->assertStatus(422)->assertJsonValidationErrors('obligasi_id');

        // Out-of-coverage lot: same fields, image to DMS, source manual.
        $luar = $this->obligasi('taksiran_pbt', null, null, '100901');
        app(LiputanService::class)->kemaskini($this->agensi('MPSEPANG'), ['perkhidmatan_semakan' => false]);
        $res = $this->actingAs($user)->post('/api/v1/cki/bil', ['obligasi_id' => $luar->refresh()->id] + $payload + ['fail' => UploadedFile::fake()->create('bil.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $res->assertJsonPath('data.sumber_rekod', 'manual');
        $doc = $this->dms->ofCategory('resit')[0];
        $this->assertSame('cukai', $doc['owner_type']);
        $this->assertSame('bil', $doc['metadata']['jenis_dokumen_cki']);
        $this->assertNotNull(Cukai::query()->withoutGlobalScope('access')->find($res->json('data.id'))->id_dokumen_bil);

        // Receipt for a manually-paid bill.
        $this->actingAs($user)->post('/api/v1/cki/bil/'.$res->json('data.id').'/resit', ['no_resit' => 'R-77', 'fail' => UploadedFile::fake()->create('resit.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.sumber', 'manual');
        $this->assertSame(1, Resit::query()->where('id_bil', $res->json('data.id'))->count());
    }

    public function test_SRS_CKI_16_documents_attach_to_the_bill_with_metadata_and_lot_link(): void
    {
        $user = $this->user();
        $bill = $this->bil();
        foreach (['bil', 'baucar', 'kelulusan'] as $jenis) {
            $this->actingAs($user)->post('/api/v1/cki/bil/'.$bill->id.'/dokumen', ['jenis' => $jenis, 'fail' => UploadedFile::fake()->create($jenis.'.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        }
        $docs = collect($this->dms->docs);
        $this->assertSame(['bil', 'baucar', 'kelulusan'], $docs->pluck('metadata.jenis_dokumen_cki')->all());
        $this->assertSame([$bill->lot_id], $docs->pluck('metadata.lot_id')->unique()->values()->all());
        $this->assertSame('/admin/cki/bil/'.$bill->id, $docs->first()['metadata']['pautan_induk']);
        $this->assertCount(3, $this->actingAs($user)->getJson('/api/v1/cki/bil/'.$bill->id)->json('data.dokumen'));
    }

    public function test_SRS_CKI_24_agencies_without_api_service_are_handled_by_structured_manual_fallback(): void
    {
        $user = $this->user();
        // An agency (e.g. LHDN / a PBT not yet integrated) without the enquiry service.
        app(LiputanService::class)->kemaskini($this->agensi('PTD-1009'), ['perkhidmatan_semakan' => false, 'perkhidmatan_bayaran' => false]);
        $o = $this->obligasi('tanah', null, null, '100901');
        $this->assertSame('luar', $o->status_liputan_api);
        $tasks = $this->actingAs($user)->getJson('/api/v1/cki/tugasan?tahun=2026')->assertOk()->json('data');
        $this->assertSame([$o->id], array_column($tasks, 'id'));
        $this->actingAs($user)->postJson('/api/v1/cki/bil', ['obligasi_id' => $o->id, 'tahun_taksiran' => 2026, 'no_bil' => 'L-1', 'amaun_pokok' => '90', 'tarikh_akhir' => '2026-05-31'])->assertCreated();
        $this->assertSame([], $this->actingAs($user)->getJson('/api/v1/cki/tugasan?tahun=2026')->json('data'));
    }
}
