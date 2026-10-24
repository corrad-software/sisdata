<?php

namespace Tests\Modules\Aud;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Aud\AudServiceProvider;
use App\Modules\Aud\Events\AuditIntegrityBroken;
use App\Modules\Aud\Models\AuditRekod;
use App\Modules\Aud\Services\ChainHasher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/** AUD-b: append-only store, hash chain, scheduled integrity check (SRS-AUD-06..08). */
class AuditIntegrityTest extends AudTestCase
{
    private function seedTrail(int $n = 5): void
    {
        for ($i = 1; $i <= $n; $i++) {
            Setting::set("kunci_{$i}", "nilai_{$i}");
        }
    }

    /** Runs a statement in a savepoint and returns the DB error message (or null if it succeeded). */
    private function attempt(string $sql): ?string
    {
        try {
            DB::transaction(fn () => DB::unprepared($sql));
        } catch (QueryException $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function test_SRS_AUD_06_records_form_a_gapless_sha256_chain_over_content_and_field_changes(): void
    {
        $this->seedTrail(4);

        $rows = $this->newAuditRows()->orderBy('no_urutan')->get();
        $this->assertSame([$this->seq(1), $this->seq(2), $this->seq(3), $this->seq(4)], $rows->pluck('no_urutan')->map(fn ($v) => (int) $v)->all());

        $previous = $this->baseline === 0
            ? ChainHasher::GENESIS
            : DB::table('audit_rekod')->where('no_urutan', $this->baseline)->value('cincangan_rekod');
        foreach ($rows as $row) {
            $this->assertSame($previous, $row->cincangan_sebelum);
            $changes = DB::table('audit_perubahan_medan')->where('id_audit', $row->id)->orderBy('id')->get();
            $this->assertNotEmpty($changes);
            $this->assertSame(ChainHasher::hash($row, $changes), $row->cincangan_rekod);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $row->cincangan_rekod);
            $previous = $row->cincangan_rekod;
        }
    }

    /** Kriteria penerimaan 2 — database account. */
    public function test_SRS_AUD_08_app_database_account_cannot_update_delete_or_truncate_audit_records(): void
    {
        $before = DB::table('audit_rekod')->count();
        $this->seedTrail(2);

        $this->assertStringContainsString('permission denied', (string) $this->attempt("UPDATE audit_rekod SET modul = 'x'"));
        $this->assertStringContainsString('permission denied', (string) $this->attempt("UPDATE audit_perubahan_medan SET nilai_baharu = 'x'"));
        $this->assertStringContainsString('permission denied', (string) $this->attempt('TRUNCATE audit_rekod CASCADE'));
        $this->assertStringContainsString('hanya-tambah', (string) $this->attempt('DELETE FROM audit_rekod'));
        $this->assertStringContainsString('hanya-tambah', (string) $this->attempt('DELETE FROM audit_perubahan_medan'));
        $this->assertStringContainsString('permission denied', (string) $this->attempt('DELETE FROM audit_eksport'));

        // Even with the archive flag set by hand, nothing young enough / not yet archived can be deleted.
        $this->assertStringContainsString('hanya-tambah', (string) $this->attempt("SET LOCAL sisdata.aud_arkib = 'on'; DELETE FROM audit_rekod"));

        $this->assertFalse((bool) DB::selectOne("select has_table_privilege('audit_rekod', 'UPDATE') as p")->p);
        $this->assertFalse((bool) DB::selectOne("select has_table_privilege('audit_rekod', 'TRUNCATE') as p")->p);
        $this->assertSame($before + 2, DB::table('audit_rekod')->count());
    }

    public function test_SRS_AUD_08_triggers_reject_changes_even_when_privileges_are_regranted(): void
    {
        $this->seedTrail(1);
        DB::unprepared('GRANT UPDATE, TRUNCATE ON audit_rekod TO CURRENT_USER');

        $this->assertStringContainsString('hanya-tambah', (string) $this->attempt("UPDATE audit_rekod SET modul = 'x'"));
        $this->assertStringContainsString('hanya-tambah', (string) $this->attempt('TRUNCATE audit_rekod CASCADE'));
    }

    /** Kriteria penerimaan 2 — application interface, every role incl. Pentadbir Sistem. */
    public function test_SRS_AUD_08_application_interface_rejects_update_and_delete_for_all_roles(): void
    {
        $this->seedTrail(1);
        $id = (int) $this->newAuditRows()->value('id');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->access->grant($admin, AudServiceProvider::PERMISSIONS, ['pentadbir_sistem']);

        foreach (['putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->actingAs($admin)->{$method}("/api/v1/aud/rekod/{$id}", ['modul' => 'x'])->assertStatus(405);
        }
        $this->actingAs($admin)->postJson('/api/v1/aud/rekod', ['modul' => 'x'])->assertStatus(405);

        $record = AuditRekod::findOrFail($id);
        $record->forceFill(['modul' => 'x']);
        $this->assertThrows(fn () => $record->save(), \LogicException::class);
        $this->assertThrows(fn () => $record->delete(), \LogicException::class);

        $this->assertSame(1, DB::table('audit_rekod')->where('modul', 'adm')->count());
    }

    /** Kriteria penerimaan 3. */
    public function test_SRS_AUD_07_direct_tampering_is_detected_and_alerts(): void
    {
        Event::fake([AuditIntegrityBroken::class]);
        $this->seedTrail(5);

        $this->artisan('aud:semak-integriti')->assertSuccessful();
        $this->assertSame('lulus', DB::table('audit_integriti_semakan')->latest('id')->value('status'));

        // A DBA with owner rights disables the trigger and edits record #3.
        DB::unprepared('ALTER TABLE audit_rekod DISABLE TRIGGER audit_rekod_hanya_tambah; GRANT UPDATE ON audit_rekod TO CURRENT_USER;');
        DB::table('audit_rekod')->where('no_urutan', $this->seq(3))->update(['pengecam_pengguna' => 'penyamar@mais.gov.my']);

        $this->artisan('aud:semak-integriti')->assertFailed();

        $check = DB::table('audit_integriti_semakan')->latest('id')->first();
        $this->assertSame('gagal', $check->status);
        $this->assertSame($this->seq(3), (int) $check->no_urutan_rosak);
        Event::assertDispatched(AuditIntegrityBroken::class, fn (AuditIntegrityBroken $e) => $e->firstBadSequence === $this->seq(3));
        $this->assertFileExists(config('aud.laluan_insiden'));
    }

    public function test_SRS_AUD_07_deleted_or_altered_field_change_and_truncated_tail_are_detected(): void
    {
        Event::fake([AuditIntegrityBroken::class]);
        $this->seedTrail(4);
        $this->artisan('aud:semak-integriti')->assertSuccessful();

        DB::unprepared('ALTER TABLE audit_perubahan_medan DISABLE TRIGGER audit_perubahan_medan_hanya_tambah; GRANT UPDATE ON audit_perubahan_medan TO CURRENT_USER;');
        $id2 = DB::table('audit_rekod')->where('no_urutan', $this->seq(2))->value('id');
        DB::table('audit_perubahan_medan')->where('id_audit', $id2)->where('nama_medan', 'value')->update(['nilai_baharu' => 'diubah']);
        $this->artisan('aud:semak-integriti')->assertFailed();
        Event::assertDispatched(AuditIntegrityBroken::class, fn ($e) => $e->firstBadSequence === $this->seq(2));
    }

    public function test_SRS_AUD_07_removed_records_break_the_sequence(): void
    {
        Event::fake([AuditIntegrityBroken::class]);
        $this->seedTrail(4);
        $this->artisan('aud:semak-integriti')->assertSuccessful();

        DB::unprepared('ALTER TABLE audit_rekod DISABLE TRIGGER audit_rekod_hanya_tambah; ALTER TABLE audit_perubahan_medan DISABLE TRIGGER audit_perubahan_medan_hanya_tambah;');
        $last = DB::table('audit_rekod')->where('no_urutan', $this->seq(4))->value('id');
        DB::table('audit_perubahan_medan')->where('id_audit', $last)->delete();
        DB::table('audit_rekod')->where('id', $last)->delete();

        $this->artisan('aud:semak-integriti')->assertFailed();
        $this->assertStringContainsString('hilang', (string) DB::table('audit_integriti_semakan')->latest('id')->value('butiran'));

        $middle = DB::table('audit_rekod')->where('no_urutan', $this->seq(2))->value('id');
        DB::table('audit_perubahan_medan')->where('id_audit', $middle)->delete();
        DB::table('audit_rekod')->where('id', $middle)->delete();
        $this->artisan('aud:semak-integriti')->assertFailed();
        $this->assertSame($this->seq(2), (int) DB::table('audit_integriti_semakan')->latest('id')->value('no_urutan_rosak'));
    }

    public function test_SRS_AUD_07_integrity_check_is_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => (string) $e->command)->implode("\n");

        $this->assertStringContainsString('aud:semak-integriti', $commands);
        $this->assertStringContainsString('aud:arkib', $commands);
        $this->assertStringContainsString('aud:laporan', $commands);
    }

    public function test_SRS_AUD_07_integrity_status_api_and_manual_check(): void
    {
        $this->seedTrail(2);
        $auditor = $this->auditor();

        $this->actingAs($auditor)->postJson('/api/v1/aud/integriti/semak')->assertOk()->assertJsonPath('data.status', 'lulus');
        $this->actingAs($auditor)->getJson('/api/v1/aud/integriti')
            ->assertOk()
            ->assertJsonPath('data.semakan.0.status', 'lulus')
            ->assertJsonStructure(['data' => ['rantaian' => ['dalam_talian', 'arkib', 'no_urutan_terakhir']]]);

        $nobody = $this->auditor([]);
        $this->actingAs($nobody)->postJson('/api/v1/aud/integriti/semak')->assertForbidden();
    }
}
