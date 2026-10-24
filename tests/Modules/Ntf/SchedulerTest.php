<?php

namespace Tests\Modules\Ntf;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Contracts\Reminders;
use App\Modules\Ntf\Contracts\SupervisorResolver;
use App\Modules\Ntf\Mail\NotifikasiMail;
use App\Modules\Ntf\Mail\RingkasanMail;
use App\Modules\Ntf\Models\EskalasiLog;
use App\Modules\Ntf\Models\KeutamaanPengguna;
use App\Modules\Ntf\Models\Mesej;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Models\StatusPengguna;
use Illuminate\Support\Facades\Mail;
use Tests\Modules\Ntf\Support\LeaseExpiryReminder;

class SchedulerTest extends NtfTestCase
{
    public function test_SRS_NTF_07_scheduled_reminder_runs_on_configured_days_through_both_channels(): void
    {
        $this->registerType();
        $officer = $this->officer();
        LeaseExpiryReminder::$leases = [30 => ['id' => 1, 'user' => $officer, 'lot' => 'PT 30'], 14 => ['id' => 2, 'user' => $officer, 'lot' => 'PT 14']];
        app(Reminders::class)->register(LeaseExpiryReminder::class);

        $this->artisan('ntf:peringatan', ['--tarikh' => '2026-11-01'])->assertSuccessful();

        $this->assertSame(['Pajakan lot PT 30 tamat dalam 30 hari'], Mesej::query()->pluck('subjek')->all());
        $this->assertEqualsCanonicalizing(['dalam_sistem', 'emel'], Penghantaran::query()->pluck('saluran')->all());
        Mail::assertSent(NotifikasiMail::class, fn ($m) => $m->hasTo($officer->email));

        // Re-running the same day sends nothing new.
        $this->artisan('ntf:peringatan', ['--tarikh' => '2026-11-01'])->assertSuccessful();
        $this->assertSame(1, Mesej::query()->count());
    }

    public function test_SRS_NTF_07_admin_changes_reminder_days_and_template_without_release(): void
    {
        // Kriteria penerimaan 1 + 2.
        $jenis = $this->registerType();
        $admin = $this->admin();
        $officer = $this->officer();
        LeaseExpiryReminder::$leases = [14 => ['id' => 2, 'user' => $officer, 'lot' => 'PT 14']];
        app(Reminders::class)->register(LeaseExpiryReminder::class);

        $this->artisan('ntf:peringatan', ['--tarikh' => '2026-11-01']);
        $this->assertSame(0, Mesej::query()->count(), 'default days are 30 and 7');
        $this->assertSame([30, 7], $jenis->fresh()->hari_peringatan);

        $this->actingAs($admin)->putJson("/api/v1/ntf/jenis/{$jenis->id}", ['hari_peringatan' => [14, 60]])->assertOk();
        $this->actingAs($admin)->postJson("/api/v1/ntf/jenis/{$jenis->id}/templat", [
            'subjek' => 'PERINGATAN: lot {{no_lot}} — baki {{baki_hari}} hari',
            'kandungan' => 'Tarikh tamat {{tarikh_tamat}}.',
        ])->assertCreated();

        $this->artisan('ntf:peringatan', ['--tarikh' => '2026-11-01']);
        $this->assertSame(['PERINGATAN: lot PT 14 — baki 14 hari'], Mesej::query()->pluck('subjek')->all());
        $this->assertSame('Tarikh tamat 15/11/2026.', Mesej::query()->value('kandungan'));
    }

