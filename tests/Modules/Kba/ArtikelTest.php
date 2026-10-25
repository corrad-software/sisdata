<?php

namespace Tests\Modules\Kba;

use App\Modules\Kba\Contracts\ArticleDrafts;
use App\Modules\Kba\Models\Artikel;
use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\PengurusArtikel;
use App\Modules\Wfe\Events\WorkflowCompleted;

class ArtikelTest extends KbaTestCase
{
    private function draf(array $extra = []): array
    {
        return $this->actingAs($this->user(self::PENTADBIR))->postJson('/api/v1/kba/artikel', $extra + [
            'tajuk' => 'Cara set semula token MFA',
            'kandungan' => "## Langkah\n\nPentadbir aplikasi menetapkan semula token MFA melalui skrin Pengguna dan pengguna mendaftar semula aplikasi pengesah.",
            'modul' => 'RBC',
            'versi_sistem_dirujuk' => '1.2',
        ])->assertCreated()->json('data');
    }

    private function lulus(int $id, string $outcome = 'lulus', ?int $by = null): void
    {
        event(new WorkflowCompleted('wf-1', PengurusArtikel::ALIRAN, PengurusArtikel::SUBJEK, (string) $id, $outcome, $by, 'Catatan semakan'));
    }

    public function test_SRS_KBA_11_articles_are_versioned_with_status_owner_and_review_dates(): void
    {
        $a = $this->draf();
        $this->assertSame('draf', $a['status']);
        $this->assertSame(1, $a['versi']);
        $this->assertSame('rbc', $a['modul']);
        $this->assertNotNull($a['id_pemilik']);

        $editor = $this->user(self::PENTADBIR);
        $this->actingAs($editor)->putJson("/api/v1/kba/artikel/{$a['id']}", ['kandungan' => 'Kandungan baharu tentang token MFA.', 'sebab_perubahan' => 'Kemas kini langkah'])->assertOk()->assertJsonPath('data.versi', 2);
        $versi = $this->actingAs($editor)->getJson("/api/v1/kba/artikel/{$a['id']}/versi")->assertOk()->json('data');
        $this->assertSame([2, 1], array_column($versi, 'nombor_versi'));
        $this->assertSame('Kemas kini langkah', $versi[0]['sebab_perubahan']);

        $this->actingAs($editor)->postJson("/api/v1/kba/artikel/{$a['id']}/hantar")->assertOk();
        $this->lulus($a['id'], 'lulus', $editor->id);
        $art = Artikel::query()->findOrFail($a['id']);
        $this->assertSame('diterbitkan', $art->status);
        $this->assertSame($editor->id, $art->id_penyemak);
        $this->assertSame(now()->toDateString(), $art->tarikh_semakan_akhir?->toDateString());
        $this->assertSame(now()->addDays(180)->toDateString(), $art->tarikh_semakan_seterusnya?->toDateString());

        $this->actingAs($editor)->postJson("/api/v1/kba/artikel/{$a['id']}/lapuk")->assertOk();
        $this->assertSame('lapuk', $art->refresh()->status);
        $this->assertFalse($this->tanya($this->user(), 'set semula token MFA')['cukup_sumber'], 'obsolete articles leave the index');
        $this->actingAs($this->user())->putJson("/api/v1/kba/artikel/{$a['id']}", ['tajuk' => 'x'])->assertForbidden();
    }

