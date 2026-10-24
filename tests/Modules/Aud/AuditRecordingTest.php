<?php

namespace Tests\Modules\Aud;

use App\Http\Middleware\AssignRequestId;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Aud\Contracts\Auditable;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Aud\Contracts\HasClassification;
use App\Modules\Aud\Events\AuditWriteFailed;
use App\Modules\Aud\Services\AuditWriter;
use App\Modules\Rbc\Events\AccessDenied;
use App\Modules\Rbc\Events\AccountStatusChanged;
use App\Modules\Rbc\Events\LoginFailed;
use App\Modules\Rbc\Events\UserLoggedIn;
use App\Modules\Rbc\Events\UserLoggedOut;
use App\Modules\Rbc\Events\UserRolesChanged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

/** AUD-a: recording at the infrastructure layer (SRS-AUD-01..05, 09, 16–19, 25, 26). */
class AuditRecordingTest extends AudTestCase
{
    private function makeWidgetTable(): void
    {
        Schema::create('aud_ujian_widget', function (Blueprint $t) {
            $t->id();
            $t->string('nama');
            $t->string('no_kp')->nullable();
            $t->string('nota_rahsia_dalaman')->nullable();
            $t->string('klasifikasi')->nullable();
            $t->timestamps();
        });
    }

    /** Kriteria penerimaan 1 (with users standing in for lot_tanah). */
    public function test_SRS_AUD_05_single_field_change_records_field_old_new_user_role_ip_module_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->access->grant($admin, ['rbc.pengguna.kemas_kini'], ['pentadbir_sistem']);
        $target = User::factory()->create(['name' => 'Ali Abu', 'role' => 'user', 'is_active' => true]);

        $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->putJson("/api/v1/users/{$target->id}", [
                'name' => 'Ali bin Abu', 'email' => $target->email, 'role' => 'user', 'is_active' => true,
            ])->assertOk();

        $audit = $this->assertAudited('kemas_kini', ['nama_entiti' => 'users', 'id_rekod' => $target->id])
            ->assertFieldChanged('name', 'Ali Abu', 'Ali bin Abu');

