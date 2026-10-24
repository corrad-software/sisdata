<?php

namespace Tests\Modules\Ptn;

use App\Modules\Ptn\Models\GambarLawatan;
use App\Modules\Ptn\Models\LogPenyegerakan;
use App\Modules\Ptn\Models\RekodLawatan;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** SRS-PTN-03..07: field app (PWA) download, offline sync, geotag + geofence. */
class LapanganTest extends PtnTestCase
{
    public function test_SRS_PTN_05_field_officer_downloads_only_own_assignments_as_offline_bundle(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $other = $this->user(self::LAPANGAN, ['1008']);
        $mine = $this->jadual($this->lot(), $me);
        $this->jadual($this->lot(), $other);
        $this->jadual($this->lot());

        $res = $this->actingAs($me)->getJson('/api/v1/ptn/lapangan/tugasan')->assertOk();
        $this->assertSame([$mine->id], array_column($res->json('data.tugasan'), 'id'));
        $task = $res->json('data.tugasan.0');
        $this->assertContains($task['geometri']['type'], ['Polygon', 'MultiPolygon']);
        $this->assertNotNull($task['pusat']);
        $this->assertNotEmpty($res->json('data.templat.0.senarai_item'));
        $this->assertSame($task['id_templat'], $res->json('data.templat.0.id'));

        // Security control: a field officer only sees visits assigned to them.
        $v = $this->lawatanLengkap($other, $this->jadual($this->lot(), $other));
        $this->actingAs($me)->getJson('/api/v1/ptn/lawatan/'.$v->id)->assertNotFound();
        $this->actingAs($me)->getJson('/api/v1/ptn/lawatan')->assertForbidden();
    }

    public function test_SRS_PTN_05_offline_visit_syncs_idempotently_without_losing_records(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $j = $this->jadual($this->lot(), $me);
        $uuid = (string) Str::uuid();

        $first = $this->syncVisit($me, $j, ['id_luar_talian' => $uuid])->assertCreated();
        $this->assertSame('diterima', $first->json('status_segerak'));
        // The device retries after a dropped connection: same record, no duplicate.
        $again = $this->syncVisit($me, $j, ['id_luar_talian' => $uuid])->assertOk();
        $this->assertSame('pendua', $again->json('status_segerak'));
        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, RekodLawatan::query()->withoutGlobalScope('access')->count());

        $photoId = (string) Str::uuid();
        $this->photo($me, $first->json('data.id'), null, ['id_luar_talian' => $photoId])->assertCreated();
        $this->photo($me, $first->json('data.id'), null, ['id_luar_talian' => $photoId])->assertOk()->assertJsonPath('status_segerak', 'pendua');
        $this->assertSame(1, GambarLawatan::query()->count());

        $this->actingAs($me)->postJson('/api/v1/ptn/lapangan/lawatan/'.$first->json('data.id').'/hantar')->assertOk()->assertJsonPath('data.status_lawatan', 'dihantar');
        $this->assertSame(['diterima', 'pendua', 'diterima', 'pendua', 'diterima'], LogPenyegerakan::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame('dalam_pelaksanaan', $j->refresh()->status_pelaksanaan);
        $this->assertSame(11, DB::table('item_senarai_semak')->count());
    }

    public function test_SRS_PTN_05_schedule_changed_after_download_is_kept_as_conflict_for_the_supervisor(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $other = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $j = $this->jadual($this->lot(), $me);
        $versi = $j->versi;
        // Reassigned on the server while the officer was offline.
        $j->forceFill(['id_pegawai_bertugas' => $other->id, 'versi' => $versi + 1])->save();

        $res = $this->syncVisit($me, $j, ['versi_jadual' => $versi])->assertCreated();
        $this->assertSame('konflik', $res->json('status_segerak'));
        $this->assertSame('konflik', $res->json('data.status_lawatan'));
        $this->assertNotEmpty($res->json('data.sebab_konflik'));
        $id = $res->json('data.id');
        $this->actingAs($me)->postJson('/api/v1/ptn/lapangan/lawatan/'.$id.'/hantar')->assertUnprocessable();

        $this->actingAs($sup)->postJson('/api/v1/ptn/lawatan/'.$id.'/konflik', ['keputusan' => 'terima', 'catatan' => 'Lawatan sah'])->assertOk()->assertJsonPath('data.status_lawatan', 'draf');
        // The schedule now belongs to someone else, so the accepted visit stands alone.
        $this->assertNull(RekodLawatan::query()->withoutGlobalScope('access')->find($id)->id_jadual);
        $this->photo($me, $id)->assertCreated();
        $this->actingAs($me)->postJson('/api/v1/ptn/lapangan/lawatan/'.$id.'/hantar')->assertOk();
    }