    public function test_SRS_KBA_13_drafts_are_hidden_until_published_through_the_workflow(): void
    {
        $a = $this->draf();
        $reader = $this->user();

        $this->assertSame([], $this->actingAs($reader)->getJson('/api/v1/kba/artikel')->json('data'));
        $this->actingAs($reader)->getJson("/api/v1/kba/artikel/{$a['id']}")->assertNotFound();
        $this->assertFalse($this->tanya($reader, 'Bagaimana set semula token MFA?')['cukup_sumber'], 'drafts are never indexed');

        $editor = $this->user(self::PENTADBIR);
        $this->actingAs($editor)->postJson("/api/v1/kba/artikel/{$a['id']}/hantar")->assertOk()->assertJsonPath('data.status', 'dalam_semakan');
        $started = array_values($this->workflow->started)[0];
        $this->assertSame(['flow' => 'kba.terbit_artikel', 'subjectType' => 'kba_artikel', 'subjectId' => (string) $a['id']], array_intersect_key($started, array_flip(['flow', 'subjectType', 'subjectId'])));
        $this->actingAs($editor)->putJson("/api/v1/kba/artikel/{$a['id']}", ['tajuk' => 'x'])->assertUnprocessable();

        $this->lulus($a['id'], 'tolak');
        $this->assertSame('draf', Artikel::query()->findOrFail($a['id'])->status);
        $this->assertSame('Catatan semakan', Artikel::query()->findOrFail($a['id'])->catatan_semakan);

        $this->actingAs($editor)->postJson("/api/v1/kba/artikel/{$a['id']}/hantar")->assertOk();
        $this->lulus($a['id']);
        $this->assertCount(1, $this->actingAs($reader)->getJson('/api/v1/kba/artikel')->json('data'));
        $r = $this->tanya($reader, 'Bagaimana set semula token MFA?');
        $this->assertTrue($r['cukup_sumber']);
        $this->assertSame('Cara set semula token MFA', $r['sumber'][0]['dokumen']);

        // An edit after publication is a new draft; readers keep the published version until it is approved.
        $this->actingAs($editor)->putJson("/api/v1/kba/artikel/{$a['id']}", ['tajuk' => 'Tajuk draf baharu', 'kandungan' => 'Draf belum disemak.'])->assertOk();
        $seen = $this->actingAs($reader)->getJson("/api/v1/kba/artikel/{$a['id']}")->assertOk()->json('data');
        $this->assertSame('Cara set semula token MFA', $seen['tajuk']);
        $this->assertStringContainsString('aplikasi pengesah', $seen['kandungan']);
        $this->assertTrue($this->tanya($reader, 'Bagaimana set semula token MFA?')['cukup_sumber']);
    }

    public function test_SRS_KBA_13_submitting_without_a_configured_flow_is_refused_clearly(): void
    {
        $a = $this->draf();
        $this->workflow->missing = true;
        $this->actingAs($this->user(self::PENTADBIR))->postJson("/api/v1/kba/artikel/{$a['id']}/hantar")
            ->assertUnprocessable()->assertJsonPath('code', 'tidak_boleh_dihantar');
        $this->assertSame('draf', Artikel::query()->findOrFail($a['id'])->status);
    }

