<?php

namespace Tests\Modules\Pjk;

use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\LejarTransaksi;
use App\Modules\Pjk\Models\PelarasanLejar;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Models\TemplatSurat;
use App\Modules\Pjk\Services\BayaranService;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Pjk\Support\Penomboran;

class PelarasanSuratTest extends PjkTestCase
{
    public function test_SRS_PJK_28_acceptance_7_adjustment_cannot_be_completed_by_its_requester(): void
    {
        $requester = $this->user();
        $k = $this->active($requester);
        $before = LejarTransaksi::query()->where('kontrak_id', $k->id)->count();
        $id = $this->actingAs($requester)->postJson("/api/v1/pjk/kontrak/{$k->id}/pelarasan", ['jenis' => 'pelarasan_kredit', 'amaun' => '200.00', 'justifikasi' => 'Pampasan gangguan bekalan air'])->assertCreated()->json('data.id');
        $this->assertSame(Aliran::PELARASAN, $this->workflow->last()['flow']);
        $this->assertSame($before, LejarTransaksi::query()->where('kontrak_id', $k->id)->count()); // not effective yet

        $this->complete(Aliran::PELARASAN, 'pelarasan_lejar', $id, 'lulus', $requester->id);
        $p = PelarasanLejar::query()->findOrFail($id);
        $this->assertSame('tolak', $p->status);
        $this->assertSame($before, LejarTransaksi::query()->where('kontrak_id', $k->id)->count());

        $id2 = $this->actingAs($requester)->postJson("/api/v1/pjk/kontrak/{$k->id}/pelarasan", ['jenis' => 'pelarasan_kredit', 'amaun' => '200.00', 'justifikasi' => 'Pampasan gangguan bekalan air'])->json('data.id');
        $approver = $this->user(['pjk.kontrak.lihat']);
        $this->complete(Aliran::PELARASAN, 'pelarasan_lejar', $id2, 'lulus', $approver->id);
        $row = LejarTransaksi::query()->where('pelarasan_id', $id2)->sole();
        $this->assertSame(['pelarasan_kredit', '200.00', 'diluluskan', $approver->id], [$row->jenis, $row->kredit, $row->status_pelarasan, $row->id_pegawai]);
        $this->assertStringContainsString('Pampasan gangguan', (string) $row->keterangan);
        $audit = $this->assertAudited('kemas_kini', ['nama_entiti' => 'pelarasan_lejar', 'id_rekod' => $id2]);
        $audit->assertFieldChanged('id_pelulus', null, (string) $approver->id);
        $this->actingAs($this->user(['pjk.kontrak.lihat']))->postJson("/api/v1/pjk/kontrak/{$k->id}/pelarasan", [])->assertForbidden();
        $this->actingAs($requester)->postJson("/api/v1/pjk/kontrak/{$k->id}/pelarasan", ['jenis' => 'pelarasan_kredit', 'amaun' => '1', 'justifikasi' => 'pendek'])->assertStatus(422)->assertJsonValidationErrors(['justifikasi']);
    }

