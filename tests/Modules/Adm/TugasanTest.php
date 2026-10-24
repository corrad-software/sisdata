<?php

namespace Tests\Modules\Adm;

use App\Modules\Adm\Models\Tugasan;
use App\Modules\Adm\Models\TugasanLarian;
use App\Modules\Adm\Services\Tugasan\PelaksanaTugasan;
use App\Modules\Adm\Services\Tugasan\PenjadualTugasan;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\Modules\Adm\Fakes\UjianBerjayaCommand;

class TugasanTest extends AdmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $kernel = $this->app->make(Kernel::class);
        $kernel->registerCommand($this->app->make(UjianBerjayaCommand::class));
        $kernel->registerCommand(new ClosureCommand('adm:ujian-gagal', function () {
            throw new RuntimeException('Sambungan JUPEM gagal password=rahsia123');
        }));
        $kernel->registerCommand(new ClosureCommand('adm:ujian-kod', fn () => 3));
    }

    private function job(array $override = []): Tugasan
    {
        return Tugasan::query()->create([
            'kod' => 'ujian_'.uniqid(), 'nama' => 'Ujian', 'kategori' => 'pengemaskinian_data', 'arahan' => 'adm:ujian-berjaya',
            'jenis_jadual' => 'cron', 'ungkapan_cron' => '0 * * * *', 'aktif' => true, 'cubaan_semula' => 0, 'sumber' => 'adm',
            ...$override,
        ]);
    }

    public function test_SRS_ADM_06_admin_defines_a_cron_job_and_it_is_registered_in_the_scheduler(): void
    {
        $res = $this->actingAs($this->admin())->postJson('/api/v1/adm/tugasan', [
            'kod' => 'kemas_kini_lot', 'nama' => 'Kemas kini lot', 'kategori' => 'pengemaskinian_data',
            'arahan' => 'adm:ujian-berjaya', 'jenis_jadual' => 'cron', 'ungkapan_cron' => '15 1 * * *', 'cubaan_semula' => 2,
        ])->assertCreated();
        $this->assertSame('adm', $res->json('data.sumber'));

        $schedule = new Schedule;
        $this->app->make(PenjadualTugasan::class)->register($schedule);
        $event = collect($schedule->events())->first(fn ($e) => $e->description === 'adm:kemas_kini_lot');
        $this->assertNotNull($event);
        $this->assertSame('15 1 * * *', $event->expression);
    }

    public function test_SRS_ADM_06_fixed_interval_jobs_and_disabled_jobs(): void
    {
        $this->actingAs($this->admin())->postJson('/api/v1/adm/tugasan', [
            'kod' => 'selang', 'nama' => 'Selang', 'kategori' => 'notifikasi', 'arahan' => 'adm:ujian-berjaya', 'jenis_jadual' => 'selang', 'selang_minit' => 30,
        ])->assertCreated();
        $off = $this->job(['aktif' => false]);

        $schedule = new Schedule;
        $this->app->make(PenjadualTugasan::class)->register($schedule);
        $names = collect($schedule->events())->pluck('description');
        $this->assertContains('adm:selang', $names);
        $this->assertNotContains('adm:'.$off->kod, $names);

        $job = Tugasan::query()->where('kod', 'selang')->first();
        $this->assertTrue($job->dueByInterval());
        $job->terakhir_mula_pada = now()->subMinutes(10);
        $this->assertFalse($job->dueByInterval());
        $job->terakhir_mula_pada = now()->subMinutes(31);
        $this->assertTrue($job->dueByInterval());
    }

    public function test_SRS_ADM_06_invalid_cron_and_unsafe_commands_are_refused(): void
    {
        $admin = $this->admin();
        $base = ['kod' => 'x', 'nama' => 'X', 'kategori' => 'laporan', 'jenis_jadual' => 'cron'];

        $this->actingAs($admin)->postJson('/api/v1/adm/tugasan', [...$base, 'arahan' => 'adm:ujian-berjaya', 'ungkapan_cron' => 'setiap hari'])
            ->assertStatus(422)->assertJsonValidationErrors('ungkapan_cron');
        foreach (['db:wipe', 'migrate:fresh', 'key:generate', 'adm:secret', 'tiada:arahan'] as $command) {
            $this->actingAs($admin)->postJson('/api/v1/adm/tugasan', [...$base, 'arahan' => $command, 'ungkapan_cron' => '* * * * *'])
                ->assertStatus(422)->assertJsonValidationErrors('arahan');
        }
    }

    public function test_SRS_ADM_06_run_now_is_queued_and_recorded(): void
    {
        $job = $this->job();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/v1/adm/tugasan/{$job->id}/jalankan")->assertStatus(202);

        // Queue is sync in tests: the queued row has been executed.
        $run = TugasanLarian::query()->where('id_tugasan', $job->id)->sole();
        $this->assertSame('manual', $run->pencetus);
        $this->assertSame($admin->id, $run->id_pengguna);
        $this->assertSame(TugasanLarian::BERJAYA, $run->status);
    }

    public function test_SRS_ADM_06_enable_disable_and_permissions(): void
    {
        $job = $this->job();
        $this->actingAs($this->admin())->putJson("/api/v1/adm/tugasan/{$job->id}", ['aktif' => false])->assertOk()->assertJsonPath('data.aktif', false);

        $viewer = $this->userWith(['adm.tugasan.lihat']);
        $this->actingAs($viewer)->getJson('/api/v1/adm/tugasan')->assertOk();
        $this->actingAs($viewer)->postJson("/api/v1/adm/tugasan/{$job->id}/jalankan")->assertForbidden();
        $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/tugasan')->assertForbidden();
    }

    public function test_SRS_ADM_06_other_modules_scheduled_jobs_are_listed_and_adoptable(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $schedule->command('adm:ujian-kod')->dailyAt('03:00')->description('Arkib dokumen DMS');

        $list = $this->actingAs($this->admin())->getJson('/api/v1/adm/tugasan/sistem')->assertOk()->json('data');
        $item = collect($list)->firstWhere('perihal', 'Arkib dokumen DMS');
        $this->assertSame('0 3 * * *', $item['ungkapan']);
        $this->assertNull($item['id_tugasan']);

        $adopted = $this->actingAs($this->admin())->postJson('/api/v1/adm/tugasan/sistem/ambil', ['kunci' => $item['kunci'], 'kategori' => 'pengarkiban'])
            ->assertCreated()->json('data');
        $this->assertSame('sistem', $adopted['sumber']);
        $this->assertSame('adm:ujian-kod', $adopted['arahan']);

        // Adopted: ADM records its runs from the scheduler's own events…
        $event = collect($schedule->events())->first(fn ($e) => $e->description === 'Arkib dokumen DMS');
        $scheduler = $this->app->make(PenjadualTugasan::class);
        $scheduler->onStarting(new ScheduledTaskStarting($event));
        $event->exitCode = 0;
        $scheduler->onFinished(new ScheduledTaskFinished($event, 0.5));
        $this->assertSame(TugasanLarian::BERJAYA, TugasanLarian::query()->where('id_tugasan', $adopted['id'])->sole()->status);

        // …and can switch it off.
        $this->actingAs($this->admin())->putJson("/api/v1/adm/tugasan/{$adopted['id']}", ['aktif' => false])->assertOk();
        $scheduler->decorateForeign($schedule);
        $this->assertFalse($event->filtersPass($this->app));
    }

    public function test_SRS_ADM_07_job_categories_cover_the_required_kinds(): void
    {
        $res = $this->actingAs($this->admin())->getJson('/api/v1/adm/tugasan')->assertOk();
        foreach (['pengemaskinian_data', 'notifikasi', 'laporan', 'pengarkiban', 'integrasi'] as $category) {
            $this->assertContains($category, $res->json('kategori'));
        }
        $this->assertContains('adm:pangkas-log', $res->json('arahan'));

        $this->actingAs($this->admin())->postJson('/api/v1/adm/tugasan', [
            'kod' => 'x', 'nama' => 'X', 'kategori' => 'lain_lain', 'arahan' => 'adm:ujian-berjaya', 'jenis_jadual' => 'cron', 'ungkapan_cron' => '* * * * *',
        ])->assertStatus(422)->assertJsonValidationErrors('kategori');
    }

    public function test_SRS_ADM_08_run_history_has_start_end_duration_status_records_and_error(): void
    {
        $ok = $this->job();
        $bad = $this->job(['arahan' => 'adm:ujian-gagal']);
        $runner = $this->app->make(PelaksanaTugasan::class);

        $run = $runner->run($ok->id);
        $this->assertSame(TugasanLarian::BERJAYA, $run->status);
        $this->assertNotNull($run->tarikh_mula);
        $this->assertNotNull($run->tarikh_tamat);
        $this->assertIsFloat($run->tempoh_saat);
        $this->assertSame(42, $run->bilangan_rekod);
        $this->assertStringContainsString('siap', (string) $run->output);

        $failed = $runner->run($bad->id);
        $this->assertSame(TugasanLarian::GAGAL, $failed->status);
        $this->assertStringContainsString('Sambungan JUPEM gagal', (string) $failed->mesej_ralat);
        $this->assertStringNotContainsString('rahsia123', (string) $failed->mesej_ralat); // SRS-ADM-15

        $history = $this->actingAs($this->admin())->getJson("/api/v1/adm/tugasan/{$ok->id}/larian")->assertOk();
        $this->assertSame(1, $history->json('total'));
        foreach (['tarikh_mula', 'tarikh_tamat', 'tempoh_saat', 'status', 'bilangan_rekod', 'mesej_ralat'] as $field) {
            $this->assertArrayHasKey($field, $history->json('data.0'));
        }
        $this->assertSame('berjaya', Tugasan::query()->find($ok->id)->terakhir_status);
    }

    public function test_SRS_ADM_09_the_same_job_never_runs_concurrently(): void
    {
        $job = $this->job();
        $lock = Cache::lock(PelaksanaTugasan::lockKey($job->id), 60);
        $this->assertTrue($lock->get());

        $run = $this->app->make(PelaksanaTugasan::class)->run($job->id);
        $this->assertSame(TugasanLarian::DILANGKAU, $run->status);
        $this->assertSame(1, TugasanLarian::query()->where('id_tugasan', $job->id)->count());

        $lock->release();
        $this->assertSame(TugasanLarian::BERJAYA, $this->app->make(PelaksanaTugasan::class)->run($job->id)->status);
    }

    public function test_SRS_ADM_09_failed_jobs_are_retried_as_configured(): void
    {
        $job = $this->job(['arahan' => 'adm:ujian-kod', 'cubaan_semula' => 2]);

        $this->app->make(PelaksanaTugasan::class)->run($job->id);

        $runs = TugasanLarian::query()->where('id_tugasan', $job->id)->orderBy('id')->get();
        $this->assertSame([1, 2, 3], $runs->pluck('cubaan')->all());
        $this->assertSame(['gagal', 'gagal', 'gagal'], $runs->pluck('status')->all());
    }

    public function test_SRS_ADM_10_final_failure_notifies_system_administrators(): void
    {
        $ict = $this->userWith(['adm.amaran.lihat']);
        $other = $this->userWith(['adm.tugasan.lihat']);
        $job = $this->job(['arahan' => 'adm:ujian-gagal', 'cubaan_semula' => 1]);

        $this->app->make(PelaksanaTugasan::class)->run($job->id);

        $sent = $this->notifier->ofType('adm.tugasan_gagal');
        $this->assertCount(1, $sent); // once, after the retries
        $this->assertSame([$ict->id], $sent[0]['recipients']);
        $this->assertNotContains($other->id, $sent[0]['recipients']);
        $this->assertSame($job->nama, $sent[0]['data']['tugasan']);
    }

    public function test_SRS_ADM_10_a_missed_schedule_alerts_and_stuck_runs_are_closed(): void
    {
        $this->userWith(['adm.amaran.lihat']);
        $this->travelTo(now()->setTime(10, 0));
        $job = $this->job(['ungkapan_cron' => '0 * * * *', 'toleransi_lewat_minit' => 10]);
        $job->forceFill(['created_at' => now()->subDay()])->saveQuietly();
        $stuck = TugasanLarian::query()->create(['id_tugasan' => $job->id, 'status' => 'berjalan', 'tarikh_mula' => now()->subHours(5)]);

        $this->travelTo(now()->setTime(10, 30));
        $this->artisan('adm:semak-tugasan')->assertSuccessful();

        $this->assertCount(1, $this->notifier->ofType('adm.tugasan_terlepas'));
        $this->assertSame('gagal', $stuck->fresh()->status);

        // A job that ran on time is not reported.
        $this->notifier->sent = [];
        TugasanLarian::query()->create(['id_tugasan' => $job->id, 'status' => 'berjaya', 'tarikh_mula' => now()->setTime(10, 0, 5)]);
        $this->artisan('adm:semak-tugasan')->assertSuccessful();
        $this->assertCount(0, $this->notifier->ofType('adm.tugasan_terlepas'));
    }

    public function test_SRS_ADM_10_failure_of_an_adopted_framework_job_alerts(): void
    {
        $this->userWith(['adm.amaran.lihat']);
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('adm:ujian-kod')->hourly();
        Tugasan::query()->create([
            'kod' => 'sistem_x', 'nama' => 'Sistem X', 'kategori' => 'integrasi', 'arahan' => 'adm:ujian-kod', 'jenis_jadual' => 'cron',
            'ungkapan_cron' => '0 * * * *', 'aktif' => true, 'sumber' => 'sistem', 'kunci_sistem' => $event->mutexName(),
        ]);

        $scheduler = $this->app->make(PenjadualTugasan::class);
        $scheduler->onStarting(new ScheduledTaskStarting($event));
        $scheduler->onFailed(new ScheduledTaskFailed($event, new RuntimeException('boom')));

        $this->assertCount(1, $this->notifier->ofType('adm.tugasan_gagal'));
    }
}
