<?php

namespace Tests\Modules\Hkm;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PerubahanHakMilik;
use App\Modules\Hkm\Models\SejarahStatusTanah;
use App\Modules\Hkm\Support\StatusTanah;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SRS-HKM-08/09/10/12/13/19/22 — every change to a title is a change request
 * approved in MOD-WFE and applied as a new version with before/after.
 */
class ChangeApprovalTest extends HkmTestCase
{
    private function request(HakMilik $title, string $kind, array $data, ?User $by = null): string
    {
        return $this->actingAs($by ?? $this->user())->postJson("/api/v1/hkm/hak-milik/{$title->id}/perubahan", [
            'jenis_perubahan' => $kind, 'data' => $data, 'justifikasi' => 'Berdasarkan carian rasmi PTD bertarikh 1 Okt 2026', 'no_rujukan_dokumen' => 'CR/2026/77',
        ])->assertCreated()->json('data.id');
    }

    public function test_SRS_HKM_19_change_starts_the_two_stage_flow_and_is_not_applied_before_approval(): void
    {
        $title = $this->title(['pemilik_berdaftar' => 'Pemilik Lama Sdn Bhd']);
        $user = $this->user();
        $id = $this->request($title, 'pemilik', ['pemilik_baharu' => 'Majlis Agama Islam Selangor', 'tarikh_pertukaran' => '2026-10-01', 'sebab_pertukaran' => 'Pindah milik (hibah)'], $user);

        $started = array_values($this->workflow->started);
        $this->assertCount(1, $started);
        $this->assertSame('hkm.perubahan_hak_milik', $started[0]['flow']);
        $this->assertSame('perubahan_hak_milik', $started[0]['subjectType']);
        $this->assertSame($id, $started[0]['subjectId']);
        $this->assertSame('1008', $started[0]['context']['kod_daerah']);

        $this->assertSame('Pemilik Lama Sdn Bhd', $title->refresh()->pemilik_berdaftar);
        $this->assertSame(1, $title->versi_rekod);

        // Only one pending change per title.
        $this->actingAs($user)->postJson("/api/v1/hkm/hak-milik/{$title->id}/perubahan", [
            'jenis_perubahan' => 'butiran', 'data' => ['syarat_nyata' => 'Baru'], 'justifikasi' => 'Pembetulan syarat',
        ])->assertUnprocessable();
    }

    public function test_SRS_HKM_19_approved_owner_change_is_applied_as_new_version_with_before_after(): void
    {
        $title = $this->title(['pemilik_berdaftar' => 'Pemilik Lama Sdn Bhd']);
        $approver = $this->user();
        $id = $this->request($title, 'pemilik', ['pemilik_baharu' => 'Majlis Agama Islam Selangor', 'tarikh_pertukaran' => '2026-10-01', 'sebab_pertukaran' => 'Pindah milik (hibah)']);

        $this->completeWorkflow($id, 'lulus', $approver->id);

        $title->refresh();
        $this->assertSame('Majlis Agama Islam Selangor', $title->pemilik_berdaftar);
        $this->assertSame(2, $title->versi_rekod);
        $this->assertSame('diluluskan', PerubahanHakMilik::query()->findOrFail($id)->status);

        $versions = $this->actingAs($this->user())->getJson("/api/v1/hkm/hak-milik/{$title->id}/sejarah")->json('data.versi');
        $this->assertSame(2, $versions[0]['versi']);
        $this->assertSame('Pemilik Lama Sdn Bhd', $versions[0]['data_sebelum']['pemilik_berdaftar']);
        $this->assertSame('Majlis Agama Islam Selangor', $versions[0]['data_selepas']['pemilik_berdaftar']);
        $this->assertContains('pemilik_berdaftar', $versions[0]['medan_berubah']);
        $this->assertSame('Berdasarkan carian rasmi PTD bertarikh 1 Okt 2026', $versions[0]['justifikasi']);
        $this->assertSame('CR/2026/77', $versions[0]['no_rujukan_dokumen']);
        $this->assertSame($approver->name, $versions[0]['pelulus']);

        $this->assertNotEmpty(array_filter($this->notifier->sent, fn ($n) => $n['type'] === 'hkm.perubahan_keputusan'));
    }