    public function test_SRS_PJK_28_receipt_cancellation_by_credit_receipt_and_waiver(): void
    {
        $user = $this->user();
        $k = $this->active($user, null, ['tarikh_mula' => '2026-10-01', 'tarikh_tamat' => '2027-09-30']);
        $b = Bayaran::query()->create(['no_bayaran' => Penomboran::next('bayaran'), 'kontrak_id' => $k->id, 'saluran' => 'kaunter', 'amaun' => '1500.00', 'tarikh_bayaran' => '2026-10-14', 'status' => 'menunggu_pengesahan']);
        app(BayaranService::class)->settle($b, null);
        $row = LejarTransaksi::query()->where('bayaran_id', $b->id)->where('jenis', 'bayaran')->sole();
        $oct = JadualCaj::query()->where('kontrak_id', $k->id)->where('tempoh', '2026-10')->sole();
        $this->assertSame('dibayar', $oct->status_caj);

        $id = $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/pelarasan", ['jenis' => 'pembatalan', 'lejar_id_sasaran' => $row->id, 'justifikasi' => 'Cek dipulangkan oleh bank'])->assertCreated()->json('data.id');
        $this->complete(Aliran::PELARASAN, 'pelarasan_lejar', $id, 'lulus', $this->user(['pjk.kontrak.lihat'])->id);

        $rev = LejarTransaksi::query()->where('id_transaksi_asal', $row->id)->sole();
        $this->assertSame(['pembatalan', '1500.00'], [$rev->jenis, $rev->debit]);
        $credit = Resit::query()->where('jenis', 'resit_kredit')->sole();
        $this->assertSame(['-1500.00', 'PJK/RK/2026/000001'], [$credit->amaun, $credit->no_resit]);
        $this->assertSame('dibatalkan', $b->refresh()->status);
        $this->assertSame('belum_bayar', $oct->refresh()->status_caj);
        $this->assertSame(1, Resit::query()->where('jenis', 'resit')->count()); // original kept
        $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/pelarasan", ['jenis' => 'pembatalan', 'lejar_id_sasaran' => $row->id, 'justifikasi' => 'Cubaan kali kedua'])->assertStatus(422);

        // Waiver of the October rent.
        $w = $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/pelarasan", ['jenis' => 'pengecualian', 'caj_id' => $oct->id, 'amaun' => '1500.00', 'justifikasi' => 'Pengecualian sewa bulan Oktober (MMKN)'])->assertCreated()->json('data.id');
        $this->complete(Aliran::PELARASAN, 'pelarasan_lejar', $w, 'lulus', $this->user(['pjk.kontrak.lihat'])->id);
        $this->assertSame('dikecualikan', $oct->refresh()->status_caj);
        $this->assertSame('3000.00', LejarTransaksi::query()->where('kontrak_id', $k->id)->orderByDesc('id')->value('baki_berjalan')); // only the deposit left
    }

    public function test_SRS_PJK_09_letters_from_versioned_templates_with_merge_fields_and_reference_numbers(): void
    {
        $user = $this->user();
        $k = $this->active($user);
        $templates = $this->actingAs($user)->getJson('/api/v1/pjk/templat')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['tawaran', 'peringatan_bayaran', 'tamat_tempoh', 'teguran', 'penamatan'], array_column($templates, 'jenis_surat'));
        $reminder = collect($templates)->firstWhere('jenis_surat', 'peringatan_bayaran');

        $res = $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/surat", ['templat_id' => $reminder['id']])->assertCreated();
        $this->assertSame('PJK/S/2026/00001', $res->json('data.no_rujukan_surat'));
        $text = $res->json('data.kandungan');
        $this->assertStringContainsString((string) $k->no_kontrak, $text);
        $this->assertStringContainsString('RM ', $text);
        $this->assertStringContainsString('PJK/S/2026/00001', $text);
        $this->assertStringNotContainsString('{{', $text);
        $this->assertCount(1, $this->dms->ofCategory('surat_pjk'));
        $this->assertSame('PJK/S/2026/00002', $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/surat", ['templat_id' => $reminder['id']])->json('data.no_rujukan_surat'));

        // Configurable without code: a new version; unknown merge fields refused.
        $admin = $this->user(['pjk.templat.kemas_kini', 'pjk.surat.cipta']);
        $this->actingAs($admin)->putJson("/api/v1/pjk/templat/{$reminder['id']}", ['tajuk' => 'Peringatan', 'kandungan' => 'Hai {{medan_rekaan}}'])->assertStatus(422);
        $this->actingAs($admin)->putJson("/api/v1/pjk/templat/{$reminder['id']}", ['tajuk' => 'Peringatan Bayaran (v2)', 'kandungan' => 'Tuan {{nama_penyewa}}, baki {{baki_tunggakan}} bagi {{no_kontrak}}.'])->assertOk()->assertJsonPath('data.versi', 2);
        $this->assertFalse(TemplatSurat::query()->findOrFail($reminder['id'])->status_aktif);
        $this->actingAs($user)->postJson("/api/v1/pjk/kontrak/{$k->id}/surat", ['templat_id' => $reminder['id']])->assertStatus(422);
        $this->actingAs($user)->putJson("/api/v1/pjk/templat/{$reminder['id']}", ['tajuk' => 'x', 'kandungan' => 'y'])->assertForbidden();
    }
}
