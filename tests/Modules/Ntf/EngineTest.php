<?php

namespace Tests\Modules\Ntf;

use App\Models\User;
use App\Modules\Aud\Events\AuditIntegrityBroken;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Contracts\SensitiveDataRefused;
use App\Modules\Ntf\Contracts\SmsGateway;
use App\Modules\Ntf\Contracts\WhatsAppGateway;
use App\Modules\Ntf\Jobs\DeliverNotification;
use App\Modules\Ntf\Mail\NotifikasiMail;
use App\Modules\Ntf\Models\Mesej;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Models\StatusPengguna;
use App\Modules\Ntf\Services\Channels\LogSmsGateway;
use App\Modules\Ntf\Services\Channels\LogWhatsAppGateway;
use App\Modules\Ntf\Services\NotificationEngine;
use App\Modules\Ntf\Services\TypeRegistry;
use App\Modules\Rbc\Events\AccountStatusChanged;
use App\Modules\Rbc\Events\SuspiciousActivityDetected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\Modules\Ntf\Support\RecordingGateway;

class EngineTest extends NtfTestCase
{
    public function test_SRS_NTF_01_one_send_delivers_in_app_and_email(): void
    {
        $this->registerType();
        $user = $this->officer();

        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 1234', 'nama_penyewa' => 'Syarikat Maju', 'tarikh_tamat' => '31/12/2026', 'baki_hari' => 30], '/admin/pjk/kontrak/9');

        $this->assertDatabaseHas('ntf_status_pengguna', ['pengguna_id' => $user->id]);
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo($user->email) && str_contains($m->mesej->subjek, 'PT 1234'));
        $this->assertEqualsCanonicalizing(['dalam_sistem', 'emel'], Penghantaran::query()->pluck('saluran')->all());
    }

    public function test_SRS_NTF_02_single_engine_is_bound_to_the_contracts(): void
    {
        $this->assertInstanceOf(NotificationEngine::class, app(Notifier::class));
        $this->assertInstanceOf(TypeRegistry::class, app(NotificationTypes::class));
        $this->assertSame(app(NotificationTypes::class), app(NotificationTypes::class), 'type registry is a singleton');
        $this->assertTrue($this->types()->isRegistered('ntf.pengumuman'));
        $this->assertTrue($this->types()->isRegistered('ntf.penghantaran_gagal'));
    }

    public function test_SRS_NTF_02_unknown_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(Notifier::class)->send('xyz.tidak_wujud', $this->officer());
    }

    public function test_SRS_NTF_04_sms_and_whatsapp_go_through_adapter_interfaces(): void
    {
        $this->assertInstanceOf(LogSmsGateway::class, app(SmsGateway::class));
        $this->assertInstanceOf(LogWhatsAppGateway::class, app(WhatsAppGateway::class));

        RecordingGateway::$sent = [];
        $this->app->instance(SmsGateway::class, new RecordingGateway('sms'));
        $this->app->instance(WhatsAppGateway::class, new RecordingGateway('wa'));

        $jenis = $this->registerType();
        $jenis->update(['saluran_lalai' => ['sms', 'whatsapp']]);
        $user = $this->officer();

        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 1'], '/admin/x');

        $this->assertSame(['sms', 'wa'], array_column(RecordingGateway::sent(), 0));
        $this->assertStringContainsString('/admin/x', RecordingGateway::sent()[0][2]);
        $this->assertSame(2, Penghantaran::query()->where('status', Penghantaran::DITERIMA)->count());
    }

    public function test_SRS_NTF_05_dynamic_fields_are_filled_in_malay_template(): void
    {
        $this->registerType();
        $user = $this->officer();

        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 77', 'nama_penyewa' => 'Ali', 'tarikh_tamat' => '01/01/2027', 'baki_hari' => 7]);

        $mesej = Mesej::query()->firstOrFail();
        $this->assertSame('Pajakan lot PT 77 tamat dalam 7 hari', $mesej->subjek);
        $this->assertSame('Pajakan oleh Ali bagi lot PT 77 tamat pada 01/01/2027.', $mesej->kandungan);
    }

    public function test_SRS_NTF_06_event_based_triggers(): void
    {
        $admin = $this->admin();
        $user = $this->officer();

        event(new AccountStatusChanged($user, 'aktif', 'digantung'));
        event(new AuditIntegrityBroken(42, 'hash mismatch'));

        $this->assertTrue(StatusPengguna::query()->where('pengguna_id', $user->id)
            ->whereHas('mesej', fn ($q) => $q->where('subjek', 'Status akaun anda telah berubah'))->exists());
        $this->assertTrue(StatusPengguna::query()->where('pengguna_id', $admin->id)
            ->whereHas('mesej.jenis', fn ($q) => $q->where('kod', 'ntf.integriti_audit'))->exists());
    }

    public function test_SRS_RBC_22_suspicious_activity_alerts_system_administrators(): void
    {
        $sysAdmin = User::factory()->create();
        $this->access->grant($sysAdmin, ['rbc.amaran.kemas_kini']);
        $officer = $this->officer();
        $target = User::factory()->create(['email' => 'sasaran@mais.gov.my']);

        event(new SuspiciousActivityDetected('gagal_berulang', $target, '203.0.113.7', ['pengecam' => 'sasaran@mais.gov.my']));

        $status = StatusPengguna::query()->where('pengguna_id', $sysAdmin->id)
            ->whereHas('mesej.jenis', fn ($q) => $q->where('kod', 'ntf.amaran_keselamatan'))->with('mesej')->firstOrFail();
        $this->assertSame('Amaran keselamatan: Cubaan log masuk gagal berulang', $status->mesej->subjek);
        $this->assertStringContainsString('203.0.113.7', $status->mesej->kandungan);
        $this->assertStringContainsString('sasaran@mais.gov.my', $status->mesej->kandungan);
        $this->assertSame('kritikal', $this->types()->find('ntf.amaran_keselamatan')->keutamaan);
        $this->assertFalse(StatusPengguna::query()->whereIn('pengguna_id', [$officer->id, $target->id])->exists());
    }

    public function test_SRS_NTF_08_priority_is_stored_and_orders_the_list(): void
    {
        $this->registerType('pjk.biasa', NotificationTypes::BIASA, ['no_lot']);
        $this->registerType('pjk.kritikal', NotificationTypes::KRITIKAL, ['no_lot']);
        $user = $this->officer();

        app(Notifier::class)->send('pjk.biasa', $user, ['no_lot' => 'A']);
        app(Notifier::class)->send('pjk.kritikal', $user, ['no_lot' => 'B']);
        app(Notifier::class)->send('pjk.biasa', $user, ['no_lot' => 'C']);

        $list = $this->actingAs($user)->getJson('/api/v1/ntf/notifikasi')->assertOk()->json('data');
        $this->assertSame('kritikal', $list[0]['keutamaan']);
        $this->assertSame(['kritikal', 'biasa', 'biasa'], array_column($list, 'keutamaan'));
    }

    public function test_SRS_NTF_19_sensitive_fields_are_refused(): void
    {
        $jenis = $this->registerType(fields: ['no_lot', 'nilai_sewa']);
        $jenis->update(['medan_sensitif' => ['nilai_sewa']]);
        $user = $this->officer();

        try {
            app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 1', 'nilai_sewa' => 'RM 9,000']);
            $this->fail('Sensitive field was accepted');
        } catch (SensitiveDataRefused $e) {
            $this->assertSame(['nilai_sewa'], $e->fields);
        }

        // Global Sulit/Rahsia list (ADM parameter) applies to every type.
        $this->expectException(SensitiveDataRefused::class);
        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 1', 'no_kp' => '800101-10-1234']);
    }

    public function test_SRS_NTF_19_only_summary_and_internal_link_are_kept(): void
    {
        $this->registerType(fields: ['no_lot']);
        $user = $this->officer();

        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 1', 'catatan_dalaman' => 'bukan medan diisytihar'], '/admin/pjk/1');

        $mesej = Mesej::query()->firstOrFail();
        $this->assertSame(['no_lot' => 'PT 1'], $mesej->data);
        $this->assertSame('/admin/pjk/1', $mesej->pautan);
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => str_contains($m->render(), '/admin/pjk/1'));

        $this->expectException(InvalidArgumentException::class);
        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 2'], 'https://evil.example/phish');
    }

    public function test_SRS_NTF_21_delivery_goes_through_a_persistent_queue(): void
    {
        Queue::fake();
        $this->registerType();
        $user = $this->officer();

        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 1']);

        $this->assertContains(ShouldQueue::class, class_implements(DeliverNotification::class));
        Queue::assertPushed(DeliverNotification::class, 2);
        $this->assertSame(2, Penghantaran::query()->where('status', Penghantaran::BERATUR)->count(), 'rows persist before the worker runs');
        Mail::assertNothingSent();

        // Lost from the queue (e.g. Redis restarted): the sweeper re-queues it.
        $this->travel(15)->minutes();
        $this->artisan('ntf:proses-tertunggak')->assertSuccessful();
        Queue::assertPushed(DeliverNotification::class, 4);
    }

    public function test_SRS_NTF_22_same_event_to_same_recipient_is_sent_once_in_quiet_period(): void
    {
        $jenis = $this->registerType();
        $jenis->update(['tempoh_pendiaman_minit' => 30]);
        $a = $this->officer();
        $b = $this->officer();

        app(Notifier::class)->send('pjk.pajakan_tamat', $a, ['no_lot' => 'PT 1'], null, 'kontrak:1');
        app(Notifier::class)->send('pjk.pajakan_tamat', [$a, $b], ['no_lot' => 'PT 1'], null, 'kontrak:1');

        $this->assertSame(1, StatusPengguna::query()->where('pengguna_id', $a->id)->count());
        $this->assertSame(1, StatusPengguna::query()->where('pengguna_id', $b->id)->count());

        // Without an explicit key the same type + data + link is the same event.
        app(Notifier::class)->send('pjk.pajakan_tamat', $b, ['no_lot' => 'PT 9']);
        app(Notifier::class)->send('pjk.pajakan_tamat', $b, ['no_lot' => 'PT 9']);
        $this->assertSame(2, StatusPengguna::query()->where('pengguna_id', $b->id)->count());

        $this->travel(31)->minutes();
        app(Notifier::class)->send('pjk.pajakan_tamat', $a, ['no_lot' => 'PT 1'], null, 'kontrak:1');
        $this->assertSame(2, StatusPengguna::query()->where('pengguna_id', $a->id)->count());
    }

    public function test_SRS_NTF_15_every_delivery_is_audited(): void
    {
        $this->registerType();
        $user = $this->officer();

        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 1']);

        $records = $this->audit->where('hantar_notifikasi');
        $this->assertCount(2, $records);
        $this->assertEqualsCanonicalizing(['dalam_sistem', 'emel'], array_column(array_column($records, 'meta'), 'saluran'));
        $this->assertSame('pjk.pajakan_tamat', $records[0]['meta']['jenis']);
        $this->assertSame($user->id, $records[0]['meta']['penerima_id']);
        $this->assertSame('berjaya', $records[0]['status']);
        $this->assertNotNull($records[0]['meta']['tarikh_hantar']);
    }

    public function test_SRS_NTF_16_hantar_terima_baca_are_timestamped(): void
    {
        $this->registerType();
        $user = $this->officer();
        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 1']);

        $inApp = fn () => Penghantaran::query()->where('saluran', 'dalam_sistem')->firstOrFail();
        $email = Penghantaran::query()->where('saluran', 'emel')->firstOrFail();

        $this->assertSame(Penghantaran::DIHANTAR, $inApp()->status);
        $this->assertNotNull($inApp()->tarikh_hantar);
        $this->assertNull($inApp()->tarikh_terima);
        $this->assertSame(Penghantaran::DITERIMA, $email->status, 'SMTP gateway accepted');
        $this->assertNotNull($email->tarikh_terima);

        $this->travel(1)->minutes();
        $this->actingAs($user)->getJson('/api/v1/ntf/notifikasi/ringkas')->assertOk()->assertJsonPath('data.belum_baca', 1);
        $this->assertSame(Penghantaran::DITERIMA, $inApp()->status);
        $this->assertNotNull($inApp()->tarikh_terima);

        $this->travel(1)->minutes();
        $id = StatusPengguna::query()->value('id');
        $this->actingAs($user)->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'baca', 'ids' => [$id]])->assertOk();
        $this->assertSame(Penghantaran::DIBACA, $inApp()->status);
        $this->assertTrue($inApp()->tarikh_baca->gt($inApp()->tarikh_terima));
    }

    public function test_SRS_NTF_02_inactive_type_and_inactive_users_are_skipped(): void
    {
        $jenis = $this->registerType();
        $inactive = User::factory()->create(['is_active' => false]);
        app(Notifier::class)->send('pjk.pajakan_tamat', $inactive, ['no_lot' => 'PT 1']);
        $this->assertSame(0, Mesej::query()->count());

        $jenis->update(['aktif' => false]);
        app(Notifier::class)->send('pjk.pajakan_tamat', $this->officer(), ['no_lot' => 'PT 1']);
        $this->assertDatabaseCount('ntf_mesej', 0);
    }
}