    public function test_SRS_HKM_19_rejected_or_withdrawn_change_is_never_applied(): void
    {
        $title = $this->title(['syarat_nyata' => 'Asal']);
        $id = $this->request($title, 'butiran', ['syarat_nyata' => 'Baharu']);
        $this->completeWorkflow($id, 'tolak');
        $this->assertSame('Asal', $title->refresh()->syarat_nyata);
        $this->assertSame('ditolak', PerubahanHakMilik::query()->findOrFail($id)->status);

        $id2 = $this->request($title, 'butiran', ['syarat_nyata' => 'Baharu']);
        $this->completeWorkflow($id2, 'ditarik_balik');
        $this->assertSame('Asal', $title->refresh()->syarat_nyata);

        // A late duplicate event is ignored (idempotent).
        $this->completeWorkflow($id, 'lulus');
        $this->assertSame('Asal', $title->refresh()->syarat_nyata);
    }

    public function test_SRS_HKM_19_missing_workflow_definition_is_reported(): void
    {
        $this->workflow->noFlow = true;
        $title = $this->title();
        $this->actingAs($this->user())->postJson("/api/v1/hkm/hak-milik/{$title->id}/perubahan", [
            'jenis_perubahan' => 'butiran', 'data' => ['syarat_nyata' => 'X'], 'justifikasi' => 'Pembetulan',
        ])->assertUnprocessable()->assertJsonValidationErrors('aliran');
        $this->assertSame(0, PerubahanHakMilik::query()->count());
    }

    public function test_SRS_HKM_19_change_request_needs_update_permission_and_scope(): void
    {
        $title = $this->title();
        $payload = ['jenis_perubahan' => 'butiran', 'data' => ['syarat_nyata' => 'X'], 'justifikasi' => 'Pembetulan'];
        $this->actingAs($this->user(['hkm.hak_milik.lihat']))->postJson("/api/v1/hkm/hak-milik/{$title->id}/perubahan", $payload)->assertForbidden();
        $this->actingAs($this->user(self::PENDAFTAR, ['1009']))->postJson("/api/v1/hkm/hak-milik/{$title->id}/perubahan", $payload)->assertNotFound();
    }

    /** SRS-HKM-08: the status list is configurable by MAIS (ADM parameter). */
    public function test_SRS_HKM_08_status_list_is_configurable(): void
    {
        $title = $this->title();
        $codes = collect($this->actingAs($this->user())->getJson('/api/v1/hkm/rujukan')->json('data.status_tanah'))->pluck('kod');
        foreach (['aktif', 'kosong', 'disewa', 'dipajak', 'dalam_penyelenggaraan', 'tindakan_undang_undang', 'proses_tukar_syarat', 'dilupuskan'] as $code) {
            $this->assertContains($code, $codes);
        }

        $this->actingAs($this->user())->postJson("/api/v1/hkm/hak-milik/{$title->id}/perubahan", [
            'jenis_perubahan' => 'status', 'data' => ['status_selepas' => 'dirampas', 'tarikh_berkuat_kuasa' => '2026-10-01'], 'justifikasi' => 'Ujian status',
        ])->assertUnprocessable();

        app(Parameters::class)->declare(StatusTanah::PARAMETER, 'json', [...StatusTanah::LALAI, ['kod' => 'dirampas', 'nama' => 'Dirampas']], 'x');
        $this->request($title, 'status', ['status_selepas' => 'dirampas', 'tarikh_berkuat_kuasa' => '2026-10-01']);
    }

    /** Acceptance criterion 4: one history row per change, with before/after/date/reason/officer, not editable. */
    public function test_SRS_HKM_09_status_change_writes_one_immutable_history_row(): void
    {
        $title = $this->title();
        $requester = $this->user();
        $approver = $this->user();
        $id = $this->request($title, 'status', ['status_selepas' => 'disewa', 'tarikh_berkuat_kuasa' => '2026-09-15'], $requester);
        $this->completeWorkflow($id, 'lulus', $approver->id);

        $this->assertSame('disewa', $title->refresh()->status_semasa);
        $rows = SejarahStatusTanah::query()->where('hak_milik_id', $title->id)->orderBy('id')->get();
        $this->assertCount(2, $rows); // registration + this change
        $row = $rows->last();
        $this->assertSame('aktif', $row->status_sebelum);
        $this->assertSame('disewa', $row->status_selepas);
        $this->assertSame('2026-09-15', $row->tarikh_berkuat_kuasa->format('Y-m-d'));
        $this->assertSame('Berdasarkan carian rasmi PTD bertarikh 1 Okt 2026', $row->sebab_perubahan);
        $this->assertSame($requester->id, $row->id_pegawai);
        $this->assertSame($approver->id, $row->id_pelulus);
        $this->assertSame('CR/2026/77', $row->no_rujukan_dokumen);

        // Not editable — by the model…
        $this->assertThrows(fn () => $row->update(['sebab_perubahan' => 'pinda']), \LogicException::class);
        // …nor directly in the database (trigger), even for the table owner.
        foreach (['UPDATE sejarah_status_tanah SET sebab_perubahan = ? WHERE id = ?' => ['pinda', $row->id], 'DELETE FROM sejarah_status_tanah WHERE id = ?' => [$row->id]] as $sql => $bindings) {
            try {
                DB::transaction(fn () => DB::statement($sql, $bindings));
                $this->fail('History row was modified: '.$sql);
            } catch (QueryException $e) {
                $this->assertStringContainsString('hanya-tambah', $e->getMessage());
            }
        }
        $this->assertSame('Berdasarkan carian rasmi PTD bertarikh 1 Okt 2026', $row->fresh()?->sebab_perubahan);
    }

