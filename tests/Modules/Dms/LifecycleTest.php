<?php

namespace Tests\Modules\Dms;

use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenArkib;
use App\Modules\Dms\Models\DokumenKategori;
use App\Modules\Dms\Models\DokumenPelupusan;
use App\Modules\Dms\Services\Lifecycle\DisposalService;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Facades\Storage;
use Tests\Modules\Dms\Support\TestFiles;

class LifecycleTest extends DmsTestCase
{
    public function test_SRS_DMS_15_expiry_reminder_goes_to_the_process_owner_once_and_expired_documents_become_tamat(): void
    {
        $uploader = $this->userWith();
        $owner = $this->userWith();
        $this->params->set('dms.amaran_luput_hari', 14);

        $soon = $this->uploadDoc($uploader, TestFiles::pdf(), ['tarikh_luput' => now()->addDays(10)->toDateString(), 'pemilik_proses_id' => $owner->id]);
        $later = $this->uploadDoc($uploader, TestFiles::pdf(), ['tarikh_luput' => now()->addDays(60)->toDateString()]);
        $past = $this->uploadDoc($uploader, TestFiles::pdf(), ['tarikh_luput' => now()->subDay()->toDateString()]);

        $this->artisan('dms:semak-luput')->assertSuccessful();
        $this->artisan('dms:semak-luput')->assertSuccessful();

        $sent = $this->notifier->of('dms.luput_hampir');
        $this->assertCount(1, $sent);
        $this->assertSame([$owner->id], $sent[0]['recipients']);
        $this->assertSame(10, $sent[0]['data']['baki_hari']);
        $this->assertSame('/admin/dms/'.$soon->id, $sent[0]['link']);
        $this->assertSame('tamat', $past->refresh()->status);
        $this->assertSame('aktif', $later->refresh()->status);

        // Category-specific warning period.
        DokumenKategori::query()->where('kod', 'laporan')->update(['tempoh_amaran_luput_hari' => 90]);
        $this->artisan('dms:semak-luput');
        $this->assertCount(2, $this->notifier->of('dms.luput_hampir'));
    }

    public function test_SRS_DMS_21_retention_policy_is_configurable_per_category(): void
    {
        $admin = $this->userWith(['dms.dokumen.lihat', 'dms.konfigurasi.kemas_kini']);
        $kategori = DokumenKategori::query()->where('kod', 'resit')->firstOrFail();

        $this->actingAs($admin)->putJson("/api/v1/dms/kategori/{$kategori->id}", [
            'tempoh_retensi_bulan' => 24, 'tempoh_arkib_bulan' => 60, 'tindakan_selepas_arkib' => 'lupus',
        ])->assertOk()->assertJsonPath('data.tempoh_retensi_bulan', 24)->assertJsonPath('data.tindakan_selepas_arkib', 'lupus');

        $this->actingAs($admin)->putJson("/api/v1/dms/kategori/{$kategori->id}", ['tindakan_selepas_arkib' => 'bakar'])->assertJsonValidationErrors('tindakan_selepas_arkib');
        $this->actingAs($this->userWith())->putJson("/api/v1/dms/kategori/{$kategori->id}", ['tempoh_retensi_bulan' => 1])->assertForbidden();
    }

