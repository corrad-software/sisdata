<?php

namespace Tests\Modules\Ntf;

use App\Models\User;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Models\Jenis;
use App\Modules\Ntf\Models\Mesej;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Models\StatusPengguna;
use App\Modules\Ntf\Services\Channels\EmailChannel;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use Symfony\Component\Mailer\Exception\TransportException;

class AdminApiTest extends NtfTestCase
{
    public function test_SRS_NTF_03_permissions_are_declared_to_rbc(): void
    {
        $catalogue = $this->app->call(fn (PermissionRegistry $registry) => $registry->all());

        $this->assertArrayHasKey('ntf', $catalogue);
        $this->assertArrayHasKey('ntf.jenis.kemas_kini', $catalogue['ntf']['permissions']);
        $this->assertArrayHasKey('ntf.pengumuman.cipta', $catalogue['ntf']['permissions']);
    }

    public function test_SRS_NTF_03_admin_configures_channels_priority_frequency_and_recipients_without_code(): void
    {
        $jenis = $this->registerType('pjk.pajakan_tamat', NotificationTypes::BIASA, ['no_lot']);
        $admin = $this->admin();
        $daerahOfficer = $this->officer();
        $this->access->scopeTo($daerahOfficer, 'daerah', ['10']);

        $this->actingAs($admin)->getJson('/api/v1/ntf/jenis')->assertOk()
            ->assertJsonFragment(['kod' => 'pjk.pajakan_tamat', 'versi_templat' => 1]);

        $this->actingAs($admin)->putJson("/api/v1/ntf/jenis/{$jenis->id}", [
            'keutamaan' => 'tinggi',
            'saluran_lalai' => ['dalam_sistem'],
            'tempoh_pendiaman_minit' => 0,
            'wajib' => true,
        ])->assertOk()->assertJsonPath('data.keutamaan', 'tinggi');
        $this->actingAs($admin)->postJson("/api/v1/ntf/jenis/{$jenis->id}/peraturan", ['sasaran' => 'daerah', 'nilai' => '10'])->assertCreated();
        $this->actingAs($admin)->postJson("/api/v1/ntf/jenis/{$jenis->id}/peraturan", ['sasaran' => 'daerah', 'nilai' => '10'])->assertUnprocessable();

        app(Notifier::class)->send('pjk.pajakan_tamat', [], ['no_lot' => 'PT 1']);

        $mesej = Mesej::query()->sole();
        $this->assertSame('tinggi', $mesej->keutamaan);
        $this->assertSame([$daerahOfficer->id], $mesej->penghantaran()->pluck('penerima_id')->all());
        $this->assertSame(['dalam_sistem'], $mesej->penghantaran()->pluck('saluran')->all());

        // A digest frequency is only valid for Biasa/Maklumat.
        $this->actingAs($admin)->putJson("/api/v1/ntf/jenis/{$jenis->id}", ['kekerapan' => 'ringkasan'])->assertUnprocessable();
    }

    public function test_SRS_NTF_05_template_edit_creates_a_new_active_version_used_by_next_notification(): void
    {
        $jenis = $this->registerType('pjk.pajakan_tamat', NotificationTypes::TINGGI, ['no_lot', 'nama_penyewa']);
        $admin = $this->admin();
        $user = $this->officer();

        $preview = $this->actingAs($admin)->postJson("/api/v1/ntf/jenis/{$jenis->id}/templat/pratonton", [
            'subjek' => 'Lot {{no_lot}}', 'kandungan' => 'Penyewa {{nama_penyewa}} {{tiada}}', 'data' => ['no_lot' => 'PT 5'],
        ])->assertOk()->json('data');
        $this->assertSame('Lot PT 5', $preview['subjek']);
        $this->assertSame('Penyewa [nama_penyewa] —', $preview['kandungan']);
        $this->assertSame(['tiada'], $preview['medan_tidak_dikenali']);

        $this->actingAs($admin)->postJson("/api/v1/ntf/jenis/{$jenis->id}/templat", ['subjek' => 'X {{tiada}}', 'kandungan' => 'Y'])->assertUnprocessable();
        $this->actingAs($admin)->postJson("/api/v1/ntf/jenis/{$jenis->id}/templat", ['subjek' => 'X', 'kandungan' => 'No KP {{no_kp}}'])
            ->assertUnprocessable()->assertJsonValidationErrors('kandungan');

        $this->actingAs($admin)->postJson("/api/v1/ntf/jenis/{$jenis->id}/templat", [
            'subjek' => 'Notis: lot {{no_lot}}', 'kandungan' => 'Kepada {{nama_penyewa}}, tarikh {{tarikh}}.', 'catatan' => 'Semakan 2026',
        ])->assertCreated()->assertJsonPath('data.versi', 2);

        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 9', 'nama_penyewa' => 'Ali']);
        $this->assertSame('Notis: lot PT 9', Mesej::query()->latest('id')->value('subjek'));

        $show = $this->actingAs($admin)->getJson("/api/v1/ntf/jenis/{$jenis->id}")->assertOk()->json('data');
        $this->assertSame([2, 1], array_column($show['templat'], 'versi'));
        $this->assertSame([true, false], array_column($show['templat'], 'aktif'));

        // Roll back to version 1.
        $v1 = $show['templat'][1]['id'];
        $this->actingAs($admin)->postJson("/api/v1/ntf/jenis/{$jenis->id}/templat/{$v1}/aktif")->assertOk();
        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 10', 'nama_penyewa' => 'Ali']);
        $this->assertStringStartsWith('Pajakan lot PT 10', Mesej::query()->latest('id')->value('subjek'));
    }