    public function test_SRS_HKM_09_chronology_and_versions_are_append_only_too(): void
    {
        $title = $this->title();
        foreach (['kronologi_pemilikan', 'hak_milik_versi'] as $table) {
            try {
                DB::transaction(fn () => DB::table($table)->where('hak_milik_id', $title->id)->delete());
                $this->fail("{$table} rows were deleted");
            } catch (QueryException $e) {
                $this->assertStringContainsString('hanya-tambah', $e->getMessage());
            }
        }
    }

    /** SRS-HKM-10: ownership timeline of a lot from the earliest owner to MAIS. */
    public function test_SRS_HKM_10_lot_ownership_chronology_is_ordered(): void
    {
        $lotId = $this->lot();
        $title = $this->title(['pemilik_berdaftar' => 'Haji Ahmad bin Osman (sintetik)', 'tarikh_pendaftaran' => '1975-01-01'], $lotId);
        $id = $this->request($title, 'pemilik', ['pemilik_baharu' => 'Majlis Agama Islam Selangor', 'tarikh_pertukaran' => '2020-06-01', 'sebab_pertukaran' => 'Wakaf am']);
        $this->completeWorkflow($id, 'lulus');

        $timeline = $this->actingAs($this->user())->getJson("/api/v1/hkm/lot/{$lotId}/kronologi")->assertOk()->json('data');
        $this->assertCount(2, $timeline);
        $this->assertNull($timeline[0]['pemilik_sebelum']);
        $this->assertSame('Haji Ahmad bin Osman (sintetik)', $timeline[0]['pemilik_selepas']);
        $this->assertSame('Majlis Agama Islam Selangor', $timeline[1]['pemilik_selepas']);
        $this->assertSame('Wakaf am', $timeline[1]['sebab_pertukaran']);
    }

    /** SRS-HKM-12: heirs / co-owners with fractions, verification source, faraid flag. */
    public function test_SRS_HKM_12_heirs_with_fractions_and_unresolved_ownership_flag(): void
    {
        $title = $this->title();
        $add = fn (array $w) => $this->completeWorkflow($this->request($title, 'waris', ['tindakan' => 'tambah', ...$w]), 'lulus');

        $add(['nama' => 'Aminah binti Daud', 'no_pengenalan' => '800101-00-1234', 'hubungan' => 'waris', 'pecahan_bahagian_pembilang' => 1, 'pecahan_bahagian_penyebut' => 2, 'sumber_pengesahan' => 'e_faraid', 'status_pengesahan' => 'disahkan']);
        $flags = fn () => collect($this->actingAs($this->user())->getJson("/api/v1/hkm/hak-milik/{$title->id}")->json('data.bendera'))->pluck('kod');
        $this->assertContains('pemilikan_belum_selesai', $flags()); // 1/2 only — shares don't add up

        $add(['nama' => 'Hassan bin Daud', 'hubungan' => 'waris', 'pecahan_bahagian_pembilang' => 1, 'pecahan_bahagian_penyebut' => 2, 'sumber_pengesahan' => 'e_faraid', 'status_pengesahan' => 'disahkan']);
        $this->assertNotContains('pemilikan_belum_selesai', $flags());

        // Waiting for faraid needs the case number and flags the title.
        $this->actingAs($this->user())->postJson("/api/v1/hkm/hak-milik/{$title->id}/perubahan", [
            'jenis_perubahan' => 'waris', 'data' => ['tindakan' => 'tambah', 'nama' => 'Zainab', 'hubungan' => 'waris', 'status_pengesahan' => 'menunggu_faraid'], 'justifikasi' => 'Kes pusaka',
        ])->assertUnprocessable()->assertJsonValidationErrors('data.no_kes_faraid');
        $add(['nama' => 'Zainab binti Daud', 'hubungan' => 'pemilik_bersama', 'status_pengesahan' => 'menunggu_faraid', 'no_kes_faraid' => 'EF/2026/0012']);
        $this->assertContains('pemilikan_belum_selesai', $flags());
        $this->actingAs($this->user())->getJson('/api/v1/hkm/hak-milik?bendera=pemilikan_belum_selesai')->assertJsonPath('meta.total', 1);

        $heirs = $this->actingAs($this->user())->getJson("/api/v1/hkm/hak-milik/{$title->id}")->json('data.waris');
        $this->assertCount(3, $heirs);
        $this->assertSame('1/2', $heirs[0]['pecahan']);
        $this->assertSame('e_faraid', $heirs[0]['sumber_pengesahan']);
        $this->assertSame('******-**-1234', $heirs[0]['no_pengenalan']); // masked without hkm.sulit.lihat
        $this->assertSame('EF/2026/0012', $heirs[2]['no_kes_faraid']);

        $full = $this->actingAs($this->user(['hkm.hak_milik.lihat', 'hkm.sulit.lihat']))->getJson("/api/v1/hkm/hak-milik/{$title->id}")->json('data.waris.0.no_pengenalan');
        $this->assertSame('800101-00-1234', $full);
    }

