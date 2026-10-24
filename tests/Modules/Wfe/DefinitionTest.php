<?php

namespace Tests\Modules\Wfe;

use App\Models\User;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\DefinisiLog;
use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\ConditionEvaluator;
use App\Modules\Wfe\Services\DefinitionService;

class DefinitionTest extends WfeTestCase
{
    public function test_SRS_WFE_01_flow_stages_differ_by_financial_threshold(): void
    {
        $officer = $this->user();
        $finance = $this->user();
        $director = $this->user();
        $this->activeFlow($this->structure('ujian.ambang', [
            $this->stage('semakan', [$officer]),
            $this->stage('kewangan', [$finance]),
            $this->stage('pengarah', [$director]),
        ], [
            ['dari' => 'mula', 'ke' => 'semakan'],
            ['dari' => 'semakan', 'ke' => 'kewangan', 'keutamaan' => 1, 'syarat' => [['medan' => 'nilai', 'operator' => 'lebih', 'nilai' => 1000000]]],
            ['dari' => 'semakan', 'ke' => 'pengarah', 'keutamaan' => 2],
            ['dari' => 'kewangan', 'ke' => 'pengarah'],
            ['dari' => 'pengarah', 'ke' => 'tamat'],
        ]));

        $applicant = $this->user();
        $small = $this->engine()->start('ujian.ambang', 'rekod_ujian', 1, $applicant, ['nilai' => 5000]);
        $big = $this->engine()->start('ujian.ambang', 'rekod_ujian', 2, $applicant, ['nilai' => 2500000]);

        foreach ([$small, $big] as $id) {
            $task = Tugasan::where('id_instans', $id)->firstOrFail();
            $this->act($officer, $task->id, 'lulus')->assertOk();
        }

        $this->assertSame('pengarah', Instans::find($small)->peringkat->kod);
        $this->assertSame('kewangan', Instans::find($big)->peringkat->kod);
    }

    public function test_SRS_WFE_02_branch_on_district_and_land_category(): void
    {
        $hq = $this->user();
        $district = $this->user();
        $this->activeFlow($this->structure('ujian.cabang', [
            $this->stage('daerah', [$district]),
            $this->stage('ibu_pejabat', [$hq]),
        ], [
            ['dari' => 'mula', 'ke' => 'ibu_pejabat', 'keutamaan' => 1, 'logik' => 'mana_mana', 'syarat' => [
                ['medan' => 'kod_daerah', 'operator' => 'dalam', 'nilai' => ['10', '11']],
                ['medan' => 'kategori_tanah', 'operator' => 'sama', 'nilai' => 'wakaf'],
            ]],
            ['dari' => 'mula', 'ke' => 'daerah', 'keutamaan' => 2],
            ['dari' => 'daerah', 'ke' => 'tamat'],
            ['dari' => 'ibu_pejabat', 'ke' => 'tamat'],
        ]));

        $applicant = $this->user();
        $a = $this->engine()->start('ujian.cabang', 'rekod_ujian', 'a', $applicant, ['kod_daerah' => '11', 'kategori_tanah' => 'baitulmal']);
        $b = $this->engine()->start('ujian.cabang', 'rekod_ujian', 'b', $applicant, ['kod_daerah' => '03', 'kategori_tanah' => 'wakaf']);
        $c = $this->engine()->start('ujian.cabang', 'rekod_ujian', 'c', $applicant, ['kod_daerah' => '03', 'kategori_tanah' => 'baitulmal']);

        $this->assertSame('ibu_pejabat', Instans::find($a)->peringkat->kod);
        $this->assertSame('ibu_pejabat', Instans::find($b)->peringkat->kod);
        $this->assertSame('daerah', Instans::find($c)->peringkat->kod);
    }

