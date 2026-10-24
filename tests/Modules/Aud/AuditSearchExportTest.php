<?php

namespace Tests\Modules\Aud;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/** AUD-c: search, chronology, export packages, audit-on-audit, access, reports (SRS-AUD-10..15, 23). */
class AuditSearchExportTest extends AudTestCase
{
    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['email' => 'alice@mais.gov.my']);
        $this->bob = User::factory()->create(['email' => 'bob@mais.gov.my']);
        $this->access->grant($this->alice, [], ['pegawai_hartanah']);
        $this->access->grant($this->bob, [], ['pegawai_kewangan']);

        $logger = app(AuditLogger::class);
        $at = fn (string $local, User $user, callable $fn) => $this->at($local, $user, $fn);

        $at('2026-03-02 09:00', $this->alice, fn () => $logger->record('kemas_kini', 'pro', 'lot_tanah', 1, changes: ['keluasan' => ['1.0', '1.5']]));
        $at('2026-03-03 10:00', $this->alice, fn () => $logger->record('lulus', 'wfe', 'permohonan', 9, meta: ['induk_entiti' => 'lot_tanah', 'induk_id' => '1']));
        $at('2026-03-04 11:00', $this->bob, fn () => $logger->record('kemas_kini', 'pro', 'lot_tanah', 2));
        $at('2026-03-05 12:00', $this->alice, fn () => $logger->record('muat_naik', 'dms', 'dokumen', 'D1', meta: ['induk_entiti' => 'lot_tanah', 'induk_id' => '1']));
        $at('2026-04-10 09:30', $this->alice, fn () => $logger->record('kemas_kini', 'pro', 'lot_tanah', 1, changes: ['mukim' => ['Klang', 'Kapar']]));
        // Saturday 23:00 — off hours; Rahsia record.
        $at('2026-04-11 23:00', $this->bob, fn () => $logger->record('lihat', 'kes', 'kes_mahkamah', 4, meta: ['klasifikasi' => 'rahsia']));
        Carbon::setTestNow();
    }

    private function at(string $local, User $user, callable $fn): void
    {
        Carbon::setTestNow(Carbon::parse($local, 'Asia/Kuala_Lumpur'));
        $this->actingAs($user);
        $fn();
        auth()->forgetGuards();
    }

    /** Kriteria penerimaan 4 (search part). */
    public function test_SRS_AUD_10_combined_user_module_action_and_period_filters(): void
    {
        $auditor = $this->auditor();

        $this->actingAs($auditor)->getJson('/api/v1/aud/rekod?'.http_build_query([
            'id_pengguna' => $this->alice->id, 'modul' => 'pro', 'jenis_tindakan' => 'kemas_kini',
            'dari' => '2026-03-01', 'hingga' => '2026-03-31',
        ]))->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id_rekod', '1')
            ->assertJsonPath('data.0.perubahan.0.nama_medan', 'keluasan')
            ->assertJsonPath('data.0.peranan_semasa', ['pegawai_hartanah']);

        $this->actingAs($auditor)->getJson('/api/v1/aud/rekod?peranan=pegawai_kewangan')->assertOk()->assertJsonPath('meta.total', 2);
        $this->actingAs($auditor)->getJson('/api/v1/aud/rekod?modul=pro&id_rekod=1&nama_entiti=lot_tanah')->assertOk()->assertJsonPath('meta.total', 2);
        $this->actingAs($auditor)->getJson('/api/v1/aud/rekod?status_transaksi=gagal')->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($auditor)->getJson('/api/v1/aud/rekod?pengguna=bob')->assertOk()->assertJsonPath('meta.total', 2);
        $this->actingAs($auditor)->getJson('/api/v1/aud/rekod?alamat_ip=bukan-ip')->assertStatus(422);
    }

    /** Kriteria penerimaan 6. */
    public function test_SRS_AUD_11_record_chronology_spans_modules_in_time_order(): void
    {
        $response = $this->actingAs($this->auditor())
            ->getJson('/api/v1/aud/kronologi?nama_entiti=lot_tanah&id_rekod=1')
            ->assertOk();

        $this->assertSame(
            [['pro', 'kemas_kini'], ['wfe', 'lulus'], ['dms', 'muat_naik'], ['pro', 'kemas_kini']],
            collect($response->json('data'))->map(fn ($r) => [$r['modul'], $r['jenis_tindakan']])->all(),
        );
        $this->assertSame(['2026-03-02 09:00:00', '2026-03-03 10:00:00', '2026-03-05 12:00:00', '2026-04-10 09:30:00'], collect($response->json('data'))->pluck('tarikh_masa_tempatan')->all());
    }

    /** Kriteria penerimaan 4 (export part) + 5. */
    public function test_SRS_AUD_12_export_in_all_four_formats_with_checksum_and_metadata(): void
    {
        $auditor = $this->auditor();
        $criteria = ['modul' => 'pro', 'dari' => '2026-03-01', 'hingga' => '2026-04-30'];

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $response = $this->actingAs($auditor)->get('/api/v1/aud/eksport?'.http_build_query([...$criteria, 'format' => $format]));
            $response->assertOk();

            $content = $response->streamedContent();
            $this->assertSame(hash('sha256', $content), $response->headers->get('X-Checksum-SHA256'), $format);

            $zipPath = $this->scratch."/pakej.{$format}.zip";
            file_put_contents($zipPath, $content);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($zipPath) === true);
            $metadata = json_decode((string) $zip->getFromName('metadata.json'), true);
            $data = (string) $zip->getFromName($metadata['fail_data']);
            $zip->close();

            $this->assertSame(3, $metadata['bilangan_rekod']);
            $this->assertSame($criteria, $metadata['kriteria_carian']);
            $this->assertSame($auditor->email, $metadata['pengeksport']['emel']);
            $this->assertNotEmpty($metadata['tarikh_masa_utc']);
            $this->assertSame(hash('sha256', $data), $metadata['sha256_fail_data']);

            match ($format) {
                'csv' => $this->assertSame(4, substr_count($data, "\n")), // heading + 3 rows
                'json' => $this->assertCount(3, json_decode($data, true)['rekod']),
                'pdf' => $this->assertStringStartsWith('%PDF', $data),
                'xlsx' => $this->assertStringStartsWith('PK', $data),
            };

            $this->assertDatabaseHas('audit_eksport', ['format' => $format, 'bilangan_rekod' => 3, 'checksum' => hash('sha256', $content)]);
        }
    }

    /** Kriteria penerimaan 5 (export is itself audited) — SRS-AUD-13/14. */
    public function test_SRS_AUD_14_search_view_chronology_and_export_of_the_trail_are_audited(): void
    {
        $auditor = $this->auditor();
        $id = (int) DB::table('audit_rekod')->value('id');

        $this->actingAs($auditor)->getJson('/api/v1/aud/rekod?modul=pro')->assertOk();
        $this->actingAs($auditor)->getJson("/api/v1/aud/rekod/{$id}")->assertOk();
        $this->actingAs($auditor)->getJson('/api/v1/aud/kronologi?nama_entiti=lot_tanah&id_rekod=1')->assertOk();
        $response = $this->actingAs($auditor)->get('/api/v1/aud/eksport?format=json&modul=pro');

        $this->assertAudited('carian', ['modul' => 'aud', 'id_pengguna' => $auditor->id])->assertMeta(['kriteria' => ['modul' => 'pro'], 'bilangan_hasil' => 3]);
        $this->assertAudited('lihat', ['modul' => 'aud', 'id_rekod' => $id]);
        $this->assertAudited('lihat_kronologi', ['modul' => 'aud', 'nama_entiti' => 'lot_tanah', 'id_rekod' => '1']);
        $this->assertAudited('eksport', ['modul' => 'aud', 'id_pengguna' => $auditor->id])
            ->assertMeta(['format' => 'json', 'bilangan_rekod' => 3, 'checksum' => $response->headers->get('X-Checksum-SHA256')]);
    }

    public function test_SRS_AUD_13_export_history_lists_checksums(): void
    {
        $auditor = $this->auditor();
        $this->actingAs($auditor)->get('/api/v1/aud/eksport?format=csv')->assertOk();

        $this->actingAs($auditor)->getJson('/api/v1/aud/eksport/sejarah')
            ->assertOk()
            ->assertJsonPath('data.0.format', 'csv')
            ->assertJsonPath('data.0.pengecam_pengguna', $auditor->email)
            ->assertJsonStructure(['data' => [['checksum', 'bilangan_rekod', 'kriteria_carian', 'tarikh_masa_tempatan']]]);
    }

    public function test_SRS_AUD_23_access_is_limited_to_audit_permissions_and_read_only(): void
    {
        // Ordinary user and even a legacy admin without aud.* keys: nothing.
        $plain = User::factory()->create(['role' => 'user']);
        $this->actingAs($plain)->getJson('/api/v1/aud/rekod')->assertForbidden();
        $this->actingAs($plain)->get('/api/v1/aud/eksport?format=csv')->assertForbidden();
        $this->actingAs($plain)->getJson('/api/v1/aud/integriti')->assertForbidden();
        $this->actingAs($plain)->getJson('/api/v1/aud/laporan/luar_waktu')->assertForbidden();
        $this->actingAs($plain)->putJson('/api/v1/aud/retensi', ['tempoh_dalam_talian_bulan' => 30])->assertForbidden();

        // Read-only auditor: can search, cannot export or touch the archive.
        $reader = $this->auditor(['aud.rekod.lihat']);
        $this->actingAs($reader)->getJson('/api/v1/aud/rekod')->assertOk();
        $this->actingAs($reader)->get('/api/v1/aud/eksport?format=csv')->assertForbidden();
        $this->actingAs($reader)->getJson('/api/v1/aud/rekod?sumber=arkib')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/aud/rekod')->assertUnauthorized();
    }

    public function test_SRS_AUD_15_automatic_reports(): void
    {
        $auditor = $this->auditor();
        $range = '?dari=2026-03-01&hingga=2026-04-30';

        $this->actingAs($auditor)->getJson('/api/v1/aud/laporan/sulit_rahsia'.$range)
            ->assertOk()->assertJsonPath('data.jumlah', 1)->assertJsonPath('data.rekod.0.nama_entiti', 'kes_mahkamah');

        $this->actingAs($auditor)->getJson('/api/v1/aud/laporan/luar_waktu'.$range)
            ->assertOk()->assertJsonPath('data.jumlah', 1)->assertJsonPath('data.rekod.0.tarikh_masa_tempatan', '2026-04-11 23:00:00');

        config(['aud.ambang_kelompok' => 2]);
        $this->actingAs($auditor)->getJson('/api/v1/aud/laporan/kelompok?dari=2026-03-01&hingga=2026-12-31')->assertOk();

        config(['aud.ambang_eksport_besar' => 1]);
        $this->actingAs($auditor)->get('/api/v1/aud/eksport?format=csv')->assertOk();
        $today = Carbon::now('Asia/Kuala_Lumpur')->format('Y-m-d');
        $this->actingAs($auditor)->getJson("/api/v1/aud/laporan/eksport_besar?dari={$today}&hingga={$today}")
            ->assertOk()->assertJsonPath('data.jumlah', 1);

        $this->actingAs($auditor)->getJson("/api/v1/aud/laporan/capaian_audit?dari={$today}&hingga={$today}")
            ->assertOk()->assertJsonPath('data.rekod.0.modul', 'aud');

        $this->actingAs($auditor)->getJson('/api/v1/aud/laporan/tiada_jenis')->assertStatus(422);

        $this->artisan('aud:laporan', ['--tarikh' => '2026-04-11'])->assertSuccessful();
        $this->assertFileExists(storage_path('app/aud/laporan/2026-04-11.json'));
        @unlink(storage_path('app/aud/laporan/2026-04-11.json'));
    }

    public function test_SRS_AUD_15_bulk_activity_report_flags_users_above_threshold(): void
    {
        config(['aud.ambang_kelompok' => 3]);
        $logger = app(AuditLogger::class);
        Carbon::setTestNow(Carbon::parse('2026-05-05 10:05', 'Asia/Kuala_Lumpur'));
        $this->actingAs($this->bob);
        for ($i = 0; $i < 4; $i++) {
            $logger->record('muat_turun', 'dms', 'dokumen', "X{$i}");
        }
        Carbon::setTestNow();

        $this->actingAs($this->auditor())->getJson('/api/v1/aud/laporan/kelompok?dari=2026-05-05&hingga=2026-05-05')
            ->assertOk()
            ->assertJsonPath('data.jumlah', 1)
            ->assertJsonPath('data.rekod.0.id_pengguna', $this->bob->id)
            ->assertJsonPath('data.rekod.0.bilangan', 4);
    }
}
