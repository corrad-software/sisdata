<?php

namespace Tests\Modules\Pws;

use App\Modules\Pws\Models\Kandungan;
use App\Modules\Pws\Models\Permohonan;
use App\Modules\Pws\Models\StatistikTerbitan;
use App\Modules\Pws\Services\KandunganService;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Modules\Wfe\Models\Definisi;

/** SRS-PWS-04 (AI-assisted content, human approval), SRS-PWS-01 (official name), public statistics. */
class KandunganStatistikTest extends PwsTestCase
{
    public function test_SRS_PWS_04_ai_draft_uses_the_local_model_and_is_only_a_draft(): void
    {
        $writer = $this->pegawai(['pws.kandungan.lihat', 'pws.kandungan.cipta']);
        $res = $this->actingAs($writer)->postJson('/api/v1/pws/kandungan/jana-ai', ['jenis' => 'hebahan', 'arahan' => 'Tulis hebahan ringkas projek wakaf telaga untuk orang awam.'])->assertCreated();
        $k = Kandungan::query()->findOrFail($res->json('data.id'));
        $this->assertTrue($k->dijana_ai);
        $this->assertSame('draf', $k->status_terbitan);
        $this->assertSame('qwen2.5:1.5b', $k->model_ai);
        $this->assertSame('Hebahan Projek Wakaf Telaga', $k->tajuk_bm);
        $this->assertStringContainsString('Portal Hartanah Wakaf Selangor', $this->llm->calls[0]['system']);
        $this->assertStringContainsString('projek wakaf telaga', $this->llm->calls[0]['user']);
        $this->assertAudited('jana_ai', ['modul' => 'pws', 'id_rekod' => (string) $k->id]);

        // Not on the portal until approved.
        $this->assertSame(0, $this->awam('GET', 'kandungan', [], $this->token(['baca']))->json('meta.total'));

        $this->llm->up = false;
        $this->actingAs($writer)->postJson('/api/v1/pws/kandungan/jana-ai', ['jenis' => 'artikel', 'arahan' => 'Tulis artikel tentang wakaf tunai.'])->assertStatus(503);
        $this->actingAs($this->user(['pws.kandungan.lihat']))->postJson('/api/v1/pws/kandungan/jana-ai', ['jenis' => 'artikel', 'arahan' => 'Tulis artikel tentang wakaf tunai.'])->assertForbidden();
    }

    /** Kriteria: draf disemak dan diterbitkan secara manual oleh pegawai yang diberi kuasa. */
    public function test_SRS_PWS_04_publication_needs_WFE_approval_by_a_reviewer(): void
    {
        $writer = $this->pegawai(['pws.kandungan.lihat', 'pws.kandungan.cipta']);
        $id = $this->actingAs($writer)->postJson('/api/v1/pws/kandungan/jana-ai', ['jenis' => 'hebahan', 'arahan' => 'Tulis hebahan ringkas projek wakaf telaga.'])->json('data.id');

        // AI placeholders must be filled by the officer first.
        $this->actingAs($writer)->postJson("/api/v1/pws/kandungan/{$id}/hantar")->assertUnprocessable();
        $this->actingAs($writer)->putJson("/api/v1/pws/kandungan/{$id}", ['kandungan_bm' => 'Projek wakaf telaga di Kampung Contoh, Sepang.', 'tajuk_bi' => 'Waqf Well Project'])->assertOk();
        $this->actingAs($writer)->postJson("/api/v1/pws/kandungan/{$id}/hantar")->assertOk()->assertJsonPath('data.status', 'dalam_semakan');
        $wf = $this->workflow->last();
        $this->assertSame([KandunganService::ALIRAN_KANDUNGAN, KandunganService::SUBJEK_KANDUNGAN, (string) $id, $writer->id], [$wf['flow'], $wf['subjectType'], $wf['subjectId'], $wf['initiator']]);
        $this->actingAs($writer)->putJson("/api/v1/pws/kandungan/{$id}", ['kandungan_bm' => 'Ubah semasa semakan'])->assertUnprocessable();

        $reviewer = $this->user(['pws.kandungan.lihat']);
        event(new WorkflowCompleted('wf-1', KandunganService::ALIRAN_KANDUNGAN, KandunganService::SUBJEK_KANDUNGAN, (string) $id, 'lulus', $reviewer->id, 'Sesuai diterbitkan'));
        $k = Kandungan::query()->findOrFail($id);
        $this->assertSame(['diterbitkan', $reviewer->id], [$k->status_terbitan, $k->id_penyemak]);

        $token = $this->token(['baca']);
        $pub = $this->awam('GET', 'kandungan', ['jenis' => 'hebahan'], $token)->assertOk();
        $this->assertSame('Waqf Well Project', $pub->json('data.0.tajuk_bi'));
        $this->assertTrue($pub->json('data.0.dibantu_ai'));
        foreach (['prompt_ai', 'id_pengarang', 'id_penyemak', 'catatan_semakan', 'model_ai'] as $internal) {
            $this->assertArrayNotHasKey($internal, $pub->json('data.0'));
        }
        $this->awam('GET', 'kandungan/'.$k->slug, [], $token)->assertOk();

        // Withdrawal takes it off the portal at once.
        $this->actingAs($writer)->postJson("/api/v1/pws/kandungan/{$id}/tarik", ['sebab' => 'Maklumat lapuk'])->assertForbidden();
        $this->actingAs($this->pegawai())->postJson("/api/v1/pws/kandungan/{$id}/tarik", ['sebab' => 'Maklumat lapuk'])->assertOk();
        $this->awam('GET', 'kandungan/'.$k->slug, [], $token)->assertNotFound();
    }

