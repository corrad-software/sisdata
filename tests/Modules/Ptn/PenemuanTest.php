<?php

namespace Tests\Modules\Ptn;

use App\Modules\Ptn\Contracts\MaintenanceNeeds;
use App\Modules\Ptn\Gis\PemantauanLayer;
use App\Modules\Ptn\Gis\RisikoLotLayer;
use App\Modules\Ptn\Models\CadanganKemaskiniLot;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\RiskRatingLot;
use App\Modules\Ptn\Models\TindakanPembetulan;
use App\Modules\Ptn\Reminders\TindakanHampirTamat;
use App\Modules\Ptn\Services\PenemuanService;
use App\Modules\Ptn\Services\TindakanService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** SRS-PTN-08/13/17/18/19/21, acceptance #6. */
class PenemuanTest extends PtnTestCase
{
    public function test_SRS_PTN_17_critical_finding_opens_issue_and_complaint_notifies_and_marks_lot_red(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $enf = $this->user(['ptn.isu.lihat']);
        $prop = $this->user(['ptn.isu.lihat']);
        $this->recipients->roles = ['penguatkuasaan' => [$enf->id], 'pengurusan_hartanah' => [$prop->id]];
        $this->directory->supervisors[$me->id] = [$sup->id];
        $lot = $this->lot();

        $v = $this->lawatanLengkap($me, $this->jadual($lot, $me), ['item' => $this->item(['CER-01' => ['status_item' => 'kritikal', 'catatan' => 'Rumah kekal dibina']])]);

        $isu = IsuPemantauan::query()->withoutGlobalScope('access')->where('id_lawatan', $v->id)->sole();
        $this->assertSame(['binaan_haram', 'kritikal', 'terbuka'], [$isu->kategori_isu, $isu->tahap_keterukan, $isu->status_isu]);
        $aduan = DB::table('aduan')->where('id', $isu->id_aduan_berkaitan)->first();
        $this->assertSame(['ptn', 'ptn_lawatan:'.$v->id, $lot, 'BINAAN_HARAM'], [$aduan->saluran_penerimaan, $aduan->rujukan_sumber, $aduan->lot_id, $aduan->kategori]);
        $sent = $this->notifier->of('ptn.penemuan_kritikal');
        $this->assertCount(1, $sent);
        $this->assertEqualsCanonicalizing([$enf->id, $prop->id, $sup->id], $sent[0]['to']);

        $r = RiskRatingLot::query()->withoutGlobalScope('access')->where('lot_id', $lot)->where('semasa', true)->sole();
        $this->assertTrue($r->paksa_merah);
        $this->assertSame('merah', $r->kod_warna);
        $risk = app(RisikoLotLayer::class)->features($sup, null, ['kod_daerah' => '1008']);
        $this->assertSame('merah', collect($risk['features'])->firstWhere('id', $lot)['properties']['kod_warna']);
        $mon = app(PemantauanLayer::class)->features($sup);
        $this->assertSame('tertunggak', collect($mon['features'])->firstWhere('id', $lot)['properties']['status_pemantauan']);
    }

    public function test_SRS_PTN_18_issue_escalates_to_complaint_without_reentry_and_once(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $v = $this->lawatanLengkap($me, $this->jadual($this->lot(), $me), ['item' => $this->item(['AST-01' => ['status_item' => 'isu', 'catatan' => 'Bumbung bocor']])]);
        $isu = IsuPemantauan::query()->withoutGlobalScope('access')->where('id_lawatan', $v->id)->sole();
        $this->assertNull($isu->id_aduan_berkaitan, 'aset rosak (bukan kritikal) tidak membuka aduan automatik');

        $id = $this->actingAs($sup)->postJson('/api/v1/ptn/isu/'.$isu->id.'/eskalasi-aduan')->assertOk()->json('data.id_aduan');
        $this->assertSame($id, $this->actingAs($sup)->postJson('/api/v1/ptn/isu/'.$isu->id.'/eskalasi-aduan')->assertOk()->json('data.id_aduan'));
        $aduan = DB::table('aduan')->where('id', $id)->first();
        $this->assertSame('KEROSAKAN_ASET', $aduan->kategori);
        $this->assertStringContainsString('Bumbung bocor', (string) $aduan->keterangan);
        $this->assertStringContainsString('/admin/ptn/lawatan/'.$v->id, (string) $aduan->keterangan);
        // MOD-KES binds LegalCaseIntake: escalation opens a case (once).
        $kes = $this->actingAs($sup)->postJson('/api/v1/ptn/isu/'.$isu->id.'/eskalasi-kes')->assertOk()->json('data.id_kes');
        $this->assertSame($kes, $this->actingAs($sup)->postJson('/api/v1/ptn/isu/'.$isu->id.'/eskalasi-kes')->assertOk()->json('data.id_kes'));
        $this->actingAs($me)->postJson('/api/v1/ptn/isu/'.$isu->id.'/eskalasi-aduan')->assertForbidden();
    }