    public function test_SRS_NTF_03_configuration_requires_ntf_permissions(): void
    {
        $jenis = $this->registerType();
        $plain = $this->officer();
        $viewer = $this->officer();
        $this->access->grant($viewer, ['ntf.jenis.lihat']);

        foreach (['/api/v1/ntf/jenis', "/api/v1/ntf/jenis/{$jenis->id}", '/api/v1/ntf/pemantauan', '/api/v1/ntf/penghantaran', '/api/v1/ntf/pengumuman/kumpulan'] as $url) {
            $this->actingAs($plain)->getJson($url)->assertForbidden();
        }
        $this->actingAs($plain)->postJson('/api/v1/ntf/pengumuman', ['tajuk' => 'x', 'kandungan' => 'y', 'sasaran' => [['jenis' => 'peranan', 'nilai' => 'admin']]])->assertForbidden();

        $this->actingAs($viewer)->getJson("/api/v1/ntf/jenis/{$jenis->id}")->assertOk();
        $this->actingAs($viewer)->putJson("/api/v1/ntf/jenis/{$jenis->id}", ['nama' => 'Ubah'])->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/v1/ntf/jenis/{$jenis->id}/templat", ['subjek' => 'a', 'kandungan' => 'b'])->assertForbidden();
        $this->assertSame('Pajakan hampir tamat', $jenis->fresh()->nama);

        $this->actingAs($viewer)->getJson('/api/v1/ntf/kebenaran')->assertOk()
            ->assertJsonPath('data', ['ntf.jenis.lihat' => true, 'ntf.jenis.kemas_kini' => false, 'ntf.pemantauan.lihat' => false, 'ntf.saluran.kemas_kini' => false, 'ntf.pengumuman.cipta' => false]);
    }

    public function test_SRS_NTF_23_monitoring_dashboard_shows_queue_success_rate_and_recent_failures(): void
    {
        $admin = $this->admin();
        $this->registerType();
        $user = $this->officer();
        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'OK']);

        $this->app->instance(EmailChannel::class, new class extends EmailChannel
        {
            public function deliver(Mesej $mesej, User $to): bool
            {
                throw new TransportException('down');
            }
        });
        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'GAGAL']);

        $data = $this->actingAs($admin)->getJson('/api/v1/ntf/pemantauan')->assertOk()->json('data');

        $email = collect($data['saluran'])->firstWhere('saluran', 'emel');
        $this->assertSame(1, $email['berjaya']);
        $this->assertSame(1, $email['gagal_muktamad']);
        $this->assertEquals(50.0, $email['kadar_kejayaan']);
        $inApp = collect($data['saluran'])->firstWhere('saluran', 'dalam_sistem');
        $this->assertEquals(100.0, $inApp['kadar_kejayaan']);
        $this->assertArrayHasKey('beratur', $data['baris_gilir']);
        $failure = collect($data['kegagalan_terkini'])->firstWhere('saluran', 'emel');
        $this->assertSame('SMTP_TIDAK_TERSEDIA', $failure['kod_ralat']);
        $this->assertSame($user->id, $failure['penerima']['id']);
    }

    public function test_SRS_NTF_15_delivery_log_for_auditors_shows_channel_times_status_and_error(): void
    {
        // Kriteria penerimaan 5.
        $this->registerType();
        $auditor = $this->officer();
        $this->access->grant($auditor, ['ntf.pemantauan.lihat']);
        $user = $this->officer();
        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 1']);
        $item = StatusPengguna::query()->sole();
        $this->actingAs($user)->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'baca', 'ids' => [$item->id]]);

        $rows = $this->actingAs($auditor)->getJson('/api/v1/ntf/penghantaran?jenis=pjk.pajakan_tamat')->assertOk()->json('data');

        $this->assertCount(2, $rows);
        $inApp = collect($rows)->firstWhere('saluran', 'dalam_sistem');
        $this->assertSame('dibaca', $inApp['status']);
        foreach (['tarikh_hantar', 'tarikh_terima', 'tarikh_baca'] as $field) {
            $this->assertNotNull($inApp[$field], $field);
        }
        $this->assertArrayHasKey('kod_ralat', $inApp);
        $this->actingAs($auditor)->getJson('/api/v1/ntf/penghantaran?saluran=emel&status=diterima')->assertJsonCount(1, 'data');

        // Users can't alter the log: there is no endpoint, and deleting from the inbox leaves it.
        $this->actingAs($user)->postJson('/api/v1/ntf/notifikasi/tindakan', ['tindakan' => 'padam', 'ids' => [$item->id]]);
        $this->assertSame(2, Penghantaran::query()->count());
    }

    public function test_SRS_NTF_08_type_priorities_are_validated(): void
    {
        $jenis = $this->registerType();
        $this->actingAs($this->admin())->putJson("/api/v1/ntf/jenis/{$jenis->id}", ['keutamaan' => 'segera'])->assertUnprocessable();
        $this->assertSame(Jenis::KEUTAMAAN, ['kritikal', 'tinggi', 'biasa', 'maklumat']);
    }
}
