<?php

namespace App\Modules\Adm\Services\Tugasan;

use App\Modules\Adm\Models\Tugasan;
use App\Modules\Adm\Models\TugasanLarian;
use App\Modules\Adm\Services\Amaran;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bridges adm_tugasan_berjadual and Laravel's scheduler (SRS-ADM-06).
 *
 *  - ADM-defined jobs are registered as scheduler events named "adm:<kod>"
 *    (cron or fixed interval) that hand off to PelaksanaTugasan.
 *  - Jobs other modules register in Laravel's scheduler are listed read-only;
 *    an admin can "adopt" one (sumber = sistem): ADM then records its run
 *    history, can switch it off (skip filter) and alerts on failure.
 */
class PenjadualTugasan
{
    public const PREFIX = 'adm:';

    /** @var array<string, int> mutex => open run id, for adopted framework events */
    private array $open = [];

    private bool $decorated = false;

    public function register(Schedule $schedule): void
    {
        try {
            if (! Schema::hasTable('adm_tugasan_berjadual')) {
                return;
            }
            $jobs = Tugasan::query()->where('sumber', 'adm')->where('aktif', true)->get();
        } catch (Throwable) {
            return; // DB unavailable: the rest of the schedule still runs
        }

        foreach ($jobs as $tugasan) {
            $id = $tugasan->id;
            $event = $schedule->call(fn () => app(PelaksanaTugasan::class)->run($id, 'jadual'))
                ->name(self::PREFIX.$tugasan->kod);

            if ($tugasan->jenis_jadual === 'selang') {
                $event->everyMinute()->when(fn () => (bool) Tugasan::query()->find($id)?->dueByInterval());
            } else {
                $event->cron((string) $tugasan->ungkapan_cron);
            }
        }
    }

    /**
     * Framework/module scheduler events not owned by ADM.
     *
     * @return list<array{kunci: string, perihal: string, arahan: string, ungkapan: string, seterusnya: ?string, id_tugasan: ?int}>
     */
    public function foreign(Schedule $schedule): array
    {
        $adopted = Tugasan::query()->where('sumber', 'sistem')->pluck('id', 'kunci_sistem')->all();
        $list = [];

        foreach ($schedule->events() as $event) {
            if ($this->isOwn($event)) {
                continue;
            }

            $key = $event->mutexName();
            try {
                $next = $event->nextRunDate()->toIso8601String();
            } catch (Throwable) {
                $next = null;
            }

            $list[] = [
                'kunci' => $key,
                'perihal' => (string) ($event->description ?: self::commandLine($event)),
                'arahan' => self::commandLine($event),
                'ungkapan' => $event->expression,
                'seterusnya' => $next,
                'id_tugasan' => $adopted[$key] ?? null,
            ];
        }

        return $list;
    }

    public function find(Schedule $schedule, string $key): ?Event
    {
        foreach ($schedule->events() as $event) {
            if (! $this->isOwn($event) && $event->mutexName() === $key) {
                return $event;
            }
        }

        return null;
    }

    /** Called when schedule:run/work starts: adopted events can be switched off from ADM. */
    public function decorateForeign(Schedule $schedule): void
    {
        if ($this->decorated) {
            return;
        }
        $this->decorated = true;

        try {
            $adopted = Tugasan::query()->where('sumber', 'sistem')->pluck('id', 'kunci_sistem')->all();
        } catch (Throwable) {
            return;
        }

        foreach ($schedule->events() as $event) {
            $id = $adopted[$event->mutexName()] ?? null;
            if ($id !== null && ! $this->isOwn($event)) {
                $event->skip(fn () => Tugasan::query()->whereKey($id)->where('aktif', false)->exists());
            }
        }
    }

    public function onStarting(ScheduledTaskStarting $e): void
    {
        $tugasan = $this->adoptedFor($e->task);
        if (! $tugasan) {
            return;
        }

        $run = TugasanLarian::query()->create([
            'id_tugasan' => $tugasan->id, 'pencetus' => 'jadual', 'cubaan' => 1,
            'tarikh_mula' => now(), 'status' => TugasanLarian::BERJALAN, 'id_korelasi' => (string) Str::uuid(),
        ]);
        $tugasan->forceFill(['terakhir_mula_pada' => now()])->saveQuietly();
        $this->open[$e->task->mutexName()] = $run->id;
    }

    public function onFinished(ScheduledTaskFinished $e): void
    {
        $this->close($e->task, $e->task->exitCode === null || $e->task->exitCode === 0, $e->task->exitCode ? 'Exit code '.$e->task->exitCode : null);
    }

    public function onFailed(ScheduledTaskFailed $e): void
    {
        $this->close($e->task, false, $e->exception::class.': '.$e->exception->getMessage());
    }

    public function onSkipped(ScheduledTaskSkipped $e): void
    {
        // Skipped because ADM switched it off: nothing to record.
    }

    private function close(Event $event, bool $ok, ?string $error): void
    {
        $runId = $this->open[$event->mutexName()] ?? null;
        unset($this->open[$event->mutexName()]);
        $run = $runId ? TugasanLarian::query()->find($runId) : null;
        if (! $run || $run->status !== TugasanLarian::BERJALAN) {
            return;
        }

        $run->update([
            'tarikh_tamat' => now(),
            'tempoh_saat' => $run->tarikh_mula ? round($run->tarikh_mula->diffInMilliseconds(now()) / 1000, 3) : null,
            'status' => $ok ? TugasanLarian::BERJAYA : TugasanLarian::GAGAL,
            'mesej_ralat' => $error,
        ]);

        $tugasan = Tugasan::query()->find($run->id_tugasan);
        $tugasan?->forceFill(['terakhir_status' => $run->status])->saveQuietly();

        if (! $ok && $tugasan) {
            app(Amaran::class)->ict('adm.tugasan_gagal', [
                'tugasan' => $tugasan->nama, 'kod' => $tugasan->kod, 'cubaan' => 1, 'ralat' => Str::limit((string) $error, 200),
            ], '/admin/pentadbiran/tugasan-berjadual/'.$tugasan->id, 'tugasan_gagal:'.$tugasan->id.':'.$run->id);
        }
    }

    private function adoptedFor(Event $event): ?Tugasan
    {
        if ($this->isOwn($event)) {
            return null;
        }

        try {
            return Tugasan::query()->where('sumber', 'sistem')->where('kunci_sistem', $event->mutexName())->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function isOwn(Event $event): bool
    {
        return $event instanceof CallbackEvent && str_starts_with((string) $event->description, self::PREFIX);
    }

    /** "'/usr/bin/php' 'artisan' dms:arkib --hari=30" → "dms:arkib --hari=30" */
    public static function commandLine(Event $event): string
    {
        $command = (string) $event->command;
        if ($command === '') {
            return (string) $event->description;
        }

        return trim((string) preg_replace("/^.*?'?artisan'?\s+/", '', $command));
    }
}
