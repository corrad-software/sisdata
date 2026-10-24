<?php

namespace Tests\Modules\Wfe;

use App\Models\User;
use App\Modules\Wfe\Database\Seeders\CutiUmumSelangorSeeder;
use App\Modules\Wfe\Events\TaskEscalated;
use App\Modules\Wfe\Models\CutiUmum;
use App\Modules\Wfe\Models\EskalasiLog;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\WorkingCalendar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

class SlaTest extends WfeTestCase
{
    private function local(string $at): Carbon
    {
        return Carbon::parse($at, WorkingCalendar::TZ);
    }

    private function calendar(): WorkingCalendar
    {
        $calendar = app(WorkingCalendar::class);
        $calendar->flush();

        return $calendar;
    }

    public function test_SRS_WFE_06_working_days_skip_selangor_weekend_and_public_holidays(): void
    {
        $this->seed(CutiUmumSelangorSeeder::class);
        $cal = $this->calendar();

        // Thu 10 Dec 2026 + 1 working day: Fri 11 Dec = Sultan Selangor's birthday, then weekend → Mon 14 Dec
        $this->assertSame('2026-12-14 17:00', $cal->addWorkingDays($this->local('2026-12-10 10:00'), 1)->setTimezone(WorkingCalendar::TZ)->format('Y-m-d H:i'));
        // Fri 6 Nov 2026 + 2: Sun 8 Nov Deepavali, Mon 9 Nov cuti ganti → Tue 10, Wed 11
        $this->assertSame('2026-11-11 17:00', $cal->addWorkingDays($this->local('2026-11-06 12:00'), 2)->setTimezone(WorkingCalendar::TZ)->format('Y-m-d H:i'));
        $this->assertFalse($cal->isWorkingDay($this->local('2026-12-12 10:00'))); // Saturday
        $this->assertFalse($cal->isWorkingDay($this->local('2027-01-22 10:00'))); // Thaipusam 2027
        $this->assertTrue($cal->isWorkingDay($this->local('2027-01-06 10:00'))); // Israk Mikraj is not a Selangor holiday
    }

    public function test_SRS_WFE_06_working_hours_sla_counts_office_hours_only(): void
    {
        $this->seed(CutiUmumSelangorSeeder::class);
        $cal = $this->calendar();

        // Fri 4 Dec 2026 15:00 + 4 working hours: 2h Friday, weekend skipped, 2h Monday → Mon 10:00
        $this->assertSame('2026-12-07 10:00', $cal->addWorkingHours($this->local('2026-12-04 15:00'), 4)->setTimezone(WorkingCalendar::TZ)->format('Y-m-d H:i'));
        // starting outside office hours begins at 08:00 next working day
        $this->assertSame('2026-12-08 09:00', $cal->addWorkingHours($this->local('2026-12-07 20:00'), 1)->setTimezone(WorkingCalendar::TZ)->format('Y-m-d H:i'));
        $this->assertSame(9 * 60, $cal->workingMinutesBetween($this->local('2026-12-04 08:00'), $this->local('2026-12-06 23:00')));
    }

    public function test_SRS_WFE_06_seeded_selangor_calendar_2026_2027_with_source(): void
    {
        $this->seed(CutiUmumSelangorSeeder::class);

        $this->assertSame(22, CutiUmum::whereYear('tarikh', 2026)->count());
        $this->assertSame(21, CutiUmum::whereYear('tarikh', 2027)->count());
        $this->assertTrue(CutiUmum::whereDate('tarikh', '2026-12-11')->where('nama', 'Hari Keputeraan Sultan Selangor')->exists());
        $this->assertSame(0, CutiUmum::whereNull('sumber')->count());
        $this->assertStringContainsString('kabinet.gov.my', (string) CutiUmum::whereDate('tarikh', '2027-10-28')->value('sumber'));

        // idempotent
        $this->seed(CutiUmumSelangorSeeder::class);
        $this->assertSame(43, CutiUmum::count());
    }

    public function test_SRS_WFE_06_admin_updates_calendar_and_sla_follows(): void
    {
        $admin = $this->user(['wfe.kalendar.lihat', 'wfe.kalendar.kemas_kini']);
        $viewer = $this->user(['wfe.kalendar.lihat']);

        // Tue 3 Nov declared a special holiday → Mon 09:00 + 1 working day = Wed 4 Nov 17:00
        $this->actingAs($viewer)->postJson('/api/v1/wfe/kalendar', ['tarikh' => '2026-11-03', 'nama' => 'Cuti Peristiwa', 'jenis' => 'khas'])->assertForbidden();
        $this->actingAs($admin)->postJson('/api/v1/wfe/kalendar', ['tarikh' => '2026-11-03', 'nama' => 'Cuti Peristiwa', 'jenis' => 'khas', 'sumber' => 'Pengumuman Kerajaan Negeri'])
            ->assertCreated();

        $approver = $this->user();
        $this->activeFlow($this->structure('ujian.cuti', [$this->stage('a', [$approver], ['sla_nilai' => 1])]));
        $id = $this->engine()->start('ujian.cuti', 'rekod_ujian', 1, $this->user());

        $due = Tugasan::where('id_instans', $id)->firstOrFail()->tarikh_akhir_sla->setTimezone(WorkingCalendar::TZ);
        $this->assertSame('2026-11-04 17:00', $due->format('Y-m-d H:i'));

        $list = $this->actingAs($viewer)->getJson('/api/v1/wfe/kalendar?tahun=2026')->assertOk();
        $list->assertJsonPath('data.0.nama', 'Cuti Peristiwa')->assertJsonPath('meta.hujung_minggu', [6, 7]);
    }