    public function test_SRS_PTN_03_visit_records_condition_encroachment_maintenance_photos_coordinates_and_recommendation(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $j = $this->jadual($this->lot(), $me);
        $v = $this->lawatanLengkap($me, $j, [
            'keadaan_tapak' => 'buruk', 'isu_pencerobohan' => 'Pondok kayu di sudut timur', 'isu_penyelenggaraan' => 'Pagar roboh',
            'cadangan_tindakan' => 'Notis pengosongan', 'item' => $this->item(['CER-01' => ['status_item' => 'isu', 'catatan' => 'Pondok', 'tahap_keterukan' => 'tinggi']]),
        ]);
        $d = $this->actingAs($sup)->getJson('/api/v1/ptn/lawatan/'.$v->id)->assertOk()->json('data');
        $this->assertSame('buruk', $d['keadaan_tapak']);
        $this->assertSame('Pondok kayu di sudut timur', $d['isu_pencerobohan']);
        $this->assertSame('Pagar roboh', $d['isu_penyelenggaraan']);
        $this->assertSame('Notis pengosongan', $d['cadangan_tindakan']);
        $this->assertEqualsWithDelta(self::PUSAT_PETALING[0], $d['lat'], 0.00001);
        $this->assertTrue($d['dalam_lingkungan']);
        $this->assertCount(1, $d['gambar']);
        $this->assertSame('isu', collect($d['item'])->firstWhere('kod_item', 'CER-01')['status_item']);
        $this->assertAudited('cipta', ['modul' => 'ptn', 'nama_entiti' => 'rekod_lawatan']);
    }

    public function test_SRS_PTN_04_checklist_must_be_complete_with_baik_isu_kritikal_status(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $j = $this->jadual($this->lot(), $me);
        $this->syncVisit($me, $j, ['item' => [['kod_item' => 'FIZ-01', 'status_item' => 'teruk']]])->assertUnprocessable()->assertJsonValidationErrors('item.0.status_item');
        $id = $this->syncVisit($me, $j, ['item' => [['kod_item' => 'FIZ-01', 'status_item' => 'baik', 'catatan' => 'ok']]])->assertCreated()->json('data.id');
        $this->photo($me, $id)->assertCreated();
        $this->actingAs($me)->postJson('/api/v1/ptn/lapangan/lawatan/'.$id.'/hantar')->assertUnprocessable()->assertJsonValidationErrors('item');
        $this->actingAs($me)->putJson('/api/v1/ptn/lapangan/lawatan/'.$id, ['item' => $this->item(['GUN-01' => ['status_item' => 'kritikal', 'catatan' => 'Dijadikan bengkel']])])->assertOk();
        $this->actingAs($me)->postJson('/api/v1/ptn/lapangan/lawatan/'.$id.'/hantar')->assertOk();
        $this->assertSame('Dijadikan bengkel', DB::table('item_senarai_semak')->where('kod_item', 'GUN-01')->value('catatan'));
        // The default template covers the five mandatory categories.
        $cats = DB::table('item_senarai_semak')->distinct()->pluck('kategori_item')->sort()->values()->all();
        $this->assertSame(['akses_sempadan', 'keadaan_aset', 'keadaan_fizikal', 'penggunaan_semasa', 'tanda_pencerobohan'], $cats);
    }

    public function test_SRS_PTN_04_template_configuration_requires_the_five_categories_and_versions(): void
    {
        $admin = $this->admin();
        $items = [['kod' => 'A1', 'kategori' => 'keadaan_fizikal', 'label' => 'Fizikal']];
        $this->actingAs($admin)->postJson('/api/v1/ptn/konfigurasi/templat', ['kod' => 'PTN-X', 'nama' => 'X', 'jenis_pemeriksaan' => 'khas', 'senarai_item' => $items])->assertUnprocessable()->assertJsonValidationErrors('senarai_item');
        $items = array_map(fn ($k) => ['kod' => 'K-'.strtoupper($k), 'kategori' => $k, 'label' => $k], ['keadaan_fizikal', 'tanda_pencerobohan', 'penggunaan_semasa', 'keadaan_aset', 'akses_sempadan']);
        $id = $this->actingAs($admin)->postJson('/api/v1/ptn/konfigurasi/templat', ['kod' => 'PTN-X', 'nama' => 'X', 'jenis_pemeriksaan' => 'khas', 'senarai_item' => $items])->assertCreated()->json('data.id');
        $v2 = $this->actingAs($admin)->postJson('/api/v1/ptn/konfigurasi/templat/'.$id.'/versi', ['nama' => 'X v2', 'jenis_pemeriksaan' => 'khas', 'senarai_item' => [...$items, ['kod' => 'EXT', 'kategori' => 'keadaan_aset', 'label' => 'Tambahan']]])->assertCreated();
        $this->assertSame(2, $v2->json('data.versi'));
        $this->assertFalse((bool) DB::table('templat_senarai_semak')->where('id', $id)->value('status_aktif'));
        $this->actingAs($this->user(['ptn.konfigurasi.lihat']))->postJson('/api/v1/ptn/konfigurasi/templat', ['kod' => 'PTN-Y'])->assertForbidden();
    }