        $this->assertCount(1, $audit->changes, 'Only the changed field is recorded');
        $this->assertSame($admin->id, (int) $audit->get('id_pengguna'));
        $this->assertSame(['pentadbir_sistem'], json_decode($audit->get('peranan_semasa'), true));
        $this->assertSame('10.20.30.40', $audit->get('alamat_ip'));
        $this->assertSame('rbc', $audit->get('modul'));
        $this->assertNotNull($audit->get('tarikh_masa_utc'));
        $this->assertSame('berjaya', $audit->get('status_transaksi'));
    }

    public function test_SRS_AUD_01_create_update_delete_of_any_model_is_recorded(): void
    {
        Setting::set('site_name', 'SISDATA');
        Setting::set('site_name', 'SISDATA MAIS');
        $setting = Setting::where('key', 'site_name')->firstOrFail();
        $setting->delete();

        $this->assertAudited('cipta', ['nama_entiti' => 'settings', 'id_rekod' => $setting->id, 'modul' => 'adm'])
            ->assertFieldChanged('key', null, 'site_name')
            ->assertFieldChanged('value', null, 'SISDATA');
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'settings'])->assertFieldChanged('value', 'SISDATA', 'SISDATA MAIS');
        $this->assertAudited('padam', ['nama_entiti' => 'settings'])->assertFieldChanged('value', 'SISDATA MAIS', null);
    }

    public function test_SRS_AUD_09_global_listener_covers_a_model_with_no_audit_code_at_all(): void
    {
        $this->makeWidgetTable();
        $widget = AudTestWidget::create(['nama' => 'A']);
        $widget->update(['nama' => 'B']);

        $this->assertAudited('cipta', ['nama_entiti' => 'aud_ujian_widget', 'id_rekod' => $widget->id, 'modul' => 'teras']);
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'aud_ujian_widget'])
            ->assertFieldChanged('nama', 'A', 'B')
            ->assertFieldNotRecorded('updated_at');
    }

    public function test_SRS_AUD_09_framework_and_audit_tables_are_not_audited(): void
    {
        DB::table('cache')->insert(['key' => 'x', 'value' => 'y', 'expiration' => time() + 60]);
        Setting::set('a', 'b');

        $this->assertSame(0, DB::table('audit_rekod')->whereIn('nama_entiti', ['cache', 'audit_rekod', 'audit_perubahan_medan'])->count());
        $this->assertSame(1, $this->newAuditRows()->count());
    }

    public function test_SRS_AUD_04_records_session_device_channel_entity_and_correlation_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->access->grant($admin, ['rbc.pengguna.kemas_kini'], ['pentadbir_sistem']);
        $target = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148',
                'X-Saluran' => 'mobile',
                AssignRequestId::HEADER => 'ujian-korelasi-123',
            ])
            ->putJson("/api/v1/users/{$target->id}", ['name' => 'Baru', 'email' => $target->email, 'role' => 'user', 'is_active' => true])
            ->assertOk();

        $audit = $this->assertAudited('kemas_kini', ['nama_entiti' => 'users', 'id_rekod' => $target->id]);
        $this->assertSame('mudah_alih', $audit->get('jenis_peranti'));
        $this->assertSame('mudah_alih', $audit->get('saluran_capaian'));
        $this->assertSame('ujian-korelasi-123', $audit->get('id_korelasi'));
        $this->assertStringContainsString('iPhone', $audit->get('user_agent'));
        $this->assertNotNull($audit->get('lokasi_anggaran'));
    }

    public function test_SRS_AUD_04_console_work_is_recorded_as_system_channel(): void
    {
        Setting::set('k', 'v');

        $audit = $this->assertAudited('cipta', ['nama_entiti' => 'settings']);
        $this->assertSame('sistem', $audit->get('saluran_capaian'));
        $this->assertSame('pelayan', $audit->get('jenis_peranti'));
        $this->assertNull($audit->get('id_pengguna'));
    }

    public function test_SRS_AUD_03_reason_from_scope_helper_and_from_request_header(): void
    {
        AuditScope::withReason('Pembetulan data migrasi', fn () => Setting::set('alasan', '1'));
        $this->assertAudited('cipta', ['nama_entiti' => 'settings', 'sebab_perubahan' => 'Pembetulan data migrasi']);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->access->grant($admin, ['rbc.pengguna.kemas_kini'], ['pentadbir_sistem']);
        $target = User::factory()->create(['role' => 'user']);
        $this->actingAs($admin)
            ->withHeaders(['X-Sebab-Perubahan' => rawurlencode('Permohonan pengguna #12')])
            ->putJson("/api/v1/users/{$target->id}", ['name' => 'Z', 'email' => $target->email, 'role' => 'user', 'is_active' => true])
            ->assertOk();

        $this->assertAudited('kemas_kini', ['id_rekod' => $target->id, 'sebab_perubahan' => 'Permohonan pengguna #12']);
    }

    /** Kriteria penerimaan 9. */
    public function test_SRS_AUD_25_passwords_tokens_and_ic_numbers_are_masked(): void
    {
        $user = User::factory()->create(['password' => 'RahsiaSangat123!']);
        $user->forceFill(['password' => 'KataLaluanBaru456!', 'remember_token' => 'tok-abc'])->save();

        $this->assertAudited('cipta', ['nama_entiti' => 'users', 'id_rekod' => $user->id])->assertFieldMasked('password');
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'users', 'id_rekod' => $user->id])->assertFieldMasked('password');

        $this->makeWidgetTable();
        AudTestWidgetSensitive::create(['nama' => 'X', 'no_kp' => '800101-10-5555', 'nota_rahsia_dalaman' => 'jangan']);
        $this->assertAudited('cipta', ['nama_entiti' => 'aud_ujian_widget'])
            ->assertFieldMasked('no_kp')
            ->assertFieldMasked('nota_rahsia_dalaman')
            ->assertFieldChanged('nama', null, 'X');

        app(AuditLogger::class)->record('jana_kunci_api', 'rbc', 'personal_access_tokens', 1, changes: ['token' => [null, 'plain-secret-token']], meta: ['api_key' => 'sk-123', 'nama' => 'Integrasi GRP']);
        $this->assertAudited('jana_kunci_api')->assertFieldMasked('token')->assertMeta(['api_key' => '[DITAPIS]', 'nama' => 'Integrasi GRP']);

        $everything = DB::table('audit_perubahan_medan')->pluck('nilai_baharu')->implode('|').DB::table('audit_rekod')->pluck('meta')->implode('|');
        foreach (['RahsiaSangat123!', 'KataLaluanBaru456!', '800101-10-5555', 'plain-secret-token', 'sk-123', 'tok-abc'] as $secret) {
            $this->assertStringNotContainsString($secret, $everything);
        }
    }

    public function test_SRS_AUD_02_explicit_entries_for_view_download_print_approval_and_ai_prompt(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record('lihat', 'pro', 'lot_tanah', 77, meta: ['klasifikasi' => 'rahsia']);
        $logger->record('muat_turun', 'dms', 'dokumen', 'doc-1');
        $logger->record('cetak', 'dms', 'dokumen', 'doc-1');
        $logger->record('lulus', 'wfe', 'permohonan', 5, reason: 'Lengkap');
        $logger->record('arahan_ai', 'kba', null, null, meta: ['arahan' => 'Ringkaskan kes', 'hasil' => 'Ringkasan…']);

        $this->assertAudited('lihat', ['modul' => 'pro', 'klasifikasi' => 'rahsia']);
        $this->assertAudited('muat_turun', ['modul' => 'dms', 'id_rekod' => 'doc-1']);
        $this->assertAudited('cetak', ['modul' => 'dms']);
        $this->assertAudited('lulus', ['modul' => 'wfe', 'sebab_perubahan' => 'Lengkap']);
        $this->assertAudited('arahan_ai', ['modul' => 'kba'])->assertMeta(['arahan' => 'Ringkaskan kes', 'hasil' => 'Ringkasan…']);
    }

    public function test_SRS_AUD_16_document_activity_entries(): void
    {
        $logger = app(AuditLogger::class);
        foreach (['muat_naik', 'muat_turun', 'pratonton', 'cetak', 'pinda', 'padam', 'arkib'] as $action) {
            $logger->record($action, 'dms', 'dokumen', 'D-9', meta: ['induk_entiti' => 'lot_tanah', 'induk_id' => '1']);
        }

        foreach (['muat_naik', 'muat_turun', 'pratonton', 'cetak', 'pinda', 'padam', 'arkib'] as $action) {
            $this->assertAudited($action, ['modul' => 'dms', 'nama_entiti' => 'dokumen', 'id_rekod' => 'D-9']);
        }
    }

    public function test_SRS_AUD_17_database_notifications_are_recorded_automatically(): void
    {
        $user = User::factory()->create();
        $user->notify(new AudTestNotification);

        $this->assertAudited('cipta', ['nama_entiti' => 'notifications', 'modul' => 'ntf'])
            ->assertFieldChanged('notifiable_id', null, (string) $user->id);

        app(AuditLogger::class)->record('hantar_notifikasi', 'ntf', 'notifications', 'n-1', meta: ['saluran' => 'emel', 'status_penghantaran' => 'dihantar', 'diterima' => true]);
        $this->assertAudited('hantar_notifikasi')->assertMeta(['saluran' => 'emel', 'diterima' => true]);
    }

    public function test_SRS_AUD_18_workflow_decision_entries_keep_approver_decision_and_stage(): void
    {
        $approver = User::factory()->create();
        $this->actingAs($approver);

        app(AuditLogger::class)->record('lulus', 'wfe', 'wfe_tugasan', 10, reason: 'Dokumen lengkap', meta: ['peringkat' => 'Pengarah', 'keputusan' => 'lulus']);

        $this->assertAudited('lulus', ['modul' => 'wfe', 'id_pengguna' => $approver->id, 'sebab_perubahan' => 'Dokumen lengkap'])
            ->assertMeta(['peringkat' => 'Pengarah']);
    }

    public function test_SRS_AUD_19_password_login_success_and_failure_are_recorded_with_ip_device_location(): void
    {
        $user = User::factory()->create(['email' => 'pegawai@mais.gov.my', 'password' => 'Betul123!']);
        $this->withHeader('Referer', 'http://localhost:3000'); // Sanctum SPA (stateful) request

        $this->withServerVariables(['REMOTE_ADDR' => '203.106.1.10'])
            ->postJson('/api/v1/login', ['email' => 'pegawai@mais.gov.my', 'password' => 'Salah'])
            ->assertStatus(422);
        $failed = $this->assertAudited('log_masuk', ['status_transaksi' => 'gagal', 'pengecam_pengguna' => 'pegawai@mais.gov.my']);
        $this->assertSame('203.106.1.10', $failed->get('alamat_ip'));
        $this->assertSame('Malaysia (anggaran)', $failed->get('lokasi_anggaran'));

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->postJson('/api/v1/login', ['email' => 'pegawai@mais.gov.my', 'password' => 'Betul123!'])
            ->assertOk();
        $ok = $this->assertAudited('log_masuk', ['status_transaksi' => 'berjaya', 'id_pengguna' => $user->id]);
        $this->assertSame('Rangkaian dalaman MAIS', $ok->get('lokasi_anggaran'));
        $this->assertStringStartsWith('sesi:', (string) $ok->get('id_sesi'));
        $this->assertAuditCount(1, 'log_masuk', ['status_transaksi' => 'berjaya']);
    }

    public function test_SRS_AUD_19_rbc_events_are_recorded(): void
    {
        $user = User::factory()->create();

        event(new UserLoggedIn($user, 'sso'));
        event(new LoginFailed(null, 'tiada@mais.gov.my', 'akaun_tidak_wujud'));
        event(new UserLoggedOut($user));
        event(new AccountStatusChanged($user, 'aktif', 'digantung', 'Tidak aktif 90 hari'));
        event(new AccessDenied($user, 'pro.lot.padam', 'lot_tanah:5'));

        $this->assertAudited('log_masuk', ['id_pengguna' => $user->id, 'status_transaksi' => 'berjaya'])->assertMeta(['kaedah' => 'sso']);
        $this->assertAudited('log_masuk', ['status_transaksi' => 'gagal', 'pengecam_pengguna' => 'tiada@mais.gov.my'])->assertMeta(['sebab_gagal' => 'akaun_tidak_wujud']);
        $this->assertAudited('log_keluar', ['id_pengguna' => $user->id]);
        $this->assertAudited('tukar_status', ['id_rekod' => $user->id, 'sebab_perubahan' => 'Tidak aktif 90 hari'])->assertFieldChanged('status', 'aktif', 'digantung');
        $this->assertAudited('capaian_ditolak', ['id_pengguna' => $user->id, 'status_transaksi' => 'ditolak'])->assertMeta(['kebenaran' => 'pro.lot.padam']);
    }

    /** Role assignments are pivot rows (no model events): MOD-RBC's UserRolesChanged is audited instead. */
    public function test_SRS_AUD_02_role_assignment_changes_are_recorded(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        UserRolesChanged::dispatch($target, ['pelulus'], ['pengguna_paparan'], 'manual', $admin);

        $this->assertAudited('tukar_peranan', ['modul' => 'rbc', 'nama_entiti' => 'users', 'id_rekod' => $target->id, 'id_pengguna' => $admin->id])
            ->assertFieldChanged('peranan_ditambah', null, 'pelulus')
            ->assertFieldChanged('peranan_dikeluarkan', 'pengguna_paparan', null)
            ->assertMeta(['sumber' => 'manual', 'ditambah' => ['pelulus'], 'dikeluarkan' => ['pengguna_paparan']]);

        // End to end through MOD-RBC: the legacy role toggle grants Pentadbir Sistem.
        $target->update(['role' => 'admin']);
        $this->assertAudited('tukar_peranan', ['id_rekod' => $target->id, 'id_pengguna' => null])
            ->assertFieldChanged('peranan_ditambah', null, 'pentadbir_sistem');
    }

    public function test_SRS_AUD_03_rolled_back_change_is_recorded_once_as_failed(): void
    {
        try {
            DB::transaction(function () {
                Setting::set('gagal', 'x');
                throw new \RuntimeException('business failure');
            });
        } catch (\RuntimeException) {
        }

        $this->assertAudited('cipta', ['nama_entiti' => 'settings', 'status_transaksi' => 'gagal'])->assertMeta(['transaksi_dibatalkan' => true]);
        $this->assertAuditCount(0, 'cipta', ['nama_entiti' => 'settings', 'status_transaksi' => 'berjaya']);
        $this->assertNull(Setting::where('key', 'gagal')->first());
    }

    public function test_SRS_AUD_03_change_inside_committed_transaction_is_recorded_after_commit(): void
    {
        DB::transaction(function () {
            Setting::set('dalam_tx', '1');
            $this->assertSame(0, $this->newAuditRows()->count(), 'not written before commit');
        });

        $this->assertAudited('cipta', ['nama_entiti' => 'settings', 'status_transaksi' => 'berjaya']);
    }

    public function test_SRS_AUD_26_audit_failure_does_not_fail_the_business_transaction(): void
    {
        Event::fake([AuditWriteFailed::class]);
        AuditWriter::$simulateFailure = true;

        $admin = User::factory()->create(['role' => 'admin']);
        $this->access->grant($admin, ['rbc.pengguna.kemas_kini'], ['pentadbir_sistem']);
        $target = User::factory()->create(['role' => 'user', 'name' => 'Lama']);
        $this->actingAs($admin)
            ->putJson("/api/v1/users/{$target->id}", ['name' => 'Baharu', 'email' => $target->email, 'role' => 'user', 'is_active' => true])
            ->assertOk();

        $this->assertSame('Baharu', $target->fresh()->name);
        $this->assertSame(0, $this->newAuditRows()->count());
        Event::assertDispatched(AuditWriteFailed::class);
        $this->assertFileExists(config('aud.laluan_insiden'));
        $this->assertFileExists(config('aud.laluan_spool'));

        // Incident recovery: the spooled entries are written once the store is back.
        AuditWriter::$simulateFailure = false;
        $this->artisan('aud:ulang-spool')->assertSuccessful();
        $this->assertAudited('kemas_kini', ['nama_entiti' => 'users', 'id_rekod' => $target->id])
            ->assertFieldChanged('name', 'Lama', 'Baharu')
            ->assertMeta(['diulang_dari_spool' => true]);
    }

    public function test_SRS_AUD_15_classification_is_taken_from_model(): void
    {
        $this->makeWidgetTable();
        AudTestWidgetSensitive::create(['nama' => 'S']);
        AudTestWidget::create(['nama' => 'K', 'klasifikasi' => 'Sulit']);

        $this->assertAuditCount(1, 'cipta', ['klasifikasi' => 'rahsia']);
        $this->assertAuditCount(1, 'cipta', ['klasifikasi' => 'sulit']);
    }
}

/** @property int $id */
class AudTestWidget extends Model
{
    protected $table = 'aud_ujian_widget';

    protected $guarded = [];
}

class AudTestWidgetSensitive extends Model implements Auditable, HasClassification
{
    protected $table = 'aud_ujian_widget';

    protected $guarded = [];

    public function auditModule(): string
    {
        return 'pro';
    }

    public function auditExcept(): array
    {
        return ['klasifikasi'];
    }

    public function auditSensitive(): array
    {
        return ['nota_rahsia_dalaman'];
    }

    public function auditClassification(): string
    {
        return 'rahsia';
    }
}

class AudTestNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['mesej' => 'Ujian'];
    }
}