    public function test_SRS_KBA_14_ticket_solution_becomes_a_structured_draft_linked_both_ways(): void
    {
        $drafts = app(ArticleDrafts::class);
        $id = $drafts->fromTicket('TKT-2026-000123', [
            'tajuk' => 'Muat naik geran gagal (fail terlalu besar)', 'masalah' => 'Pengguna tidak dapat memuat naik geran imbasan.',
            'punca' => 'Saiz fail melebihi had 25 MB.', 'penyelesaian' => 'Mampatkan imbasan atau pecahkan kepada beberapa fail.', 'modul' => 'dms', 'versi_sistem' => '1.0',
        ], $this->user());
        $again = $drafts->fromTicket('TKT-2026-000123', [
            'tajuk' => 'Muat naik geran gagal', 'masalah' => 'Pengguna tidak dapat memuat naik geran imbasan.',
            'punca' => 'Saiz fail melebihi had 25 MB.', 'penyelesaian' => 'Mampatkan imbasan kepada PDF.',
        ]);
        $this->assertSame($id, $again, 'same ticket → same draft, new version');

        $a = Artikel::query()->findOrFail($id);
        $this->assertSame('tiket', $a->sumber_asal);
        $this->assertSame('TKT-2026-000123', $a->id_tiket_asal);
        $this->assertSame(2, $a->versi);
        $this->assertStringContainsString("## Masalah\n\n", $a->kandungan);
        $this->assertStringContainsString("## Punca\n\n", $a->kandungan);
        $this->assertStringContainsString("## Penyelesaian\n\nMampatkan imbasan kepada PDF.", $a->kandungan);
        $this->assertSame([['id' => $id, 'tajuk' => 'Muat naik geran gagal', 'status' => 'draf', 'pautan' => '/admin/kba/artikel/'.$id]], $drafts->forTicket('TKT-2026-000123'));

        $editor = $this->user(self::PENTADBIR);
        $view = $this->actingAs($editor)->getJson("/api/v1/kba/artikel/{$id}")->json('data');
        $this->assertSame('/admin/tkt/tiket/TKT-2026-000123', $view['pautan_tiket']);
        $this->assertCount(1, $this->actingAs($editor)->getJson('/api/v1/kba/artikel?tindakan=1')->json('data'), 'ticket drafts appear in the content admin action list');

        // Acceptance #7: visible to users only after publication.
        $this->assertSame([], $this->actingAs($this->user())->getJson('/api/v1/kba/artikel')->json('data'));
        $this->actingAs($editor)->postJson("/api/v1/kba/artikel/{$id}/hantar")->assertOk();
        $this->lulus($id);
        $this->assertSame('artikel_tiket', SumberDokumen::query()->where('kod', 'artikel:'.$id)->value('jenis_dokumen'));
        $this->assertTrue($this->tanya($this->user(), 'Kenapa muat naik geran imbasan gagal?')['cukup_sumber']);
    }

    public function test_SRS_KBA_12_new_release_flags_articles_on_older_versions_and_tells_owners(): void
    {
        $owner = $this->user(self::PENTADBIR);
        $old = $this->actingAs($owner)->postJson('/api/v1/kba/artikel', ['tajuk' => 'Panduan v1.2', 'kandungan' => 'Langkah lama.', 'versi_sistem_dirujuk' => '1.2'])->json('data');
        $new = $this->actingAs($owner)->postJson('/api/v1/kba/artikel', ['tajuk' => 'Panduan v2.0', 'kandungan' => 'Langkah baharu.', 'versi_sistem_dirujuk' => '2.0'])->json('data');
        foreach ([$old['id'], $new['id']] as $id) {
            $this->actingAs($owner)->postJson("/api/v1/kba/artikel/{$id}/hantar")->assertOk();
            $this->lulus($id);
        }

        $this->actingAs($this->user())->postJson('/api/v1/kba/keluaran', ['versi' => '2.0'])->assertForbidden();
        $this->actingAs($owner)->postJson('/api/v1/kba/keluaran', ['versi' => '2.0', 'catatan' => 'Keluaran Q4'])->assertCreated()->assertJsonPath('data.bilangan_artikel_ditanda', 1);
        $this->assertTrue(Artikel::query()->findOrFail($old['id'])->perlu_semakan);
        $this->assertFalse(Artikel::query()->findOrFail($new['id'])->perlu_semakan);

        $actions = $this->actingAs($owner)->getJson('/api/v1/kba/artikel?tindakan=1')->json('data');
        $this->assertSame([$old['id']], array_column($actions, 'id'));
        $this->assertStringContainsString('2.0', $actions[0]['sebab_perlu_semakan']);
        $sent = $this->notifier->of('kba.artikel_perlu_semakan');
        $this->assertSame([$owner->id], $sent[0]['recipients']);
        $this->assertSame(['bil' => 1, 'versi' => '2.0'], $sent[0]['data']);

        $this->artisan('kba:keluaran', ['versi' => '2.0'])->assertFailed();
        $this->artisan('kba:keluaran', ['versi' => '2.1'])->assertSuccessful();
    }
}