    public function test_SRS_NTF_18_unread_kritikal_escalates_to_supervisor_once_and_is_logged(): void
    {
        $jenis = $this->registerType('kes.prosiding', NotificationTypes::KRITIKAL, ['no_kes']);
        $jenis->update(['tempoh_eskalasi_minit' => 60]);
        $this->registerType('kes.biasa', NotificationTypes::BIASA, ['no_kes']);
        $supervisor = $this->admin();
        $unread = $this->officer();
        $reader = $this->officer();

        app(Notifier::class)->send('kes.prosiding', [$unread, $reader], ['no_kes' => 'BA-1'], '/admin/kes/1');
        app(Notifier::class)->send('kes.biasa', $unread, ['no_kes' => 'BA-2']);
        $readItem = StatusPengguna::query()->where('pengguna_id', $reader->id)->firstOrFail();
        $this->actingAs($reader)->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'baca', 'ids' => [$readItem->id]])->assertOk();

        $this->travel(30)->minutes();
        $this->artisan('ntf:eskalasi')->assertSuccessful();
        $this->assertSame(0, EskalasiLog::query()->count(), 'not yet past the period');

        $this->travel(31)->minutes();
        $this->artisan('ntf:eskalasi')->assertSuccessful();
        $this->artisan('ntf:eskalasi')->assertSuccessful();

        $log = EskalasiLog::query()->sole();
        $this->assertSame($unread->id, $log->penerima_asal_id);
        $this->assertSame($supervisor->id, $log->penyelia_id);
        $escalation = Mesej::query()->findOrFail($log->mesej_eskalasi_id);
        $this->assertSame('ntf.eskalasi', $escalation->jenis->kod);
        $this->assertSame('/admin/kes/1', $escalation->pautan);
        $this->assertTrue(StatusPengguna::query()->where('pengguna_id', $supervisor->id)->where('mesej_id', $escalation->id)->exists());
        $this->assertNotNull(StatusPengguna::query()->where('pengguna_id', $unread->id)->whereHas('mesej', fn ($q) => $q->where('keutamaan', 'kritikal'))->value('dieskalasi_pada'));
        $this->assertCount(1, $this->audit->where('eskalasi'));
    }

    public function test_SRS_NTF_18_supervisor_resolution_is_a_replaceable_contract(): void
    {
        $this->registerType('kes.prosiding', NotificationTypes::KRITIKAL, ['no_kes'])->update(['tempoh_eskalasi_minit' => 1]);
        $boss = $this->officer();
        $user = $this->officer();
        $this->app->instance(SupervisorResolver::class, new class($boss) implements SupervisorResolver
        {
            public function __construct(private User $boss) {}

            public function supervisorsOf(User $user): array
            {
                return [$this->boss];
            }
        });

        app(Notifier::class)->send('kes.prosiding', $user, ['no_kes' => 'X']);
        $this->travel(2)->minutes();
        $this->artisan('ntf:eskalasi');

        $this->assertSame($boss->id, EskalasiLog::query()->value('penyelia_id'));
    }

    public function test_SRS_NTF_18_fallback_supervisor_role_is_an_adm_parameter(): void
    {
        $this->registerType('kes.prosiding', NotificationTypes::KRITIKAL, ['no_kes'])->update(['tempoh_eskalasi_minit' => 1]);
        $this->admin();
        $ketua = $this->officer();
        $this->access->assignRoles($ketua, ['ketua_unit']);
        $this->app->instance(Parameters::class, new class implements Parameters
        {
            public function declare(string $key, string $type, mixed $default, string $label, bool $critical = false): void {}

            public function get(string $key, mixed $default = null): mixed
            {
                return $key === 'ntf.peranan_penyelia' ? 'ketua_unit' : $default;
            }
        });
        $user = $this->officer();

        app(Notifier::class)->send('kes.prosiding', $user, ['no_kes' => 'X']);
        $this->travel(2)->minutes();
        $this->artisan('ntf:eskalasi');

        $this->assertSame([$ketua->id], EskalasiLog::query()->pluck('penyelia_id')->all());
    }

    public function test_SRS_NTF_20_biasa_and_maklumat_bundled_into_one_digest(): void
    {
        $biasa = $this->registerType('pjk.biasa', NotificationTypes::BIASA, ['no_lot']);
        $maklumat = $this->registerType('pjk.maklumat', NotificationTypes::MAKLUMAT, ['no_lot']);
        $maklumat->update(['saluran_lalai' => ['dalam_sistem', 'emel']]);
        $user = $this->officer();
        foreach ([$biasa, $maklumat] as $jenis) {
            KeutamaanPengguna::query()->create(['pengguna_id' => $user->id, 'jenis_id' => $jenis->id, 'aktif' => true, 'saluran' => ['dalam_sistem', 'emel'], 'ringkasan' => true]);
        }

        app(Notifier::class)->send('pjk.biasa', $user, ['no_lot' => 'A']);
        app(Notifier::class)->send('pjk.maklumat', $user, ['no_lot' => 'B']);

        Mail::assertNothingSent();
        $this->assertSame(2, StatusPengguna::query()->count(), 'in-app is still immediate');
        $this->assertSame(2, Penghantaran::query()->where('status', Penghantaran::DITAHAN)->count());

        $this->artisan('ntf:ringkasan')->assertSuccessful();

        Mail::assertSent(RingkasanMail::class, 1);
        Mail::assertSent(RingkasanMail::class, fn (RingkasanMail $m) => $m->hasTo($user->email) && $m->mesej->count() === 2);
        $this->assertSame(2, Penghantaran::query()->where('saluran', 'emel')->where('status', Penghantaran::DITERIMA)->whereNotNull('tarikh_terima')->count());

        $this->artisan('ntf:ringkasan');
        Mail::assertSent(RingkasanMail::class, 1);
    }

    public function test_SRS_NTF_20_kritikal_never_waits_for_a_digest(): void
    {
        $jenis = $this->registerType('kes.kritikal', NotificationTypes::KRITIKAL, ['no_kes']);
        $user = $this->officer();
        KeutamaanPengguna::query()->create(['pengguna_id' => $user->id, 'jenis_id' => $jenis->id, 'aktif' => true, 'saluran' => ['emel'], 'ringkasan' => true]);

        app(Notifier::class)->send('kes.kritikal', $user, ['no_kes' => 'X']);

        Mail::assertSent(NotifikasiMail::class);
        $this->actingAs($user)->putJson('/api/v1/ntf/keutamaan', ['items' => [['jenis_id' => $jenis->id, 'aktif' => true, 'saluran' => ['emel'], 'ringkasan' => true]]])
            ->assertUnprocessable();
    }

    public function test_SRS_NTF_06_scheduler_registers_the_ntf_jobs(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('ntf:peringatan')
            ->expectsOutputToContain('ntf:eskalasi')
            ->expectsOutputToContain('ntf:ringkasan')
            ->expectsOutputToContain('ntf:proses-tertunggak')
            ->assertSuccessful();
    }
}