    /** Acceptance criterion 2 (+ SRS-WFE-08: two configurable levels with own period and recipients). */
    public function test_SRS_WFE_07_overdue_task_escalates_level_one_then_level_two(): void
    {
        Event::fake([TaskEscalated::class]);
        $supervisor = $this->user(null, [], null, ['role' => 'admin']); // default supervisor role (wfe.peranan_penyelia_lalai)
        $director = $this->user();
        $approver = $this->user();
        $this->activeFlow($this->structure('ujian.eskalasi', [
            $this->stage('a', [$approver], ['sla_nilai' => 1, 'sla_unit' => 'hari_bekerja', 'eskalasi' => [
                ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'jam', 'jenis_penerima' => 'penyelia'],
                ['aras' => 2, 'tempoh_nilai' => 2, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'individu', 'nilai_penerima' => (string) $director->id],
            ]]),
        ]));
        $id = $this->engine()->start('ujian.eskalasi', 'rekod_ujian', 1, $this->user());
        $task = Tugasan::where('id_instans', $id)->firstOrFail(); // due Tue 3 Nov 17:00

        Carbon::setTestNow($this->local('2026-11-03 16:00'));
        $this->artisan('wfe:semak-sla')->assertSuccessful();
        $this->assertSame(0, $task->refresh()->aras_eskalasi);
        $this->assertCount(1, $this->sentOfType('wfe.sla_hampir')); // warning before the SLA runs out

        Carbon::setTestNow($this->local('2026-11-03 17:30'));
        $this->artisan('wfe:semak-sla')->assertSuccessful();
        $this->assertSame(1, $task->refresh()->aras_eskalasi);
        $level1 = $this->sentOfType('wfe.eskalasi')[0];
        $this->assertSame([$supervisor->id], $level1['to']);
        $this->assertSame(1, $level1['data']['aras']);

        // level 2 fires 2 working days after the due date: end of Thu 5 Nov
        Carbon::setTestNow($this->local('2026-11-05 16:00'));
        $this->artisan('wfe:semak-sla');
        $this->assertSame(1, $task->refresh()->aras_eskalasi);

        Carbon::setTestNow($this->local('2026-11-05 17:01'));
        $this->artisan('wfe:semak-sla');
        $this->assertSame(2, $task->refresh()->aras_eskalasi);
        $this->assertSame([$director->id], $this->sentOfType('wfe.eskalasi')[1]['to']);

        // each level fires once
        $this->artisan('wfe:semak-sla');
        $this->assertSame(2, EskalasiLog::where('id_tugasan', $task->id)->count());
        Event::assertDispatchedTimes(TaskEscalated::class, 2);
        $this->assertSame(2, $task->instans->sejarah()->where('peristiwa', 'eskalasi')->count());
    }

    public function test_SRS_WFE_08_escalation_levels_are_configurable_and_validated(): void
    {
        $admin = $this->admin();
        $approver = $this->user();
        $stage = $this->stage('a', [$approver], ['eskalasi' => [
            ['aras' => 1, 'tempoh_nilai' => 4, 'tempoh_unit' => 'jam', 'jenis_penerima' => 'peranan', 'nilai_penerima' => 'ketua_unit'],
            ['aras' => 2, 'tempoh_nilai' => 1, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'kumpulan', 'nilai_penerima' => 'pengurusan'],
            ['aras' => 3, 'tempoh_nilai' => 3, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia'],
        ]]);
        $id = $this->actingAs($admin)->postJson('/api/v1/wfe/definisi', $this->structure('ujian.aras', [$stage]))->assertCreated()->json('data.id');
        $levels = $this->actingAs($admin)->getJson("/api/v1/wfe/definisi/{$id}")->json('data.peringkat.0.eskalasi');
        $this->assertSame([1, 2, 3], array_column($levels, 'aras'));
        $this->assertSame('pengurusan', $levels[1]['nilai_penerima']);

        $dup = $this->stage('a', [$approver], ['eskalasi' => [
            ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'jam', 'jenis_penerima' => 'penyelia'],
            ['aras' => 1, 'tempoh_nilai' => 2, 'tempoh_unit' => 'jam', 'jenis_penerima' => 'penyelia'],
        ]]);
        $this->actingAs($admin)->postJson('/api/v1/wfe/definisi', $this->structure('ujian.aras_dua', [$dup]))
            ->assertUnprocessable()->assertJsonValidationErrors('peringkat.0.eskalasi');
    }

    public function test_SRS_WFE_07_sla_check_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'wfe:semak-sla'));
        $this->assertCount(1, $events);
        $this->assertSame('*/15 * * * *', $events->first()->expression);
    }

    public function test_SRS_WFE_17_deferred_task_resumes_when_period_ends(): void
    {
        $approver = $this->user();
        $this->activeFlow($this->structure('ujian.tangguh', [$this->stage('a', [$approver])]));
        $id = $this->engine()->start('ujian.tangguh', 'rekod_ujian', 1, $this->user());
        $task = Tugasan::where('id_instans', $id)->firstOrFail();
        $this->act($approver, $task->id, 'tangguh', 'Menunggu maklum balas JPS', ['tangguh_hingga' => '2026-11-04T09:00:00+08:00'])->assertOk();

        Carbon::setTestNow($this->local('2026-11-04 09:15'));
        $this->artisan('wfe:semak-sla');
        $this->assertSame(Tugasan::MENUNGGU, $task->refresh()->status);
        $this->assertTrue($task->instans->sejarah()->where('peristiwa', 'sambung')->exists());
        $this->assertInstanceOf(User::class, $approver);
    }
}