    /** SRS-HKM-13: encumbrances recorded and flagged (also on the lot profile — see ProfileAndSearchTest). */
    public function test_SRS_HKM_13_encumbrance_is_recorded_flagged_and_discharged(): void
    {
        $title = $this->title();
        $this->completeWorkflow($this->request($title, 'sekatan', [
            'tindakan' => 'tambah', 'jenis_sekatan' => 'kaveat_persendirian', 'no_rujukan_sekatan' => 'KVT 123/2026', 'pihak_terlibat' => 'Syarikat X Sdn Bhd', 'tarikh_masuk' => '2026-08-01',
        ]), 'lulus');

        $data = $this->actingAs($this->user())->getJson("/api/v1/hkm/hak-milik/{$title->id}")->json('data');
        $this->assertSame('kaveat_persendirian', $data['sekatan'][0]['jenis_sekatan']);
        $this->assertSame('KVT 123/2026', $data['sekatan'][0]['no_rujukan_sekatan']);
        $this->assertSame('aktif', $data['sekatan'][0]['status_sekatan']);
        $this->assertContains('sekatan_aktif', collect($data['bendera'])->pluck('kod'));

        $this->completeWorkflow($this->request($title, 'sekatan', ['tindakan' => 'lupus', 'id_sekatan' => $data['sekatan'][0]['id'], 'tarikh_lupus' => '2026-10-10']), 'lulus');
        $data = $this->actingAs($this->user())->getJson("/api/v1/hkm/hak-milik/{$title->id}")->json('data');
        $this->assertSame('dilupuskan', $data['sekatan'][0]['status_sekatan']);
        $this->assertNotContains('sekatan_aktif', collect($data['bendera'])->pluck('kod'));

        // The version records the encumbrance list before/after.
        $versions = $this->actingAs($this->user())->getJson("/api/v1/hkm/hak-milik/{$title->id}/sejarah")->json('data.versi');
        $this->assertSame('aktif', $versions[0]['data_sebelum']['sekatan'][0]['status_sekatan']);
        $this->assertSame('dilupuskan', $versions[0]['data_selepas']['sekatan'][0]['status_sekatan']);
    }

    /** SRS-HKM-22: applied changes leave field-level audit with the justification as reason. */
    public function test_SRS_HKM_22_applied_change_is_audited_with_old_and_new_values(): void
    {
        $title = $this->title(['syarat_nyata' => 'Asal']);
        $this->completeWorkflow($this->request($title, 'butiran', ['syarat_nyata' => 'Baharu']), 'lulus');

        $this->assertAudited('kemas_kini', ['nama_entiti' => 'hak_milik', 'id_rekod' => $title->id, 'sebab_perubahan' => 'Berdasarkan carian rasmi PTD bertarikh 1 Okt 2026'])
            ->assertFieldChanged('syarat_nyata', 'Asal', 'Baharu');
    }
}
