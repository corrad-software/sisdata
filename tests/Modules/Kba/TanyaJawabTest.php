<?php

namespace Tests\Modules\Kba;

use App\Modules\Kba\Contracts\KnowledgeBase;
use App\Modules\Kba\Models\LogPrompt;
use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\Penjawab;

class TanyaJawabTest extends KbaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->korpus();
    }

    public function test_SRS_KBA_03_plain_language_question_in_malay_gets_a_grounded_answer(): void
    {
        $r = $this->tanya($this->user(), 'Berapakah had saiz fail dokumen pejabat?');

        $this->assertTrue($r['cukup_sumber']);
        $this->assertSame('berjaya', $r['status']);
        $this->assertSame('ms', $r['bahasa']);
        $this->assertSame('ekstraktif', $r['mod']);
        $this->assertStringContainsString('25 MB', $r['jawapan']);
        $this->assertSame('Manual Pengguna Ujian', $r['sumber'][0]['dokumen']);
        $this->assertNotNull($r['id_perbualan']);
    }

    public function test_SRS_KBA_03_english_question_over_malay_sources_is_answered(): void
    {
        $r = $this->tanya($this->user(), 'What is the file size limit for office documents?');

        $this->assertTrue($r['cukup_sumber']);
        $this->assertSame('en', $r['bahasa']);
        $this->assertStringContainsString('extractive mode', $r['jawapan']);
        $this->assertStringContainsString('25 MB', $r['jawapan']);
    }

    public function test_SRS_KBA_04_citations_name_document_version_and_section_and_open_the_source_there(): void
    {
        $user = $this->user();
        $r = $this->tanya($user, 'Siapakah yang meluluskan permohonan pajakan?');
        $c = $r['sumber'][0];

        $this->assertSame('Manual Pengguna Ujian', $c['dokumen']);
        $this->assertSame('v1', $c['versi']);
        $this->assertSame('§3 Kelulusan Pajakan', $c['seksyen']);
        $this->assertSame(1, $c['no']);
        $this->assertStringContainsString('[1]', $r['jawapan']);
        $this->assertSame("/admin/kba/sumber/{$c['id_sumber']}?segmen={$c['id_segmen']}#segmen-{$c['id_segmen']}", $c['pautan']);

        $doc = $this->actingAs($user)->getJson("/api/v1/kba/sumber/{$c['id_sumber']}/kandungan?segmen={$c['id_segmen']}")->assertOk()->json('data');
        $this->assertSame('Manual Pengguna Ujian', $doc['tajuk']);
        $this->assertSame($c['id_segmen'], $doc['segmen_fokus']);
        $focus = collect($doc['segmen'])->firstWhere('id', $c['id_segmen']);
        $this->assertSame('§3 Kelulusan Pajakan', $focus['seksyen']);
        $this->assertStringContainsString('Pelulus', $focus['kandungan']);
        $this->assertCount(3, $doc['segmen'], 'the whole source opens, not just the passage');
    }

    public function test_SRS_KBA_05_out_of_scope_question_says_no_sufficient_sources_and_generates_nothing(): void
    {
        $this->params->values['kba.mod_jawapan'] = 'auto';
        $r = $this->tanya($this->user(), 'Siapakah pemenang Piala Dunia FIFA 2022?');

        $this->assertFalse($r['cukup_sumber']);
        $this->assertSame('tiada_sumber', $r['status']);
        $this->assertStringContainsString('Tiada sumber yang mencukupi', $r['jawapan']);
        $this->assertSame([], $r['sumber']);
        $this->assertSame([], $this->llm->calls, 'the model is never asked when sources are insufficient');

        $en = $this->tanya($this->user(), 'What is the capital city of France?');
        $this->assertFalse($en['cukup_sumber']);
        $this->assertStringContainsString('No sufficient sources', $en['jawapan']);
    }

    public function test_SRS_KBA_05_contract_answer_reports_insufficient_sources(): void
    {
        $kb = app(KnowledgeBase::class);
        $this->assertInstanceOf(Penjawab::class, $kb);

        $none = $kb->answer('Resipi nasi lemak terbaik di Selangor', $this->user());
        $this->assertFalse($none['cukup_sumber']);
        $this->assertSame([], $none['sumber']);

        $ok = $kb->answer('had saiz fail dokumen pejabat', $this->user());
        $this->assertTrue($ok['cukup_sumber']);
        $this->assertSame(['dokumen', 'versi', 'seksyen', 'petikan', 'pautan'], array_keys($ok['sumber'][0]));
        $this->assertSame('kontrak', LogPrompt::query()->latest('id')->value('saluran'));
    }

    public function test_SRS_KBA_07_contract_without_user_uses_only_awam_sources(): void
    {
        $kb = app(KnowledgeBase::class);

        $this->assertSame([], $kb->search('had saiz fail dokumen pejabat', null), 'Dalaman manual is not public');
        $this->assertFalse($kb->answer('Bilakah kunci penyulitan diputar?', null)['cukup_sumber']);

        $public = $kb->search('waktu operasi kaunter', null);
        $this->assertSame('Soalan Lazim Portal', $public[0]['dokumen']);
        $this->assertTrue($kb->answer('Pukul berapa kaunter dibuka?', null)['cukup_sumber']);
    }

    public function test_SRS_KBA_07_classified_passages_never_reach_the_model_for_unauthorised_roles(): void
    {
        $this->params->values['kba.mod_jawapan'] = 'auto';
        $soalan = 'Bilakah kunci penyulitan pangkalan data diputar?';

        $biasa = $this->tanya($this->user(), $soalan);
        $this->assertFalse($biasa['cukup_sumber']);
        foreach ($this->llm->calls as $call) {
            $this->assertStringNotContainsString('90 hari', $call['user']);
        }
        $log = LogPrompt::query()->findOrFail($biasa['id_log']);
        $sulitIds = \DB::table('kba_segmen')->where('klasifikasi', 'sulit')->pluck('id')->all();
        $this->assertSame([], array_intersect($log->senarai_id_segmen_dicapai, $sulitIds), 'access log: no Sulit segment in the context');

        $dibenarkan = $this->tanya($this->user([...self::PENGGUNA, 'kba.sulit.lihat']), $soalan);
        $this->assertTrue($dibenarkan['cukup_sumber']);
        $this->assertSame('generatif', $dibenarkan['mod']);
        $this->assertStringContainsString('90 hari', $this->llm->lastUser());
        $this->assertSame('sulit', $dibenarkan['sumber'][0]['klasifikasi']);
    }

    public function test_SRS_KBA_07_user_without_permission_or_session_is_refused(): void
    {
        $this->postJson('/api/v1/kba/tanya', ['soalan' => 'had saiz fail'])->assertUnauthorized();
        $this->actingAs($this->user(['dsh.dashboard.lihat']))->postJson('/api/v1/kba/tanya', ['soalan' => 'had saiz fail'])->assertForbidden();
        $this->actingAs($this->user(['dsh.dashboard.lihat']))->getJson('/api/v1/kba/carian?q=fail')->assertForbidden();
    }

    public function test_SRS_KBA_04_source_viewer_hides_segments_the_user_may_not_read(): void
    {
        $sop = SumberDokumen::query()->where('tajuk', 'SOP Pentadbir Keselamatan')->firstOrFail();

        $this->actingAs($this->user())->getJson("/api/v1/kba/sumber/{$sop->id}/kandungan")->assertNotFound();
        $this->actingAs($this->user([...self::PENGGUNA, 'kba.sulit.lihat']))->getJson("/api/v1/kba/sumber/{$sop->id}/kandungan")->assertOk();
        $this->assertAudited('lihat', ['modul' => 'kba', 'nama_entiti' => 'kba_sumber_dokumen', 'id_rekod' => $sop->id]);
    }

    public function test_SRS_KBA_03_conversation_history_is_per_user(): void
    {
        $user = $this->user();
        $first = $this->tanya($user, 'Berapakah had saiz fail dokumen pejabat?');
        $this->actingAs($user)->postJson('/api/v1/kba/tanya', ['soalan' => 'Bagaimana pengguna log masuk?', 'id_perbualan' => $first['id_perbualan']])->assertOk();

        $list = $this->actingAs($user)->getJson('/api/v1/kba/perbualan')->assertOk()->json('data');
        $this->assertCount(1, $list);
        $this->assertSame(2, $list[0]['log_count']);
        $thread = $this->actingAs($user)->getJson("/api/v1/kba/perbualan/{$first['id_perbualan']}")->assertOk()->json('data');
        $this->assertCount(2, $thread['mesej']);
        $this->assertStringContainsString('/admin/kba/sumber/', $thread['mesej'][0]['sumber'][0]['pautan']);

        $other = $this->user();
        $this->actingAs($other)->getJson("/api/v1/kba/perbualan/{$first['id_perbualan']}")->assertNotFound();
        $this->actingAs($other)->postJson('/api/v1/kba/tanya', ['soalan' => 'had saiz fail dokumen', 'id_perbualan' => $first['id_perbualan']])->assertOk();
        $this->assertCount(2, $this->actingAs($user)->getJson("/api/v1/kba/perbualan/{$first['id_perbualan']}")->json('data.mesej'), "another user's question never joins my thread");

        $this->actingAs($user)->deleteJson("/api/v1/kba/perbualan/{$first['id_perbualan']}")->assertOk();
        $this->assertCount(0, $this->actingAs($user)->getJson('/api/v1/kba/perbualan')->json('data'));
        $this->assertSame(3, LogPrompt::query()->count(), 'hiding a conversation keeps the prompt log');
    }

    public function test_SRS_KBA_10_keyword_search_with_filters(): void
    {
        $user = $this->user([...self::PENGGUNA, 'kba.sulit.lihat']);
        $hits = $this->actingAs($user)->getJson('/api/v1/kba/carian?q=pajakan')->assertOk()->json('data');
        $this->assertSame('§3 Kelulusan Pajakan', $hits[0]['seksyen']);
        $this->assertSame('pjk', $hits[0]['modul']);

        $this->assertCount(0, $this->actingAs($user)->getJson('/api/v1/kba/carian?q=pajakan&jenis_dokumen=SOP')->json('data'));
        $this->assertCount(1, $this->actingAs($user)->getJson('/api/v1/kba/carian?q=kunci+penyulitan&jenis_dokumen=SOP')->json('data'));
        $this->assertCount(1, $this->actingAs($user)->getJson('/api/v1/kba/carian?q=pajakan&modul=PJK')->json('data'));
        $this->assertCount(0, $this->actingAs($user)->getJson('/api/v1/kba/carian?q=pajakan&modul=hkm')->json('data'));
        $this->assertCount(0, $this->actingAs($this->user())->getJson('/api/v1/kba/carian?q=kunci+penyulitan')->json('data'), 'Sulit hidden from keyword search too');
    }
}