    public function test_SRS_WFE_02_parallel_stage_all_any_and_quorum(): void
    {
        [$p1, $p2, $p3] = [$this->user(), $this->user(), $this->user()];
        $final = $this->user();
        foreach (['semua' => null, 'mana_mana' => null, 'kuorum' => 2] as $mode => $quorum) {
            $this->activeFlow($this->structure('ujian.selari_'.$mode, [
                $this->stage('jawatankuasa', [$p1, $p2, $p3], ['mod_kelulusan' => $mode, 'kuorum' => $quorum]),
                $this->stage('akhir', [$final]),
            ]));
        }
        $applicant = $this->user();

        // all: three tasks in parallel, stage completes only after all approve
        $id = $this->engine()->start('ujian.selari_semua', 'rekod_ujian', 'semua', $applicant);
        $tasks = Tugasan::where('id_instans', $id)->orderBy('id')->get();
        $this->assertCount(3, $tasks);
        $this->act($p1, $tasks[0]->id, 'lulus')->assertOk();
        $this->act($p2, $tasks[1]->id, 'lulus')->assertOk();
        $this->assertSame('jawatankuasa', Instans::find($id)->peringkat->kod);
        $this->act($p3, $tasks[2]->id, 'lulus')->assertOk();
        $this->assertSame('akhir', Instans::find($id)->peringkat->kod);

        // any: first approval completes the stage and cancels the rest
        $id = $this->engine()->start('ujian.selari_mana_mana', 'rekod_ujian', 'mana', $applicant);
        $tasks = Tugasan::where('id_instans', $id)->orderBy('id')->get();
        $this->act($p2, $tasks[1]->id, 'lulus')->assertOk();
        $this->assertSame('akhir', Instans::find($id)->peringkat->kod);
        $this->assertSame(Tugasan::DIBATALKAN, $tasks[0]->refresh()->status);

        // quorum 2 of 3: one rejection still leaves the quorum reachable
        $id = $this->engine()->start('ujian.selari_kuorum', 'rekod_ujian', 'kuorum', $applicant);
        $tasks = Tugasan::where('id_instans', $id)->orderBy('id')->get();
        $this->act($p1, $tasks[0]->id, 'tolak', 'Tidak setuju dengan nilai')->assertOk();
        $this->assertSame(Instans::BERJALAN, Instans::find($id)->status);
        $this->act($p2, $tasks[1]->id, 'lulus')->assertOk();
        $this->act($p3, $tasks[2]->id, 'lulus')->assertOk();
        $this->assertSame('akhir', Instans::find($id)->peringkat->kod);

        // all: a single rejection ends the instance
        $id = $this->engine()->start('ujian.selari_semua', 'rekod_ujian', 'semua-2', $applicant);
        $task = Tugasan::where('id_instans', $id)->orderBy('id')->firstOrFail();
        $this->act($p1, $task->id, 'tolak', 'Dokumen tidak lengkap')->assertOk();
        $this->assertSame(Instans::TOLAK, Instans::find($id)->status);
    }

    public function test_SRS_WFE_02_condition_model_is_data_only_and_safe(): void
    {
        $eval = new ConditionEvaluator;
        $ctx = ['nilai' => '1500.50', 'lot' => ['kod_daerah' => '10'], 'tag' => ['a', 'b'], 'nama' => 'Tanah Wakaf Klang'];

        $this->assertTrue($eval->matches('nilai', 'lebih', 1000, $ctx));
        $this->assertFalse($eval->matches('nilai', 'kurang_sama', '1000', $ctx));
        $this->assertTrue($eval->matches('lot.kod_daerah', 'dalam', '10, 11', $ctx));
        $this->assertTrue($eval->matches('tag', 'mengandungi', 'b', $ctx));
        $this->assertTrue($eval->matches('nama', 'mengandungi', 'wakaf', $ctx));
        $this->assertTrue($eval->matches('tiada_medan', 'tiada', null, $ctx));
        $this->assertFalse($eval->matches('tiada_medan', 'lebih', 0, $ctx));
        // An "expression" is just a string compared as data — nothing is executed.
        $this->assertFalse($eval->matches('nilai', 'sama', 'phpinfo()', $ctx));

        $this->expectException(\InvalidArgumentException::class);
        $eval->matches('nilai', 'eval', 1, $ctx);
    }

