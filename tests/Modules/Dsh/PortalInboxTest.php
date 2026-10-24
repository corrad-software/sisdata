<?php

namespace Tests\Modules\Dsh;

use App\Modules\Dsh\Models\DrafTransaksi;
use Illuminate\Support\Facades\DB;

class PortalInboxTest extends DshTestCase
{
    public function test_SRS_DSH_19_portal_lists_only_the_functions_the_user_may_use(): void
    {
        $links = collect($this->actingAs($this->user(['dsh.dashboard.lihat', 'pro.lot.lihat']))->getJson('/api/v1/dsh/portal')->assertOk()->json('data'))->pluck('href');
        $this->assertContains('/admin', $links);
        $this->assertContains('/admin/pro/lot', $links);
        $this->assertContains('/admin/dsh/bantuan', $links);
        $this->assertNotContains('/admin/hkm/hak-milik', $links);

        // A user without any dashboard permission still gets the portal (welcome page) — help and search only.
        $bare = collect($this->actingAs($this->user([]))->getJson('/api/v1/dsh/portal')->assertOk()->json('data'))->pluck('href');
        $this->assertEqualsCanonicalizing(['/admin/dsh/carian', '/admin/dsh/bantuan'], $bare->all());
    }

    public function test_SRS_DSH_22_drafts_are_saved_reopened_edited_and_discarded_by_their_owner_only(): void
    {
        $lot = $this->lot(['no_lot' => 'PT 77']);
        $before = DB::table('lot_tanah')->where('id', $lot)->first();
        $me = $this->user();
        $id = $this->actingAs($me)->postJson('/api/v1/dsh/draf', ['modul' => 'pro', 'jenis' => 'kemas_kini_lot', 'tajuk' => 'Kemas kini PT 77', 'pautan' => '/admin/pro/lot/'.$lot, 'muatan_json' => ['keluasan' => 99]])
            ->assertCreated()->json('data.id');

        $this->actingAs($me)->getJson("/api/v1/dsh/draf/{$id}")->assertOk()->assertJsonPath('data.muatan_json.keluasan', 99);
        $this->actingAs($me)->putJson("/api/v1/dsh/draf/{$id}", ['muatan_json' => ['keluasan' => 100]])->assertOk()->assertJsonPath('data.muatan_json.keluasan', 100);
        $this->actingAs($me)->getJson('/api/v1/dsh/draf')->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($before, DB::table('lot_tanah')->where('id', $lot)->first(), 'a draft never touches the master record');

        $other = $this->user();
        $this->actingAs($other)->getJson("/api/v1/dsh/draf/{$id}")->assertNotFound();
        $this->actingAs($other)->deleteJson("/api/v1/dsh/draf/{$id}")->assertNotFound();
        $this->actingAs($other)->getJson('/api/v1/dsh/draf')->assertJsonCount(0, 'data');

        $this->actingAs($me)->postJson('/api/v1/dsh/draf', ['modul' => 'pro', 'jenis' => 'x', 'tajuk' => 'x', 'pautan' => 'https://evil.example', 'muatan_json' => []])->assertStatus(422);
        $this->assertSame(1, $this->kpis($me)['dsh.draf_saya']['nilai']);

        $this->actingAs($me)->deleteJson("/api/v1/dsh/draf/{$id}")->assertOk();
        $this->assertSame(0, DrafTransaksi::query()->count());
    }