    public function test_SRS_PTN_13_corrective_action_tracked_until_verified_and_issue_closed(): void
    {
        $me = $this->user([...self::LAPANGAN, 'ptn.tindakan.kemas_kini'], ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $v = $this->lawatanLengkap($me, $this->jadual($this->lot(), $me), ['item' => $this->item(['FIZ-02' => 'isu'])]);
        $isu = IsuPemantauan::query()->withoutGlobalScope('access')->where('id_lawatan', $v->id)->sole();

        $t = $this->actingAs($sup)->postJson('/api/v1/ptn/isu/'.$isu->id.'/tindakan', ['langkah_tindakan' => 'Pembersihan sampah', 'id_pegawai_bertanggungjawab' => $me->id, 'tarikh_sasaran' => now()->addDays(30)->toDateString()])
            ->assertCreated()->assertJsonPath('data.kod_warna', 'hijau')->json('data');
        $this->assertSame('dalam_tindakan', $isu->refresh()->status_isu);
        $this->assertCount(1, $this->notifier->of('ptn.tindakan_ditugaskan'));

        $this->actingAs($me)->putJson('/api/v1/ptn/tindakan/'.$t['id'], ['peratus_kemajuan' => 50, 'status_kemajuan' => 'dalam_tindakan'])->assertOk()->assertJsonPath('data.peratus_kemajuan', 50);
        $this->actingAs($me)->putJson('/api/v1/ptn/tindakan/'.$t['id'], ['tarikh_sasaran' => now()->addDays(40)->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('justifikasi_lanjutan');
        $this->actingAs($me)->putJson('/api/v1/ptn/tindakan/'.$t['id'], ['tarikh_sasaran' => now()->addDays(40)->toDateString(), 'justifikasi_lanjutan' => 'Kontraktor lewat'])->assertOk();
        $this->actingAs($this->user(['ptn.tindakan.kemas_kini'], ['1008']))->putJson('/api/v1/ptn/tindakan/'.$t['id'], ['peratus_kemajuan' => 1])->assertForbidden();

        $this->actingAs($me)->postJson('/api/v1/ptn/tindakan/'.$t['id'].'/selesai', ['catatan' => 'Siap dibersihkan'])->assertOk()->assertJsonPath('data.status_kemajuan', 'menunggu_pengesahan');
        $this->assertSame((string) $t['id'], $this->workflow->of('ptn.pengesahan_tindakan')[0]['subjectId']);
        $this->complete('ptn.pengesahan_tindakan', (string) $t['id'], 'lulus', $sup->id);
        $this->assertSame('selesai', TindakanPembetulan::query()->withoutGlobalScope('access')->findOrFail($t['id'])->status_kemajuan);
        $isu->refresh();
        $this->assertSame('ditutup', $isu->status_isu);
        $this->assertNotNull($isu->tarikh_ditutup);
    }

    public function test_SRS_PTN_19_overdue_action_notifies_officer_and_supervisor_and_reminder_before_deadline(): void
    {
        $me = $this->user(['ptn.isu.lihat', 'ptn.tindakan.kemas_kini'], ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $this->directory->supervisors[$me->id] = [$sup->id];
        $isu = app(PenemuanService::class)->cipta(['lot' => ['id' => $this->lot(), 'kod_daerah' => '1008', 'kod_mukim' => '100803', 'klasifikasi' => 'dalaman'], 'kategori_isu' => 'pembuangan_sampah', 'tahap_keterukan' => 'sederhana', 'keterangan' => 'Sampah'], $sup);
        $service = app(TindakanService::class);
        $late = $service->cipta($isu, ['langkah_tindakan' => 'Bersihkan', 'id_pegawai_bertanggungjawab' => $me->id, 'tarikh_sasaran' => now()->addDays(10)->toDateString()], $sup);
        $soon = $service->cipta($isu, ['langkah_tindakan' => 'Pasang papan tanda', 'id_pegawai_bertanggungjawab' => $me->id, 'tarikh_sasaran' => now()->addDays(3)->toDateString()], $sup);
        DB::table('tindakan_pembetulan')->where('id', $late->id)->update(['tarikh_sasaran' => now()->subDays(2)->toDateString()]);

        $r = $service->semakTertunggak();
        $this->assertSame(1, $r['lewat']);
        $sent = $this->notifier->of('ptn.tindakan_lewat');
        $this->assertEqualsCanonicalizing([$me->id, $sup->id], $sent[0]['to']);
        $this->assertSame(2, $sent[0]['data']['hari_lewat']);
        $this->assertSame('merah', DB::table('tindakan_pembetulan')->where('id', $late->id)->value('kod_warna_status'));

        $due = iterator_to_array((new TindakanHampirTamat)->due(3, CarbonImmutable::now()));
        $this->assertSame(['tindakan_pembetulan:'.$soon->id], array_map(fn ($x) => $x->key, $due));
    }

    public function test_SRS_PTN_21_maintenance_needs_recorded_and_exposed_to_asset_module(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $lot = $this->lot();
        $this->lawatanLengkap($me, $this->jadual($lot, $me), ['penyelenggaraan' => [['jenis' => 'pagar', 'keterangan' => 'Pagar depan roboh 20 m', 'keutamaan' => 'tinggi', 'tarikh_jadual' => now()->addMonth()->toDateString()]]]);
        $needs = app(MaintenanceNeeds::class)->forLot($lot);
        $this->assertCount(1, $needs);
        $this->assertSame(['pagar', 'tinggi', 'dijadual'], [$needs[0]['jenis'], $needs[0]['keutamaan'], $needs[0]['status']]);
        app(MaintenanceNeeds::class)->link($needs[0]['id'], 'AST-0001', 'dijadual');
        $this->assertSame('AST-0001', app(MaintenanceNeeds::class)->forLot($lot)[0]['id_aset_fizikal']);
    }

    public function test_SRS_PTN_08_lot_update_proposal_applied_to_master_only_after_approval(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $lot = $this->lot(kategori: 'PERTANIAN');
        $prop = ['cadangan' => [['medan' => 'kategori_guna_tanah', 'nilai_dicadang' => 'BANGUNAN', 'justifikasi' => 'Bangunan kedai telah didirikan']]];
        $this->lawatanLengkap($me, $this->jadual($lot, $me), $prop);
        $c = CadanganKemaskiniLot::query()->withoutGlobalScope('access')->sole();
        $this->assertSame(['menunggu', 'PERTANIAN'], [$c->status, $c->nilai_semasa]);
        $this->assertSame('PERTANIAN', DB::table('lot_tanah')->where('id', $lot)->value('kategori_guna_tanah'));
        $this->assertCount(1, $this->workflow->of('ptn.kemaskini_lot'));

        $approver = $this->user(['ptn.lawatan.lihat']);
        $this->complete('ptn.kemaskini_lot', (string) $c->id, 'lulus', $approver->id);
        $this->assertSame('BANGUNAN', DB::table('lot_tanah')->where('id', $lot)->value('kategori_guna_tanah'));
        $this->assertSame('lulus', $c->refresh()->status);
        $this->assertAudited('kemas_kini', ['modul' => 'pro', 'nama_entiti' => 'lot_tanah', 'id_rekod' => $lot]);

        // A rejected proposal changes nothing.
        $lot2 = $this->lot();
        $this->lawatanLengkap($me, $this->jadual($lot2, $me), $prop);
        $c2 = CadanganKemaskiniLot::query()->withoutGlobalScope('access')->where('lot_id', $lot2)->sole();
        $this->complete('ptn.kemaskini_lot', (string) $c2->id, 'tolak', $approver->id, 'Tiada kelulusan PBT');
        $this->assertSame('PERTANIAN', DB::table('lot_tanah')->where('id', $lot2)->value('kategori_guna_tanah'));
    }
}
