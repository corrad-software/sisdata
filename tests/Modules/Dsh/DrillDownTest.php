<?php

namespace Tests\Modules\Dsh;

use Illuminate\Support\Facades\DB;

class DrillDownTest extends DshTestCase
{
    public function test_SRS_DSH_11_kpi_drills_to_record_list_then_to_the_profile_in_three_clicks(): void
    {
        $a = $this->lot(['kod_mukim' => '100803', 'no_lot' => 'PT 100']);
        $this->lot(['kod_mukim' => '100901', 'no_lot' => 'PT 200']);
        $user = $this->user();

        // Click 1: dashboard KPI → its pautan (the DSH drill list).
        $kpi = $this->kpis($user)['pro.bilangan_lot'];
        $this->assertTrue($kpi['drill']);
        $this->assertSame('/admin/dsh/senarai?kpi=pro.bilangan_lot', $kpi['pautan']);

        // Click 2: a slice (Petaling) → filtered list.
        $res = $this->actingAs($user)->getJson('/api/v1/dsh/kpi/pro.bilangan_lot/senarai?pecahan=daerah:1008')->assertOk();
        $res->assertJsonPath('meta.jumlah', 1)->assertJsonPath('data.0.sel.no_lot', 'PT 100')->assertJsonPath('data.0.sel.daerah', 'Petaling');

        // Click 3: the record's own page (Profil Hartanah 360°).
        $this->assertSame('/admin/pro/lot/'.$a, $res->json('data.0.pautan'));
        $this->assertAudited('lihat', ['modul' => 'dsh', 'nama_entiti' => 'kpi_senarai', 'id_rekod' => 'pro.bilangan_lot', 'id_pengguna' => $user->id]);
    }

    public function test_SRS_DSH_11_drill_down_respects_scope_and_permission(): void
    {
        $this->lot(['kod_mukim' => '100803']);
        $this->lot(['kod_mukim' => '100901']);
        $district = $this->user(self::EKSEKUTIF, ['1009']);
        $this->actingAs($district)->getJson('/api/v1/dsh/kpi/pro.bilangan_lot/senarai')->assertOk()->assertJsonPath('meta.jumlah', 1);
        $this->actingAs($district)->getJson('/api/v1/dsh/kpi/pro.bilangan_lot/senarai?pecahan=daerah:1008')->assertOk()->assertJsonPath('meta.jumlah', 0);

        // KPI the user may not see → 404 (no existence leak); KPI without drill-down → 404.
        $this->actingAs($this->user(['dsh.dashboard.lihat']))->getJson('/api/v1/dsh/kpi/pro.bilangan_lot/senarai')->assertNotFound();
        $this->actingAs($district)->getJson('/api/v1/dsh/kpi/ntf.belum_dibaca/senarai')->assertNotFound();
    }

    public function test_SRS_DSH_11_title_case_and_task_lists_link_to_their_records(): void
    {
        $lot = $this->lot();
        $title = $this->title($lot, ['tarikh_tamat_tempoh' => now()->addDays(30)->toDateString()]);
        $case = $this->acquisition();
        $user = $this->user();
        $task = $this->task('individu:'.$user->id);
        $instans = DB::table('wf_tugasan')->where('id', $task)->value('id_instans');

        $this->assertSame('/admin/hkm/hak-milik/'.$title, $this->actingAs($user)->getJson('/api/v1/dsh/kpi/hkm.hampir_tamat/senarai')->json('data.0.pautan'));
        $this->assertSame('/admin/per/kes/'.$case, $this->actingAs($user)->getJson('/api/v1/dsh/kpi/per.dalam_proses/senarai')->json('data.0.pautan'));
        $this->assertSame('/admin/wfe/instans/'.$instans, $this->actingAs($user)->getJson('/api/v1/dsh/kpi/wfe.tugasan_tertunggak/senarai?pecahan=modul:per')->json('data.0.pautan'));
        $this->assertSame('/admin/wfe/tugasan/'.$task, $this->actingAs($user)->getJson('/api/v1/dsh/kpi/wfe.tugasan_saya/senarai')->json('data.0.pautan'));
    }
}