    public function test_SRS_DSH_23_inbox_shows_my_tasks_deadlines_sla_status_and_notifications(): void
    {
        $me = $this->user(self::EKSEKUTIF, role: 'pelulus');
        $late = $this->task('individu:'.$me->id, '1008', now()->subHours(2)->toDateTimeString());
        $soon = $this->task('peranan:pelulus', '1009', now()->addHours(5)->toDateTimeString());
        $this->task('individu:'.$me->id, '1008', null, 'selesai');
        $this->task('individu:999999');
        $group = DB::table('wf_kumpulan')->insertGetId(['kod' => 'jk_tanah', 'nama' => 'JK Tanah', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('wf_kumpulan_ahli')->insert(['id_kumpulan' => $group, 'id_pengguna' => $me->id]);
        $this->task('kumpulan:jk_tanah', '1008', now()->addDays(10)->toDateTimeString());

        $jenis = DB::table('ntf_jenis')->insertGetId(['kod' => 'ujian.dsh', 'nama' => 'Ujian', 'modul' => 'dsh', 'keutamaan' => 'tinggi', 'saluran_lalai' => json_encode(['dalam_sistem']), 'created_at' => now(), 'updated_at' => now()]);
        $mesej = DB::table('ntf_mesej')->insertGetId(['jenis_id' => $jenis, 'keutamaan' => 'tinggi', 'modul' => 'wfe', 'subjek' => 'Tugasan baharu', 'kandungan' => 'x', 'pautan' => '/admin/wfe/tugasan/'.$late, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ntf_status_pengguna')->insert(['mesej_id' => $mesej, 'pengguna_id' => $me->id, 'created_at' => now(), 'updated_at' => now()]);

        $inbox = $this->actingAs($me)->getJson('/api/v1/dsh/inbox')->assertOk()->json('data');
        $this->assertSame([$late, $soon], array_slice(array_column($inbox['tugasan'], 'id'), 0, 2), 'ordered by deadline');
        $this->assertCount(3, $inbox['tugasan']);
        $this->assertSame('lewat', $inbox['tugasan'][0]['status_sla']);
        $this->assertSame('hampir', $inbox['tugasan'][1]['status_sla']);
        $this->assertSame('/admin/wfe/tugasan/'.$late, $inbox['tugasan'][0]['pautan']);
        $this->assertSame(['tugasan' => 3, 'lewat' => 1, 'hampir' => 1, 'notifikasi' => 1], $inbox['ringkasan']);
        $this->assertSame('Tugasan baharu', $inbox['notifikasi'][0]['subjek']);

        $this->assertSame(1, $this->kpis($me)['ntf.belum_dibaca']['nilai']);
        $this->actingAs($this->user(['pro.lot.lihat']))->getJson('/api/v1/dsh/inbox')->assertForbidden();
    }

    public function test_SRS_DSH_24_global_search_includes_my_drafts_only(): void
    {
        $me = $this->user([...self::EKSEKUTIF]);
        DrafTransaksi::query()->create(['id_pengguna' => $me->id, 'modul' => 'per', 'jenis' => 'kes_baharu', 'tajuk' => 'Wakaf Masjid Kampung Jawa', 'muatan_json' => [], 'tarikh_simpan' => now()]);
        $other = $this->user();
        DrafTransaksi::query()->create(['id_pengguna' => $other->id, 'modul' => 'per', 'jenis' => 'kes_baharu', 'tajuk' => 'Wakaf Masjid Lain', 'muatan_json' => [], 'tarikh_simpan' => now()]);

        $groups = collect($this->actingAs($me)->getJson('/api/v1/adm/carian?q=Wakaf%20Masjid')->assertOk()->json('data'))->keyBy('entiti');
        $hits = $groups['dsh_draf_transaksi']['hasil'] ?? [];
        $this->assertCount(1, $hits);
        $this->assertSame('Wakaf Masjid Kampung Jawa', $hits[0]['title']);
    }

    public function test_SRS_DSH_25_online_help_has_guides_faq_and_manual_for_the_current_screen(): void
    {
        $this->params->values['dsh.url_manual'] = '/manual/sisdata.pdf';
        $help = $this->actingAs($this->user(['dsh.dashboard.lihat', 'pro.lot.lihat']))->getJson('/api/v1/dsh/bantuan?laluan=/admin/pro/lot/abc')->assertOk()->json('data');
        $this->assertSame('lot', $help['panduan'][0]['kod'], 'the guide for the current screen comes first');
        $this->assertTrue($help['panduan'][0]['berkaitan']);
        $this->assertNotEmpty($help['faq']);
        $this->assertSame('/manual/sisdata.pdf', $help['manual']);
        $this->assertNotContains('ai', array_column($help['panduan'], 'kod'), 'guides for functions the user lacks are hidden');

        // Accessible to every signed-in user, from every screen.
        $this->actingAs($this->user([]))->getJson('/api/v1/dsh/bantuan')->assertOk();
    }
}
