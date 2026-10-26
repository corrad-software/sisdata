<?php

namespace Tests\Modules\Kba;

use App\Modules\Kba\Models\LogPrompt;
use App\Modules\Kba\Models\MaklumBalas;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuditAnalitikTest extends KbaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->korpus();
    }

    public function test_SRS_KBA_16_every_prompt_is_logged_with_user_role_ip_model_segments_and_sources(): void
    {
        $user = $this->user(self::PENGGUNA, 'pegawai_operasi');
        $r = $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->postJson('/api/v1/kba/tanya', ['soalan' => 'Berapakah had saiz fail dokumen pejabat?'])->assertOk()->json('data');

        $log = LogPrompt::query()->findOrFail($r['id_log']);
        $this->assertSame($user->id, $log->id_pengguna);
        $this->assertContains('pegawai_operasi', $log->peranan);
        $this->assertSame('10.1.2.3', $log->alamat_ip);
        $this->assertSame('Berapakah had saiz fail dokumen pejabat?', $log->prompt);
        $this->assertSame($r['jawapan'], $log->jawapan);
        $this->assertSame('cincangan-v1', $log->model_embedding);
        $this->assertSame('ekstraktif', $log->mod_jawapan);
        $this->assertNotEmpty($log->senarai_id_segmen_dicapai);
        $this->assertSame('Manual Pengguna Ujian', $log->senarai_sumber_dipetik[0]['dokumen']);
        $this->assertSame('berjaya', $log->status);
        $this->assertTrue($log->cap_masa->isToday());

        $audit = $this->assertAudited('prompt_ai', ['modul' => 'kba', 'nama_entiti' => 'kba_log_prompt', 'id_rekod' => $log->id, 'id_pengguna' => $user->id]);
        $this->assertNotNull($audit->row->cincangan_rekod ?? null, 'MOD-AUD hash chain (tamper-evident)');

        $none = $this->tanya($user, 'Siapakah pemenang Piala Dunia 2022?');
        $this->assertSame('tiada_sumber', LogPrompt::query()->findOrFail($none['id_log'])->status);
        $this->assertAuditCount(2, 'prompt_ai', ['modul' => 'kba', 'id_pengguna' => $user->id]);
    }

    public function test_SRS_KBA_16_prompt_log_is_immutable_and_only_auditors_may_review_it(): void
    {
        $r = $this->tanya($this->user(), 'had saiz fail dokumen pejabat');
        $log = LogPrompt::query()->findOrFail($r['id_log']);

        try {
            $log->update(['jawapan' => 'dipinda']);
            $this->fail('update must be refused');
        } catch (LogicException) {
        }
        try {
            $log->delete();
            $this->fail('delete must be refused');
        } catch (LogicException) {
        }
        $this->assertSame($r['jawapan'], LogPrompt::query()->findOrFail($log->id)->jawapan);

        $this->actingAs($this->user(self::PENTADBIR))->getJson('/api/v1/kba/log-prompt')->assertForbidden();
        $auditor = $this->user(['kba.log_prompt.audit']);
        $list = $this->actingAs($auditor)->getJson('/api/v1/kba/log-prompt?status=berjaya')->assertOk()->json();
        $this->assertSame(1, $list['meta']['jumlah']);
        $detail = $this->actingAs($auditor)->getJson("/api/v1/kba/log-prompt/{$log->id}")->assertOk()->json('data');
        $this->assertNotEmpty($detail['segmen_dicapai']);
        $this->assertAudited('lihat', ['modul' => 'kba', 'nama_entiti' => 'kba_log_prompt', 'id_rekod' => $log->id, 'id_pengguna' => $auditor->id]);
    }

    public function test_SRS_KBA_17_feedback_on_own_answers_reaches_content_admins(): void
    {
        $admin = $this->user(self::PENTADBIR);
        $user = $this->user();
        $r = $this->tanya($user, 'had saiz fail dokumen pejabat');

        $this->actingAs($this->user())->postJson("/api/v1/kba/log/{$r['id_log']}/maklum-balas", ['penilaian' => 'membantu'])->assertNotFound();
        $this->actingAs($user)->postJson("/api/v1/kba/log/{$r['id_log']}/maklum-balas", ['penilaian' => 'sumber_salah', 'ulasan' => 'Rujuk SOP terkini'])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/kba/log/{$r['id_log']}/maklum-balas", ['penilaian' => 'salah'])->assertUnprocessable();

        $sent = $this->notifier->of('kba.maklum_balas_negatif');
        $this->assertCount(1, $sent);
        $this->assertContains($admin->id, $sent[0]['recipients']);
        $this->assertSame('/admin/kba/maklum-balas', $sent[0]['link']);

        $queue = $this->actingAs($admin)->getJson('/api/v1/kba/maklum-balas')->assertOk()->json('data');
        $this->assertSame('sumber_salah', $queue[0]['penilaian']);
        $this->assertSame('Rujuk SOP terkini', $queue[0]['ulasan']);
        $this->actingAs($admin)->putJson("/api/v1/kba/maklum-balas/{$queue[0]['id']}", ['status_tindakan' => 'selesai', 'catatan_tindakan' => 'SOP dikemas kini'])->assertOk();
        $this->assertSame('selesai', MaklumBalas::query()->findOrFail($queue[0]['id'])->status_tindakan);
        $this->actingAs($user)->getJson('/api/v1/kba/maklum-balas')->assertForbidden();

        $this->assertSame('sumber_salah', $this->actingAs($user)->getJson("/api/v1/kba/perbualan/{$r['id_perbualan']}")->json('data.mesej.0.maklum_balas'));
    }

    public function test_SRS_KBA_18_analytics_frequent_questions_gaps_citations_usage_and_weekly_digest(): void
    {
        // The four questions below must share one timestamp: otherwise "most recent first" decides between
        // the two equally frequent questions and the result depends on how fast the machine runs.
        $this->freezeTime();
        $admin = $this->user(self::PENTADBIR);
        $user = $this->user(self::PENGGUNA, 'pegawai_operasi');
        $this->tanya($user, 'Berapakah had saiz fail dokumen pejabat?');
        $this->tanya($user, 'berapakah HAD saiz fail dokumen pejabat');
        $this->tanya($user, 'Siapakah pemenang Piala Dunia 2022?');
        $this->tanya($user, 'Siapakah pemenang piala dunia 2022');

        $a = $this->actingAs($admin)->getJson('/api/v1/kba/analitik')->assertOk()->json('data');
        $this->assertSame('berapakah had saiz fail dokumen pejabat', $a['soalan_kerap'][0]['soalan']);
        $this->assertSame(2, (int) $a['soalan_kerap'][0]['bilangan']);
        $this->assertSame('siapakah pemenang piala dunia 2022', $a['jurang'][0]['soalan']);
        $this->assertSame(2, $a['jurang'][0]['tiada_sumber']);
        $this->assertSame('Manual Pengguna Ujian', $a['sumber_paling_dirujuk'][0]['tajuk']);
        $this->assertContains('Soalan Lazim Portal', array_column($a['sumber_tidak_dirujuk'], 'tajuk'));
        $this->assertSame(4, (int) $a['infrastruktur']['ringkasan']['prompt']);
        $this->assertContains('pegawai_operasi', array_column($a['mengikut_peranan'], 'peranan'));
        $this->assertNotEmpty($a['mengikut_modul']);

        $this->actingAs($admin)->getJson('/api/v1/kba/laporan/jurang')->assertOk()->assertJsonPath('data.0.bilangan', 2);
        $this->actingAs($this->user())->getJson('/api/v1/kba/analitik')->assertForbidden();

        $this->artisan('kba:analitik')->assertSuccessful();
        $this->assertSame(2, (int) DB::table('kba_analitik')->where('soalan_dinormalkan', 'berapakah had saiz fail dokumen pejabat')->value('bilangan_ditanya'));

        $this->artisan('kba:ringkasan-jurang')->assertSuccessful();
        $digest = $this->notifier->of('kba.ringkasan_jurang');
        $this->assertCount(1, $digest);
        $this->assertContains($admin->id, $digest[0]['recipients']);
        $this->assertSame(1, $digest[0]['data']['bil_jurang']);
        $this->assertStringContainsString('piala dunia', (string) $digest[0]['data']['contoh']);
    }
}
