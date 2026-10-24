<?php

namespace Tests\Modules\Ptn;

use App\Modules\Ptn\Models\LaporanLawatan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Services\LaporanService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** SRS-PTN-09/10, acceptance #4: auto report, WFE validation, lock, addendum. */
class LaporanLawatanTest extends PtnTestCase
{
    public function test_SRS_PTN_09_report_generated_from_form_and_photos_and_sent_for_validation(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $v = $this->lawatanLengkap($me, $this->jadual($this->lot(), $me), ['item' => $this->item(['CER-02' => ['status_item' => 'isu', 'catatan' => 'Tanaman pisang']])]);

        $r = LaporanLawatan::query()->withoutGlobalScope('access')->where('id_lawatan', $v->id)->firstOrFail();
        $this->assertSame(['asal', 'menunggu', false], [$r->jenis, $r->status, $r->status_kunci]);
        $k = $r->kandungan;
        $this->assertSame($v->no_rujukan, $k['no_rujukan']);
        $this->assertCount(11, $k['item']);
        $this->assertCount(1, $k['gambar']);
        $this->assertSame('dalam', $k['gambar'][0]['lingkungan']);
        $this->assertSame('pendudukan_haram', $k['isu'][0]['kategori_isu']);
        $this->assertStringContainsString('<svg', (string) $k['peta_svg']);
        $this->assertSame(LaporanService::cincang($k), $r->cincangan_kandungan);
        $flow = $this->workflow->of('ptn.pengesahan_laporan');
        $this->assertSame(['laporan_lawatan', $r->id], [$flow[0]['subjectType'], $flow[0]['subjectId']]);

        $pdf = $this->actingAs($sup)->get('/api/v1/ptn/laporan-lawatan/'.$r->id.'/pdf')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->assertAudited('muat_turun', ['modul' => 'ptn', 'nama_entiti' => 'laporan_lawatan']);
    }

    public function test_SRS_PTN_10_validated_report_is_locked_everywhere_and_corrected_only_by_linked_addendum(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $j = $this->jadual($this->lot(), $me);
        $v = $this->lawatanLengkap($me, $j);
        $r = LaporanLawatan::query()->withoutGlobalScope('access')->where('id_lawatan', $v->id)->firstOrFail();

        $this->complete('ptn.pengesahan_laporan', $r->id, 'lulus', $sup->id);
        $r->refresh();
        $this->assertTrue($r->status_kunci);
        $this->assertSame('disahkan', $r->status);
        $this->assertSame('laporan', $this->documents->docs[$r->id_dokumen_dms]['category']);
        $v->refresh();
        $this->assertSame(['disahkan', 'disahkan', $sup->id], [$v->status_lawatan, $v->status_pengesahan, $v->id_pegawai_pengesah]);
        $this->assertSame('selesai', $j->refresh()->status_pelaksanaan);
        $this->assertNotNull($this->actingAs($sup)->getJson('/api/v1/ptn/laporan-lawatan/'.$r->id.'/pdf')->assertOk()->json('data.url'));

        // No interface can change it: API …
        $this->actingAs($me)->putJson('/api/v1/ptn/lapangan/lawatan/'.$v->id, ['ringkasan_penemuan' => 'ubah'])->assertUnprocessable();
        $this->photo($me, $v->id)->assertUnprocessable();
        // … nor the database itself.
        foreach ([
            fn () => DB::update('UPDATE rekod_lawatan SET ringkasan_penemuan = ? WHERE id = ?', ['ubah', $v->id]),
            fn () => DB::update('UPDATE item_senarai_semak SET status_item = ? WHERE id_lawatan = ?', ['kritikal', $v->id]),
            fn () => DB::update('UPDATE laporan_lawatan SET kandungan = ? WHERE id = ?', ['{}', $r->id]),
            fn () => DB::delete('DELETE FROM laporan_lawatan WHERE id = ?', [$r->id]),
        ] as $attempt) {
            DB::beginTransaction();
            try {
                $attempt();
                $this->fail('Kemas kini rekod dikunci sepatutnya ditolak.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('PTN_DIKUNCI', $e->getMessage());
            } finally {
                DB::rollBack();
            }
        }

        $this->actingAs($me)->postJson('/api/v1/ptn/laporan-lawatan/'.$r->id.'/tambahan', ['catatan' => 'Pembetulan keluasan pencerobohan'])->assertForbidden();
        $add = $this->actingAs($sup)->postJson('/api/v1/ptn/laporan-lawatan/'.$r->id.'/tambahan', ['catatan' => 'Pembetulan: pondok di sudut barat, bukan timur.'])->assertCreated()->json('data');
        $this->assertSame(['tambahan', $r->id, 'menunggu'], [$add['jenis'], $add['id_laporan_asal'], $add['status']]);
        $this->complete('ptn.pengesahan_laporan', $add['id'], 'lulus', $sup->id);
        $this->assertTrue((bool) LaporanLawatan::query()->withoutGlobalScope('access')->findOrFail($add['id'])->status_kunci);
        $this->assertSame($r->cincangan_kandungan, $r->refresh()->cincangan_kandungan);
        $laporan = $this->actingAs($sup)->getJson('/api/v1/ptn/lawatan/'.$v->id)->assertOk()->json('data.laporan');
        $this->assertSame(['asal', 'tambahan'], array_column($laporan, 'jenis'));
    }

    public function test_SRS_PTN_09_rejected_report_returns_the_visit_for_correction(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $v = $this->lawatanLengkap($me, $this->jadual($this->lot(), $me));
        $r = LaporanLawatan::query()->withoutGlobalScope('access')->where('id_lawatan', $v->id)->firstOrFail();
        $this->complete('ptn.pengesahan_laporan', $r->id, 'tolak', null, 'Gambar kabur');
        $this->assertSame('dikembalikan', $v->refresh()->status_lawatan);
        $this->assertCount(1, $this->notifier->of('ptn.laporan_dikembalikan'));
        $this->actingAs($me)->putJson('/api/v1/ptn/lapangan/lawatan/'.$v->id, ['ringkasan_penemuan' => 'Dibetulkan'])->assertOk();
        $this->actingAs($me)->postJson('/api/v1/ptn/lapangan/lawatan/'.$v->id.'/hantar')->assertOk();
        $this->assertSame(2, LaporanLawatan::query()->withoutGlobalScope('access')->where('id_lawatan', $v->id)->count());
        $this->assertSame('dihantar', RekodLawatan::query()->withoutGlobalScope('access')->findOrFail($v->id)->status_lawatan);
    }

    public function test_SRS_PTN_09_missing_flow_does_not_lose_the_submission(): void
    {
        $this->workflow->noFlow = true;
        $me = $this->user(self::LAPANGAN, ['1008']);
        $id = $this->syncVisit($me, $this->jadual($this->lot(), $me))->json('data.id');
        $this->photo($me, $id)->assertCreated();
        $res = $this->actingAs($me)->postJson('/api/v1/ptn/lapangan/lawatan/'.$id.'/hantar')->assertOk();
        $this->assertNotEmpty($res->json('amaran'));
        $r = LaporanLawatan::query()->withoutGlobalScope('access')->where('id_lawatan', $id)->firstOrFail();
        $this->assertNull($r->id_aliran);
        $this->workflow->noFlow = false;
        $this->actingAs($me)->postJson('/api/v1/ptn/laporan-lawatan/'.$r->id.'/mula-pengesahan')->assertOk()->assertJsonPath('data.id_aliran', 'wf-1');
    }
}