    public function test_SRS_PWS_04_rejected_draft_returns_to_the_writer(): void
    {
        $writer = $this->pegawai(['pws.kandungan.lihat', 'pws.kandungan.cipta']);
        $id = $this->actingAs($writer)->postJson('/api/v1/pws/kandungan', ['jenis' => 'projek', 'tajuk_bm' => 'Projek Wakaf Klinik', 'kandungan_bm' => 'Pembinaan klinik wakaf.', 'kemajuan_peratus' => 40, 'lokasi' => 'Shah Alam'])
            ->assertCreated()->json('data.id');
        $this->actingAs($writer)->postJson("/api/v1/pws/kandungan/{$id}/hantar")->assertOk();
        event(new WorkflowCompleted('wf-1', KandunganService::ALIRAN_KANDUNGAN, KandunganService::SUBJEK_KANDUNGAN, (string) $id, 'tolak', $this->user()->id, 'Tambah gambar'));
        $this->assertSame('ditolak', Kandungan::query()->findOrFail($id)->status_terbitan);
        $this->actingAs($writer)->putJson("/api/v1/pws/kandungan/{$id}", ['kemajuan_peratus' => 45])->assertOk()->assertJsonPath('data.status', 'draf');
        $this->actingAs($this->user(['pjk.kontrak.lihat']))->getJson('/api/v1/pws/kandungan')->assertForbidden();
    }

    public function test_SRS_PWS_04_publication_flows_are_seeded_in_WFE(): void
    {
        $this->assertSame(2, Definisi::query()->whereIn('kod_aliran', [KandunganService::ALIRAN_KANDUNGAN, KandunganService::ALIRAN_STATISTIK])->where('status', Definisi::AKTIF)->count());
    }

    public function test_SRS_PWS_27_statistics_are_aggregate_suppressed_and_published_only_after_approval(): void
    {
        $token = $this->token();
        foreach (range(1, 4) as $i) {
            Permohonan::query()->create(['no_rujukan' => 'PHWS-2026-STAT00'.$i, 'jenis' => 'wakaf', 'status' => 'menunggu_semakan', 'status_penghantaran' => 'diterima']);
        }
        Permohonan::query()->create(['no_rujukan' => 'PHWS-2026-STAT009', 'jenis' => 'sewaan', 'status' => 'lulus', 'status_penghantaran' => 'diterima']);
        $this->premis();

        $officer = $this->pegawai(['pws.statistik.lihat', 'pws.statistik.cipta']);
        $preview = $this->actingAs($officer)->getJson('/api/v1/pws/statistik/pratonton?tempoh=2026')->assertOk()->json('data');
        $this->assertSame(4, $preview['permohonan']['wakaf']['jumlah']);
        $this->assertSame('<3', $preview['permohonan']['sewaan']['jumlah']);
        $this->assertSame(1, $preview['premis_ditawarkan']);

        $id = $this->actingAs($officer)->postJson('/api/v1/pws/statistik', ['tajuk' => 'Statistik Portal 2026', 'tempoh' => '2026'])->assertCreated()->json('data.id');
        $this->assertSame([], $this->awam('GET', 'statistik', [], $token)->json('data'));
        $this->actingAs($officer)->postJson("/api/v1/pws/statistik/{$id}/hantar")->assertOk()->assertJsonPath('data.status', 'dalam_semakan');
        $this->assertSame(KandunganService::ALIRAN_STATISTIK, $this->workflow->last()['flow']);
        event(new WorkflowCompleted('wf-1', KandunganService::ALIRAN_STATISTIK, KandunganService::SUBJEK_STATISTIK, (string) $id, 'lulus', $this->user()->id));
        $this->assertSame('diterbitkan', StatistikTerbitan::query()->findOrFail($id)->status);
        $pub = $this->awam('GET', 'statistik', [], $token)->assertOk();
        $this->assertSame('Statistik Portal 2026', $pub->json('data.0.tajuk'));
        $this->assertStringNotContainsString('PHWS-2026-STAT', $pub->getContent());
        $this->actingAs($this->user(['pws.statistik.lihat']))->postJson('/api/v1/pws/statistik', ['tajuk' => 'X', 'tempoh' => '2026'])->assertForbidden();
    }

    /** Kriteria penerimaan 1. */
    public function test_SRS_PWS_01_official_name_is_used_and_portal_wakaf_never_appears_as_a_display_name(): void
    {
        $root = dirname(__DIR__, 3);
        $files = [];
        foreach ([$root.'/pws', $root.'/app/Modules/Pws/lang'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (in_array($f->getExtension(), ['php', 'css', 'js', 'json', 'md', 'txt', 'html'], true)) {
                    $files[] = $f->getPathname();
                }
            }
        }
        $this->assertGreaterThan(10, count($files));
        $seen = false;
        foreach ($files as $file) {
            $text = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/Portal Wakaf(?! Selangor)/i', $text, $file.' menggunakan "Portal Wakaf" sebagai nama paparan.');
            $this->assertDoesNotMatchRegularExpression('/>\s*PWS\s*</', $text, $file.' memaparkan singkatan PWS.');
            $seen = $seen || str_contains($text, 'Portal Hartanah Wakaf Selangor');
        }
        $this->assertTrue($seen);
        $this->assertSame('Portal Hartanah Wakaf Selangor', config('pws.nama_rasmi'));
    }
}
