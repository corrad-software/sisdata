<?php

namespace Tests\Modules\Ntf;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Jobs\DeliverNotification;
use App\Modules\Ntf\Models\Mesej;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Services\Channels\EmailChannel;
use App\Modules\Ntf\Services\DeliveryService;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;

/** Kriteria penerimaan 6: e-mail gateway down → ≥3 automatic retries → final failure + admin alert. */
class DeliveryRetryTest extends NtfTestCase
{
    private function breakEmailGateway(): void
    {
        $this->app->instance(EmailChannel::class, new class extends EmailChannel
        {
            public function deliver(Mesej $mesej, User $to): bool
            {
                throw new TransportException('Connection could not be established with host "smtp.mais.gov.my:587"');
            }
        });
    }

    public function test_SRS_NTF_17_job_retries_at_least_three_times_with_increasing_backoff(): void
    {
        $job = new DeliverNotification(1);

        $this->assertGreaterThanOrEqual(4, $job->tries, 'first attempt + at least 3 retries');
        $backoff = $job->backoff();
        $this->assertGreaterThanOrEqual(3, count($backoff));
        $sorted = $backoff;
        sort($sorted);
        $this->assertSame($sorted, $backoff);
        $this->assertCount(count($backoff), array_unique($backoff), 'strictly increasing');
    }

    public function test_SRS_NTF_17_failed_email_is_retried_then_marked_final_and_admin_alerted(): void
    {
        Queue::fake();
        $this->breakEmailGateway();
        $admin = $this->admin();
        $this->registerType();
        $user = $this->officer();

        app(Notifier::class)->send('pjk.pajakan_tamat', $user, ['no_lot' => 'PT 1']);
        $delivery = Penghantaran::query()->where('saluran', 'emel')->firstOrFail();
        $service = app(DeliveryService::class);

        foreach ([1, 2, 3] as $attempt) {
            try {
                $service->attempt($delivery->id, $attempt, 4);
                $this->fail('A failing attempt must throw so the queue retries it');
            } catch (TransportException) {
            }
            $delivery->refresh();
            $this->assertSame(Penghantaran::GAGAL, $delivery->status);
            $this->assertSame($attempt, $delivery->bilangan_cubaan);
            $this->assertSame('SMTP_TIDAK_TERSEDIA', $delivery->kod_ralat);
        }

        $service->attempt($delivery->id, 4, 4);
        $delivery->refresh();
        $this->assertSame(Penghantaran::GAGAL_MUKTAMAD, $delivery->status);
        $this->assertSame(4, $delivery->bilangan_cubaan);
        $this->assertNotNull($delivery->tarikh_gagal);

        // Pentadbir Sistem (holder of ntf.saluran.kemas_kini) is alerted in-app.
        $alert = Mesej::query()->whereHas('jenis', fn ($q) => $q->where('kod', 'ntf.penghantaran_gagal'))->firstOrFail();
        $this->assertStringContainsString('SMTP_TIDAK_TERSEDIA', $alert->kandungan);
        $this->assertSame(['dalam_sistem'], $alert->penghantaran()->pluck('saluran')->unique()->values()->all());
        $this->assertSame([$admin->id], $alert->penghantaran()->pluck('penerima_id')->all());

        // Every attempt and the final failure are in the audit trail with the error code.
        $failed = array_filter($this->audit->where('hantar_notifikasi'), fn ($r) => $r['status'] === 'gagal' && $r['meta']['saluran'] === 'emel');
        $this->assertCount(5, $failed);
        $this->assertSame('SMTP_TIDAK_TERSEDIA', end($failed)['reason']);
    }

    public function test_SRS_NTF_17_queue_failed_hook_marks_final(): void
    {
        Queue::fake();
        $this->admin();
        $this->registerType();
        app(Notifier::class)->send('pjk.pajakan_tamat', $this->officer(), ['no_lot' => 'PT 1']);
        $delivery = Penghantaran::query()->where('saluran', 'emel')->firstOrFail();

        (new DeliverNotification($delivery->id))->failed(new TransportException('down'));

        $this->assertSame(Penghantaran::GAGAL_MUKTAMAD, $delivery->fresh()->status);
        $this->assertSame('SMTP_TIDAK_TERSEDIA', $delivery->fresh()->kod_ralat);
    }

    public function test_SRS_NTF_17_alert_failures_never_alert_again(): void
    {
        Queue::fake();
        $this->admin();
        app(Notifier::class)->send('ntf.penghantaran_gagal', [], ['kod_ralat' => 'X']);
        $alertDelivery = Penghantaran::query()->firstOrFail();

        app(DeliveryService::class)->finalFailure($alertDelivery->id);

        $this->assertSame(1, Mesej::query()->count());
    }

    public function test_SRS_NTF_23_admin_can_retry_a_failed_delivery(): void
    {
        $this->breakEmailGateway();
        $admin = $this->admin();
        $this->registerType();
        app(Notifier::class)->send('pjk.pajakan_tamat', $this->officer(), ['no_lot' => 'PT 1']);
        $delivery = Penghantaran::query()->where('saluran', 'emel')->firstOrFail();
        $this->assertSame(Penghantaran::GAGAL_MUKTAMAD, $delivery->status, 'sync queue: failed() runs at once');

        $this->app->forgetInstance(EmailChannel::class);
        $this->actingAs($admin)->postJson("/api/v1/ntf/penghantaran/{$delivery->id}/cuba-semula")->assertOk();
        $this->assertSame(Penghantaran::DITERIMA, $delivery->fresh()->status);

        $viewer = $this->officer();
        $this->access->grant($viewer, ['ntf.pemantauan.lihat']);
        $this->actingAs($viewer)->postJson("/api/v1/ntf/penghantaran/{$delivery->id}/cuba-semula")->assertForbidden();
    }
}
