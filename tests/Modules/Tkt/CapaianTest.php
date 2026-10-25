<?php

namespace Tests\Modules\Tkt;

use App\Modules\Tkt\Models\Kronologi;
use App\Modules\Tkt\Models\Pasukan;
use App\Modules\Tkt\Models\PeraturanPenugasan;
use App\Modules\Tkt\Models\SlaKonfigurasi;
use App\Modules\Tkt\Models\Tiket;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CapaianTest extends TktTestCase
{
    public function test_SRS_TKT_20_reporter_sees_only_own_tickets(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $a = $this->tiket([], $alice);
        $b = $this->tiket(['tajuk' => 'Tiket Bob yang berbeza'], $bob);

        $this->assertSame([$a->id], array_column($this->actingAs($alice)->getJson('/api/v1/tkt/tiket')->assertOk()->json('data'), 'id'));
        $this->actingAs($alice)->getJson("/api/v1/tkt/tiket/{$b->id}")->assertNotFound();
        $this->actingAs($alice)->postJson("/api/v1/tkt/tiket/{$b->id}/balas", ['kandungan' => 'Cubaan'])->assertNotFound();
        $this->actingAs($alice)->postJson("/api/v1/tkt/tiket/{$a->id}/status", ['status' => 'disiasat'])->assertForbidden();
        $this->actingAs($this->user([]))->getJson('/api/v1/tkt/tiket')->assertForbidden();
    }

    public function test_SRS_TKT_20_support_sees_assigned_and_team_module_tickets_only(): void
    {
        $gisTeam = Pasukan::query()->create(['kod' => 'GIS_L2', 'nama' => 'Vendor GIS', 'aras' => 'L2', 'jenis' => 'vendor', 'modul' => ['gis'], 'aktif' => true]);
        PeraturanPenugasan::query()->create(['modul' => 'gis', 'id_pasukan' => $gisTeam->id, 'keutamaan' => 10, 'aktif' => true]);
        $gisTech = $this->user(self::JURUTEKNIK);
        $gisTeam->ahli()->create(['id_pengguna' => $gisTech->id, 'peranan' => 'juruteknik', 'aktif' => true]);
        $noTeam = $this->user(self::JURUTEKNIK);

        $gis = $this->tiket(['modul' => 'gis', 'tajuk' => 'Peta tidak dipaparkan']);
        $pjk = $this->tiket(['modul' => 'pjk']);

        $this->assertSame([$gis->id], array_column($this->actingAs($gisTech)->getJson('/api/v1/tkt/tiket')->assertOk()->json('data'), 'id'));
        $this->actingAs($gisTech)->getJson("/api/v1/tkt/tiket/{$pjk->id}")->assertNotFound();
        $this->assertSame([], $this->actingAs($noTeam)->getJson('/api/v1/tkt/tiket')->assertOk()->json('data'), 'support permission alone shows nothing');

        // Assigned directly → visible even outside the team.
        $pjk->forceFill(['id_juruteknik' => $noTeam->id])->save();
        $this->actingAs($noTeam)->getJson("/api/v1/tkt/tiket/{$pjk->id}")->assertOk()->assertJsonPath('data.peranan_saya.urus', true);

        // Support management sees everything.
        $this->assertCount(2, $this->actingAs($this->user(['tkt.semua.pantau']))->getJson('/api/v1/tkt/tiket')->assertOk()->json('data'));
    }

    public function test_SRS_TKT_20_classified_and_security_tickets_need_the_sulit_permission(): void
    {
        $tech = $this->ahli();
        $cleared = $this->ahli('juruteknik', 'L1_MAIS', [...self::JURUTEKNIK, 'tkt.sulit.lihat']);
        $reporter = $this->user();
        $t = $this->tiket(['isu_keselamatan' => true, 'tajuk' => 'Akaun dicerobohi'], $reporter);
        $this->assertSame('sulit', $t->klasifikasi);

        $this->actingAs($tech)->getJson("/api/v1/tkt/tiket/{$t->id}")->assertNotFound();
        $this->actingAs($cleared)->getJson("/api/v1/tkt/tiket/{$t->id}")->assertOk();
        $this->actingAs($reporter)->getJson("/api/v1/tkt/tiket/{$t->id}")->assertOk()->assertJsonPath('data.peranan_saya.sokongan', false);
        $this->assertAudited('lihat', ['modul' => 'tkt', 'nama_entiti' => 'tkt_tiket', 'id_rekod' => $t->id, 'id_pengguna' => $cleared->id]);
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$this->tiket()->id}/ubah", ['klasifikasi' => 'sulit', 'sebab' => 'Mengandungi data peribadi'])->assertForbidden();
    }

    public function test_SRS_TKT_18_lifecycle_is_in_the_tamper_evident_audit_trail(): void
    {
        $tech = $this->ahli();
        $reporter = $this->user();
        $this->actingAs($reporter)->postJson('/api/v1/tkt/tiket', ['tajuk' => 'Ralat simpan rekod', 'penerangan' => 'Butang simpan memberi ralat 500.', 'kategori' => 'ralat', 'keterukan' => 3])->assertCreated();
        $t = Tiket::query()->firstOrFail();
        $this->assertAudited('cipta', ['nama_entiti' => 'tkt_tiket', 'id_rekod' => $t->id, 'modul' => 'tkt', 'id_pengguna' => $reporter->id]);

        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/status", ['status' => 'disiasat'])->assertOk();
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'tkt_tiket', 'id_rekod' => $t->id, 'id_pengguna' => $tech->id])->assertFieldChanged('status', 'baharu', 'disiasat');
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/ubah", ['keterukan' => 2, 'sebab' => 'Ramai terjejas'])->assertOk();
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'tkt_tiket', 'id_rekod' => $t->id])->assertFieldChanged('keterukan', '3', '2');
        $other = $this->ahli();
        $this->actingAs($tech)->postJson("/api/v1/tkt/tiket/{$t->id}/tugaskan", ['id_pasukan' => $this->pasukan()->id, 'id_juruteknik' => $other->id, 'sebab' => 'Juruteknik bercuti'])->assertOk();
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'tkt_tiket', 'id_rekod' => $t->id])->assertFieldChanged('id_juruteknik', (string) $tech->id, (string) $other->id);
        $this->actingAs($reporter)->getJson("/api/v1/tkt/tiket/{$t->id}")->assertOk();
        $this->assertAudited('lihat', ['nama_entiti' => 'tkt_tiket', 'id_rekod' => $t->id, 'id_pengguna' => $reporter->id]);

        $row = DB::table('audit_rekod')->where('nama_entiti', 'tkt_tiket')->orderBy('no_urutan')->first();
        $this->assertNotNull($row->alamat_ip ?? null, 'IP recorded');
        $this->expectException(QueryException::class);
        DB::table('audit_rekod')->where('nama_entiti', 'tkt_tiket')->delete();
    }

    public function test_SRS_TKT_21_handover_moves_open_tickets_without_losing_history(): void
    {
        $vendorTech = $this->ahli('juruteknik', 'L2_VENDOR');
        $btm = Pasukan::query()->create(['kod' => 'BTM_L2', 'nama' => 'BTM MAIS (L2)', 'aras' => 'L2', 'jenis' => 'dalaman', 'aktif' => true]);
        $btmTech = $this->user(self::JURUTEKNIK);
        $btm->ahli()->create(['id_pengguna' => $btmTech->id, 'peranan' => 'juruteknik', 'aktif' => true]);
        PeraturanPenugasan::query()->create(['modul' => 'gis', 'id_pasukan' => $this->pasukan('L2_VENDOR')->id, 'keutamaan' => 5, 'aktif' => true]);
        $open = $this->tiket(['modul' => 'gis', 'tajuk' => 'Lapisan hilang']);
        $done = $this->selesai($this->tiket(['modul' => 'gis', 'tajuk' => 'Isu lama sudah selesai']), $vendorTech);
        $historyBefore = Kronologi::query()->count();

        $admin = $this->user(['tkt.konfigurasi.lihat', 'tkt.konfigurasi.kemas_kini', 'tkt.serahan.eksport']);
        $this->actingAs($admin)->postJson('/api/v1/tkt/konfigurasi/serahan/pindah', ['dari_pasukan' => $this->pasukan('L2_VENDOR')->id, 'ke_pasukan' => $btm->id, 'catatan' => 'Tamat tempoh jaminan', 'nyahaktif_sumber' => true])
            ->assertOk()->assertJsonPath('data.tiket', 2)->assertJsonPath('data.peraturan', 1);
        $open->refresh();
        $this->assertSame([$btm->id, $btmTech->id], [$open->id_pasukan, $open->id_juruteknik]);
        $this->assertGreaterThan($historyBefore, Kronologi::query()->count(), 'transfer recorded, nothing removed');
        $this->assertSame(4, SlaKonfigurasi::query()->count());
        $this->assertFalse($this->pasukan('L2_VENDOR')->aktif);

        $res = $this->actingAs($admin)->get('/api/v1/tkt/serahan/pakej')->assertOk();
        $this->assertStringStartsWith('PK', (string) $res->getContent());
        $this->actingAs($this->user(['tkt.konfigurasi.lihat']))->get('/api/v1/tkt/serahan/pakej')->assertForbidden();
    }
}