    public function test_SRS_WFE_02_invalid_graphs_are_rejected(): void
    {
        $admin = $this->admin();
        $approver = $this->user();
        $base = $this->structure('ujian.salah', [$this->stage('a', [$approver]), $this->stage('b', [$approver])]);

        // cycle a -> b -> a
        $this->actingAs($admin)->postJson('/api/v1/wfe/definisi', array_merge($base, ['peralihan' => [
            ['dari' => 'mula', 'ke' => 'a'], ['dari' => 'a', 'ke' => 'b'], ['dari' => 'b', 'ke' => 'a'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors('peralihan');

        // only conditional exits from "a" — no default path
        $this->actingAs($admin)->postJson('/api/v1/wfe/definisi', array_merge($base, ['peralihan' => [
            ['dari' => 'mula', 'ke' => 'a'],
            ['dari' => 'a', 'ke' => 'b', 'syarat' => [['medan' => 'nilai', 'operator' => 'lebih', 'nilai' => 1]]],
            ['dari' => 'b', 'ke' => 'tamat'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors('peralihan');

        // unknown operator (no eval of arbitrary expressions)
        $this->actingAs($admin)->postJson('/api/v1/wfe/definisi', array_merge($base, ['peralihan' => [
            ['dari' => 'mula', 'ke' => 'a', 'syarat' => [['medan' => 'nilai', 'operator' => 'system', 'nilai' => 'ls']]],
            ['dari' => 'mula', 'ke' => 'b'], ['dari' => 'a', 'ke' => 'tamat'], ['dari' => 'b', 'ke' => 'tamat'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors('peralihan.0.syarat.0.operator');
    }

    /** Acceptance criterion 1: new stage for transactions above a threshold, no code release; running cases keep v1. */
    public function test_SRS_WFE_03_admin_adds_threshold_stage_without_code_and_running_cases_keep_old_version(): void
    {
        $admin = $this->admin();
        $secondAdmin = $this->admin();
        $officer = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $director = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $applicant = $this->user();

        // v1 via the API: single stage
        $id = $this->actingAs($admin)->postJson('/api/v1/wfe/definisi', $this->structure('ujian.konfigurasi', [
            $this->stage('semakan', [$officer]),
        ]))->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/api/v1/wfe/definisi/{$id}/mohon-aktif")->assertOk();
        $this->actingAs($secondAdmin)->withMfa()->postJson("/api/v1/wfe/definisi/{$id}/lulus-aktif")->assertOk();

        $running = $this->engine()->start('ujian.konfigurasi', 'rekod_ujian', 'lama', $applicant, ['nilai' => 9000000]);

        // v2: add a director stage when nilai > 1,000,000
        $v2 = $this->actingAs($admin)->postJson("/api/v1/wfe/definisi/{$id}/versi-baharu")->assertCreated()->json('data.id');
        $structure = $this->actingAs($admin)->getJson("/api/v1/wfe/definisi/{$v2}")->assertOk()->json('data');
        $structure['peringkat'][] = $this->stage('pengarah', [$director]);
        $structure['peralihan'] = [
            ['dari' => 'mula', 'ke' => 'semakan'],
            ['dari' => 'semakan', 'ke' => 'pengarah', 'keutamaan' => 1, 'syarat' => [['medan' => 'nilai', 'operator' => 'lebih', 'nilai' => 1000000]]],
            ['dari' => 'semakan', 'ke' => 'tamat', 'keutamaan' => 2],
            ['dari' => 'pengarah', 'ke' => 'tamat'],
        ];
        $this->actingAs($admin)->putJson("/api/v1/wfe/definisi/{$v2}", $structure)->assertOk();
        $this->actingAs($admin)->postJson("/api/v1/wfe/definisi/{$v2}/mohon-aktif")->assertOk();
        $this->actingAs($secondAdmin)->withMfa()->postJson("/api/v1/wfe/definisi/{$v2}/lulus-aktif")->assertOk();

        $this->assertSame(Definisi::ARKIB, Definisi::find($id)->status);
        $new = $this->engine()->start('ujian.konfigurasi', 'rekod_ujian', 'baru', $applicant, ['nilai' => 9000000]);

        // new transaction passes through the new stage
        $task = Tugasan::where('id_instans', $new)->firstOrFail();
        $this->act($officer, $task->id, 'lulus')->assertOk();
        $this->assertSame('pengarah', Instans::find($new)->peringkat->kod);
        $this->assertSame(2, Instans::find($new)->no_versi_definisi);

        // the running case continues on version 1 and ends after its only stage
        $old = Tugasan::where('id_instans', $running)->firstOrFail();
        $this->act($officer, $old->id, 'lulus')->assertOk();
        $this->assertSame(1, Instans::find($running)->no_versi_definisi);
        $this->assertSame(Instans::LULUS, Instans::find($running)->status);
    }

    public function test_SRS_WFE_03_activation_needs_a_second_administrator(): void
    {
        $admin = $this->admin();
        $approver = $this->user();
        $id = $this->actingAs($admin)->postJson('/api/v1/wfe/definisi', $this->structure('ujian.dua_pentadbir', [$this->stage('a', [$approver])]))
            ->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/api/v1/wfe/definisi/{$id}/mohon-aktif")->assertOk();

        $this->actingAs($admin)->withMfa()->postJson("/api/v1/wfe/definisi/{$id}/lulus-aktif")
            ->assertForbidden()->assertJsonPath('code', 'PENGASINGAN_TUGAS');
        $this->assertSame(Definisi::MENUNGGU, Definisi::find($id)->status);
    }

    public function test_SRS_WFE_04_template_library_copy_as_new_flow(): void
    {
        $admin = $this->admin();
        $approver = $this->user();
        $template = app(DefinitionService::class)->create(
            $this->structure('templat.asas', [$this->stage('a', [$approver]), $this->stage('b', [$approver])], [], ['templat' => true]),
            $admin,
        );

        $this->actingAs($admin)->getJson('/api/v1/wfe/definisi?templat=1')->assertOk()
            ->assertJsonPath('data.0.kod_aliran', 'templat.asas');

        $copyId = $this->actingAs($admin)->postJson("/api/v1/wfe/definisi/{$template->id}/salin", ['kod_aliran' => 'pjk.lanjutan', 'nama' => 'Lanjutan pajakan'])
            ->assertCreated()->json('data.id');

        $copy = $this->actingAs($admin)->getJson("/api/v1/wfe/definisi/{$copyId}")->assertOk()->json('data');
        $this->assertSame('pjk.lanjutan', $copy['kod_aliran']);
        $this->assertSame(1, $copy['no_versi']);
        $this->assertSame('draf', $copy['status']);
        $this->assertSame(['a', 'b'], array_column($copy['peringkat'], 'kod'));
        $this->assertSame('salin', $copy['log'][0]['kaedah']);
    }

    public function test_SRS_WFE_05_approver_by_role_position_individual_and_group(): void
    {
        $roleHolder = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus'], ['pegawai_hq']);
        $individual = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $memberA = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $memberB = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $positionHolder = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $outsider = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $this->group('jawatankuasa', [$memberA, $memberB]);

        // Positions come from the ApproverDirectory (RBC will implement it).
        $this->app->instance(ApproverDirectory::class, new class($positionHolder) implements ApproverDirectory
        {
            public function __construct(private User $holder) {}

            public function usersWithRole(string $roleCode): array
            {
                return [];
            }

            public function usersWithPosition(string $positionCode): array
            {
                return $positionCode === 'ptd' ? [$this->holder] : [];
            }

            public function positionOf(User $user): ?string
            {
                return $user->id === $this->holder->id ? 'ptd' : null;
            }

            public function supervisorsOf(User $user): array
            {
                return [];
            }
        });

        $this->activeFlow($this->structure('ujian.pelulus', [
            $this->stage('peranan', [['jenis' => 'peranan', 'nilai' => 'pegawai_hq']]),
            $this->stage('individu', [$individual]),
            $this->stage('kumpulan', [['jenis' => 'kumpulan', 'nilai' => 'jawatankuasa']]),
            $this->stage('jawatan', [['jenis' => 'jawatan', 'nilai' => 'ptd']]),
        ]));
        $id = $this->engine()->start('ujian.pelulus', 'rekod_ujian', 1, $this->user());

        foreach ([[$roleHolder], [$individual], [$memberB], [$positionHolder]] as [$actor]) {
            $task = Tugasan::where('id_instans', $id)->where('status', 'menunggu')->firstOrFail();
            $this->act($outsider, $task->id, 'lulus')->assertForbidden();
            $this->actingAs($actor)->getJson('/api/v1/wfe/tugasan')->assertOk()->assertJsonPath('data.0.id', $task->id);
            $this->act($actor, $task->id, 'lulus')->assertOk();
        }

        $this->assertSame(Instans::LULUS, Instans::find($id)->status);
    }

    public function test_SRS_WFE_09_visual_editor_and_form_save_the_same_definition(): void
    {
        $admin = $this->admin();
        $approver = $this->user();
        $structure = $this->structure('ujian.editor', [$this->stage('a', [$approver])]);
        $id = $this->actingAs($admin)->postJson('/api/v1/wfe/definisi', $structure + ['kaedah' => 'borang'])->assertCreated()->json('data.id');

        // The React Flow editor sends the same structure plus node positions.
        $fromEditor = $this->actingAs($admin)->getJson("/api/v1/wfe/definisi/{$id}")->json('data');
        $fromEditor['peringkat'][] = $this->stage('b', [$approver]);
        $fromEditor['peralihan'] = [['dari' => 'mula', 'ke' => 'a'], ['dari' => 'a', 'ke' => 'b'], ['dari' => 'b', 'ke' => 'tamat']];
        $fromEditor['susun_atur'] = ['nodes' => ['a' => ['x' => 100, 'y' => 50], 'b' => ['x' => 300, 'y' => 50]]];
        $this->actingAs($admin)->putJson("/api/v1/wfe/definisi/{$id}", $fromEditor + ['kaedah' => 'editor_visual'])->assertOk();

        $saved = $this->actingAs($admin)->getJson("/api/v1/wfe/definisi/{$id}")->assertOk()->json('data');
        $this->assertSame(['a', 'b'], array_column($saved['peringkat'], 'kod'));
        $this->assertSame(300, $saved['susun_atur']['nodes']['b']['x']);
        $this->assertSame(['editor_visual', 'borang'], array_column($saved['log'], 'kaedah'));
    }

    public function test_SRS_WFE_12_versions_are_kept_logged_and_never_deleted(): void
    {
        $admin = $this->admin();
        $approver = $this->user();
        $id = $this->actingAs($admin)->postJson('/api/v1/wfe/definisi', $this->structure('ujian.versi', [$this->stage('a', [$approver])]) + ['catatan' => 'Versi pertama'])
            ->assertCreated()->json('data.id');

        $log = DefinisiLog::where('id_definisi', $id)->firstOrFail();
        $this->assertSame($admin->id, $log->id_pengguna);
        $this->assertSame('borang', $log->kaedah);
        $this->assertSame('Versi pertama', $log->arahan);

        $this->actingAs($admin)->deleteJson("/api/v1/wfe/definisi/{$id}")->assertStatus(405);

        // active versions are immutable: edits only through a new version
        Definisi::find($id)->forceFill(['status' => Definisi::AKTIF])->save();
        $this->actingAs($admin)->putJson("/api/v1/wfe/definisi/{$id}", $this->structure('ujian.versi', [$this->stage('a', [$approver])]))
            ->assertStatus(409);
    }

    public function test_SRS_WFE_12_moving_a_running_instance_to_a_new_version_needs_admin_approval(): void
    {
        $admin = $this->admin();
        $secondAdmin = $this->admin();
        $approver = $this->user(['wfe.tugasan.lihat', 'wfe.tugasan.lulus']);
        $v1 = $this->activeFlow($this->structure('ujian.pindah', [$this->stage('a', [$approver])]));
        $id = $this->engine()->start('ujian.pindah', 'rekod_ujian', 1, $this->user());

        $v2 = app(DefinitionService::class)->newVersion($v1, $admin);
        $s = app(DefinitionService::class)->export($v2);
        $s['peringkat'][0]['nama'] = 'Peringkat A (v2)';
        app(DefinitionService::class)->update($v2, $s, $admin);
        $v2->forceFill(['status' => Definisi::AKTIF])->save();
        $v1->forceFill(['status' => Definisi::ARKIB])->save();

        $requestId = $this->actingAs($admin)->postJson("/api/v1/wfe/instans/{$id}/pindah-versi", ['id_definisi' => $v2->id, 'sebab' => 'Pembetulan nama peringkat'])
            ->assertCreated()->json('data.id');
        $this->assertSame(1, Instans::find($id)->no_versi_definisi);

        // requester cannot approve their own request; approval needs MFA
        $this->actingAs($secondAdmin)->postJson("/api/v1/wfe/pindah-versi/{$requestId}/keputusan", ['lulus' => true])
            ->assertForbidden()->assertJsonPath('code', 'MFA_DIPERLUKAN');
        $this->actingAs($admin)->withMfa()->postJson("/api/v1/wfe/pindah-versi/{$requestId}/keputusan", ['lulus' => true])
            ->assertForbidden()->assertJsonPath('code', 'PENGASINGAN_TUGAS');
        $this->actingAs($secondAdmin)->withMfa()->postJson("/api/v1/wfe/pindah-versi/{$requestId}/keputusan", ['lulus' => true])->assertOk();

        $instance = Instans::find($id);
        $this->assertSame(2, $instance->no_versi_definisi);
        $this->assertSame('Peringkat A (v2)', $instance->peringkat->nama_peringkat);
        $this->assertSame(1, Tugasan::where('id_instans', $id)->where('status', 'menunggu')->count());
        $this->assertTrue($instance->sejarah()->where('peristiwa', 'pindah_versi')->exists());
    }

    public function test_SRS_WFE_03_definition_endpoints_require_permission(): void
    {
        $nobody = $this->user(['wfe.tugasan.lihat']);
        $viewer = $this->user(['wfe.definisi.lihat']);
        $approver = $this->user();
        $structure = $this->structure('ujian.kebenaran', [$this->stage('a', [$approver])]);

        $this->postJson('/api/v1/wfe/definisi', $structure)->assertUnauthorized();
        $this->actingAs($nobody)->getJson('/api/v1/wfe/definisi')->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/v1/wfe/definisi')->assertOk();
        $this->actingAs($viewer)->postJson('/api/v1/wfe/definisi', $structure)->assertForbidden();
    }
}