    public function test_SRS_PTN_07_photo_inside_boundary_accepted_outside_flagged_for_supervisor(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $j = $this->jadual($this->lot(), $me);
        $id = $this->syncVisit($me, $j)->assertCreated()->json('data.id');

        $in = $this->photo($me, $id)->assertCreated()->json('data');
        $this->assertSame('dalam', $in['lingkungan']);
        $this->assertFalse($in['perlu_semakan']);
        $this->assertEquals(0, $in['jarak_dari_lot_m']);

        // ~1.1 km north of the lot.
        $out = $this->photo($me, $id, [self::PUSAT_PETALING[0] + 0.01, self::PUSAT_PETALING[1]])->assertCreated()->json('data');
        $this->assertSame('luar', $out['lingkungan']);
        $this->assertTrue($out['perlu_semakan']);
        $this->assertGreaterThan(900, $out['jarak_dari_lot_m']);
        $this->assertEquals(90, $out['arah_kamera']);

        $flagged = $this->actingAs($sup)->getJson('/api/v1/ptn/lawatan?perlu_semakan=1')->assertOk()->json('data');
        $this->assertSame([$id], array_column($flagged, 'id'));
        // The officer cannot clear their own flag; the supervisor can.
        $this->actingAs($me)->postJson("/api/v1/ptn/lawatan/{$id}/gambar/{$out['id']}/semak", ['keputusan' => 'diterima'])->assertForbidden();
        $this->actingAs($sup)->postJson("/api/v1/ptn/lawatan/{$id}/gambar/{$out['id']}/semak", ['keputusan' => 'diterima', 'catatan' => 'Diambil dari jalan masuk'])->assertOk()->assertJsonPath('data.keputusan_semakan', 'diterima');
    }

    public function test_SRS_PTN_07_geotag_and_timestamp_cannot_be_changed_and_gallery_photos_are_marked(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $j = $this->jadual($this->lot(), $me);
        $id = $this->syncVisit($me, $j)->assertCreated()->json('data.id');
        $g = $this->photo($me, $id, null, ['sumber_gambar' => 'galeri'])->assertCreated()->json('data');
        $this->assertSame('galeri', $g['sumber_gambar']);

        $this->expectException(QueryException::class);
        DB::update('UPDATE gambar_lawatan SET koordinat_gambar = ST_SetSRID(ST_MakePoint(101, 3), 4326) WHERE id = ?', [$g['id']]);
    }

    public function test_SRS_PTN_06_photo_stored_in_dms_and_tagged_to_lot_and_gis_layer(): void
    {
        $me = $this->user(self::LAPANGAN, ['1008']);
        $sup = $this->user(self::PENYELIA, ['1008']);
        $lot = $this->lot();
        $j = $this->jadual($lot, $me);
        $id = $this->syncVisit($me, $j)->assertCreated()->json('data.id');
        $this->photo($me, $id, null, ['kod_item' => 'CER-01', 'kapsyen' => 'Sudut timur'])->assertCreated();

        $g = GambarLawatan::query()->firstOrFail();
        $doc = $this->documents->docs[$g->laluan_simpanan];
        $this->assertSame(['rekod_lawatan', $id, 'gambar_tapak'], [$doc['owner_type'], $doc['owner_id'], $doc['category']]);
        $this->assertSame($lot, $g->tag_rekod_tanah);
        $this->assertSame('ptn.pemantauan', $g->tag_lapisan_gis);
        $this->assertNotNull($g->imej_kecil);
        $url = $this->actingAs($sup)->getJson("/api/v1/ptn/lawatan/{$id}/gambar/{$g->id}/url")->assertOk()->json('data.url');
        $this->assertStringContainsString($g->laluan_simpanan, (string) $url);
    }

    public function test_SRS_PTN_05_cannot_sync_into_a_schedule_outside_district_or_without_permission(): void
    {
        $sepang = $this->user(self::LAPANGAN, ['1009']);
        $j = $this->jadual($this->lot(), $sepang);
        $this->syncVisit($sepang, $j)->assertUnprocessable()->assertJsonValidationErrors('id_jadual');
        $this->syncVisit($this->user(['ptn.lapangan.lihat']), $j)->assertForbidden();
        $this->actingAs($this->user(self::LAPANGAN))->postJson('/api/v1/ptn/lapangan/lawatan', ['id_luar_talian' => (string) Str::uuid(), 'lot_id' => $j->lot_id])->assertForbidden();
        $this->actingAs($sepang)->post('/api/v1/ptn/lapangan/lawatan/'.Str::uuid().'/gambar', ['fail' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertNotFound();
    }
}