    public function test_SRS_DMS_22_documents_past_active_retention_are_archived_automatically_and_stay_searchable(): void
    {
        $user = $this->userWith();
        DokumenKategori::query()->where('kod', 'resit')->update(['tempoh_retensi_bulan' => 12, 'tempoh_arkib_bulan' => 24]);
        $old = $this->index($this->uploadDoc($user, TestFiles::pdf('Resit bayaran cukai pintu tahun lama sekali'), ['kategori' => 'resit', 'tarikh_dokumen' => now()->subMonths(13)->toDateString()]));
        $new = $this->uploadDoc($user, TestFiles::pdf(), ['kategori' => 'resit', 'tarikh_dokumen' => now()->subMonths(2)->toDateString()]);

        $this->artisan('dms:arkib-auto')->expectsOutputToContain('1')->assertSuccessful();

        $old->refresh();
        $this->assertSame('diarkib', $old->status);
        $this->assertSame('aktif', $new->refresh()->status);
        $this->assertStringStartsWith('arkib/', $old->versiSemasa->kunci_storan);
        $this->assertTrue(Storage::disk('dokumen')->exists($old->versiSemasa->kunci_storan));
        $archive = DokumenArkib::query()->where('dokumen_id', $old->id)->sole();
        $this->assertSame('retensi_auto', $archive->sebab);
        $this->assertSame(now()->addMonths(24)->toDateString(), $archive->tarikh_layak_lupus->toDateString());
        $this->assertCount(1, $this->audit->of('arkib', $old->id));

        // Acceptance 6: metadata + content still searchable, and the file still readable (audited).
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?q=pintu')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.status', 'diarkib');
        $url = $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$old->id}/pautan", ['jenis' => 'muat_turun'])->json('data.url');
        $this->actingAs($user)->get($url)->assertOk();
    }

    public function test_SRS_DMS_23_archived_documents_cannot_be_deleted_or_disposed_without_two_stage_approval(): void
    {
        $user = $this->userWith([...self::PEGAWAI, 'dms.dokumen.padam', 'dms.arkib.kemas_kini', 'dms.pelupusan.cipta']);
        $doc = $this->uploadDoc($user);

        $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/pelupusan", ['justifikasi' => 'Tamat tempoh simpanan'])->assertStatus(409);
        $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/arkib")->assertOk();

        // Acceptance 6: direct deletion of an archived document is refused.
        $this->actingAs($user)->deleteJson("/api/v1/dms/dokumen/{$doc->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'dua peringkat'));
        $this->assertNotNull(Dokumen::query()->find($doc->id));

        // Justification required.
        $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/pelupusan", ['justifikasi' => ''])->assertJsonValidationErrors('justifikasi');

        $request = $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/pelupusan", ['justifikasi' => 'Tamat tempoh arkib mengikut jadual pelupusan'])
            ->assertCreated()->json('data');
        $this->assertSame('dms.pelupusan', $this->workflow->started[$request['id_aliran_kerja']]['flow']);
        $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/pelupusan", ['justifikasi' => 'Permohonan kedua yang bertindih'])->assertStatus(409);

        // A rejected workflow leaves the document archived.
        event(new WorkflowCompleted($request['id_aliran_kerja'], 'dms.pelupusan', DisposalService::SUBJECT, (string) $request['id'], 'tolak', $this->userWith()->id, 'Masih diperlukan'));
        $this->assertSame('diarkib', $doc->refresh()->status);
        $this->assertSame('tolak', DokumenPelupusan::query()->find($request['id'])->status);
        $this->assertTrue(Storage::disk('dokumen')->exists($doc->versiSemasa->kunci_storan));
    }

    public function test_SRS_DMS_23_approved_disposal_destroys_content_keeps_metadata_and_audits_justification(): void
    {
        $user = $this->userWith([...self::PEGAWAI, 'dms.arkib.kemas_kini', 'dms.pelupusan.cipta']);
        $approver = $this->userWith();
        $doc = $this->uploadDoc($user);
        $this->actingAs($user)->post("/api/v1/dms/dokumen/{$doc->id}/versi", ['fail' => TestFiles::pdf('v2')], ['Accept' => 'application/json']);
        $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/arkib")->assertOk();
        $request = $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/pelupusan", ['justifikasi' => 'Tamat tempoh arkib mengikut jadual'])->json('data');

        // An event for a different instance id is ignored.
        event(new WorkflowCompleted('wf-palsu', 'dms.pelupusan', DisposalService::SUBJECT, (string) $request['id'], 'lulus', $approver->id));
        $this->assertSame('diarkib', $doc->refresh()->status);

        event(new WorkflowCompleted($request['id_aliran_kerja'], 'dms.pelupusan', DisposalService::SUBJECT, (string) $request['id'], 'lulus', $approver->id, 'Lulus peringkat 2'));

        $doc->refresh();
        $this->assertSame('dilupus', $doc->status);
        $this->assertNotNull($doc->tarikh_lupus);
        $this->assertSame([], Storage::disk('dokumen')->allFiles());
        $this->assertSame(0, $doc->versi()->whereNotNull('kunci_data_bersulit')->count());
        $this->assertSame($doc->no_rujukan, Dokumen::query()->find($doc->id)->no_rujukan); // tombstone

        $entry = collect($this->audit->of('lupus', $doc->id))->firstWhere('status', 'berjaya');
        $this->assertSame('Tamat tempoh arkib mengikut jadual', $entry['meta']['justifikasi']);
        $this->assertSame($approver->id, $entry['meta']['diluluskan_oleh']);
        $this->assertCount(1, $this->notifier->of('dms.pelupusan_keputusan'));

        $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/pautan", ['jenis' => 'muat_turun'])->assertStatus(410);
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen?status[]=dilupus')->assertJsonPath('meta.total', 1);
        $this->actingAs($user)->getJson('/api/v1/dms/dokumen')->assertJsonPath('meta.total', 0);
    }

    public function test_SRS_DMS_23_requester_cannot_be_the_approver(): void
    {
        $user = $this->userWith([...self::PEGAWAI, 'dms.arkib.kemas_kini', 'dms.pelupusan.cipta']);
        $doc = $this->uploadDoc($user);
        $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/arkib");
        $request = $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/pelupusan", ['justifikasi' => 'Tamat tempoh arkib mengikut jadual'])->json('data');

        event(new WorkflowCompleted($request['id_aliran_kerja'], 'dms.pelupusan', DisposalService::SUBJECT, (string) $request['id'], 'lulus', $user->id));

        $this->assertSame('diarkib', $doc->refresh()->status);
        $this->assertSame('tolak', DokumenPelupusan::query()->find($request['id'])->status);
    }

    public function test_SRS_DMS_23_disposal_without_workflow_engine_is_refused(): void
    {
        $user = $this->userWith([...self::PEGAWAI, 'dms.arkib.kemas_kini', 'dms.pelupusan.cipta']);
        $doc = $this->uploadDoc($user);
        $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/arkib");
        $this->app->offsetUnset(WorkflowEngine::class);

        $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/pelupusan", ['justifikasi' => 'Tamat tempoh arkib mengikut jadual'])->assertStatus(503);
        $this->assertSame(0, DokumenPelupusan::query()->count());
    }

    public function test_SRS_DMS_23_active_documents_are_only_logically_deleted(): void
    {
        $user = $this->userWith([...self::PEGAWAI, 'dms.dokumen.padam']);
        $doc = $this->uploadDoc($user);

        $this->actingAs($this->userWith())->deleteJson("/api/v1/dms/dokumen/{$doc->id}")->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/v1/dms/dokumen/{$doc->id}")->assertOk();

        $this->assertSoftDeleted('dokumen', ['id' => $doc->id]);
        $this->assertCount(1, Storage::disk('dokumen')->allFiles());
        $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$doc->id}")->assertNotFound();
    }
}
