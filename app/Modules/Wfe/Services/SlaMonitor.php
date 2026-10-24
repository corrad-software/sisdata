<?php

namespace App\Modules\Wfe\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Events\TaskEscalated;
use App\Modules\Wfe\Models\Eskalasi;
use App\Modules\Wfe\Models\EskalasiLog;
use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Sejarah;
use App\Modules\Wfe\Models\Tugasan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled SLA check (SRS-WFE-06..08): resumes deferred tasks, warns
 * before the SLA runs out, and fires escalation levels whose trigger period
 * after the due date has passed — each level once, in order.
 */
class SlaMonitor
{
    public function __construct(
        private WorkingCalendar $calendar,
        private Assignees $assignees,
        private Notifier $notifier,
        private Parameters $parameters,
    ) {}

    /** @return array{disambung: int, amaran: int, eskalasi: int} */
    public function run(): array
    {
        $this->calendar->flush();

        return [
            'disambung' => $this->resumeDeferred(),
            'amaran' => $this->warn(),
            'eskalasi' => $this->escalate(),
        ];
    }

    private function resumeDeferred(): int
    {
        $count = 0;
        Tugasan::query()->where('status', Tugasan::DITANGGUH)->where('ditangguh_hingga', '<=', now())->with(['instans', 'peringkat'])->get()
            ->each(function (Tugasan $task) use (&$count) {
                $task->forceFill(['status' => Tugasan::MENUNGGU])->save();
                $this->history($task, 'sambung', ['butiran' => ['sebab' => 'tempoh_tangguh_tamat']]);
                $count++;
            });

        return $count;
    }

    private function warn(): int
    {
        $threshold = max(0, (int) $this->parameters->get('wfe.sla_amaran_jam', 24)) * 60;
        $count = 0;

        $this->openTasks()->whereNull('amaran_sla_pada')->where('tarikh_akhir_sla', '>', now())
            ->get()->each(function (Tugasan $task) use ($threshold, &$count) {
                /** @var Carbon $due */
                $due = $task->tarikh_akhir_sla;
                $left = $this->calendar->workingMinutesBetween(now(), $due);
                if ($left > $threshold) {
                    return;
                }
                $recipients = $this->assignees->recipients($task->jenis_penerima, $task->nilai_penerima);
                $task->forceFill(['amaran_sla_pada' => now()])->save();
                if ($recipients !== []) {
                    $this->notifier->send('wfe.sla_hampir', $recipients, [
                        'tajuk' => $task->instans->tajuk,
                        'peringkat' => $task->peringkat->nama_peringkat ?? $task->tajuk,
                        'baki_jam' => round($left / 60, 1),
                        'tarikh_akhir' => $due->copy()->setTimezone(WorkingCalendar::TZ)->format('d/m/Y H:i'),
                    ], '/admin/wfe/tugasan/'.$task->id, 'wfe.sla_hampir.'.$task->id);
                }
                $count++;
            });

        return $count;
    }

    private function escalate(): int
    {
        $count = 0;
        $this->openTasks()->where('tarikh_akhir_sla', '<=', now())->whereNotNull('id_peringkat')
            ->with(['peringkat.eskalasi'])
            ->get()->each(function (Tugasan $task) use (&$count) {
                /** @var Carbon $due */
                $due = $task->tarikh_akhir_sla;
                $levels = $task->peringkat?->eskalasi->where('aras', '>', $task->aras_eskalasi)->sortBy('aras') ?? collect();
                foreach ($levels as $level) {
                    /** @var Eskalasi $level */
                    $trigger = $level->tempoh_nilai > 0 ? $this->calendar->due($due, $level->tempoh_nilai, $level->tempoh_unit) : $due;
                    if ($trigger->greaterThan(now())) {
                        break;
                    }
                    $this->fire($task, $level);
                    $count++;
                }
            });

        return $count;
    }

    private function fire(Tugasan $task, Eskalasi $level): void
    {
        $recipients = $level->jenis_penerima === 'penyelia'
            ? $this->supervisorsFor($task)
            : $this->assignees->recipients($level->jenis_penerima, $level->nilai_penerima);
        $ids = array_map(fn (User $u) => $u->id, $recipients);

        DB::transaction(function () use ($task, $level, $ids) {
            EskalasiLog::create(['id_tugasan' => $task->id, 'aras' => $level->aras, 'penerima' => $ids]);
            $task->forceFill(['aras_eskalasi' => $level->aras])->save();
            $this->history($task, 'eskalasi', ['butiran' => ['aras' => $level->aras, 'penerima' => $ids]]);
        });

        if ($recipients !== []) {
            $this->notifier->send('wfe.eskalasi', $recipients, [
                'tajuk' => $task->instans->tajuk,
                'peringkat' => $task->peringkat->nama_peringkat ?? '-',
                'aras' => $level->aras,
                'penerima_asal' => $this->assignees->label($task->jenis_penerima, $task->nilai_penerima),
            ], '/admin/wfe/tugasan/'.$task->id, 'wfe.eskalasi.'.$task->id.'.'.$level->aras);
        }
        TaskEscalated::dispatch($task->id, $task->id_instans, $task->instans->kod_aliran, $level->aras, $ids);
    }

    /**
     * "Penyelia bertanggungjawab": supervisors of the assigned people.
     *
     * @return list<User>
     */
    private function supervisorsFor(Tugasan $task): array
    {
        $users = [];
        foreach ($this->assignees->members($task->jenis_penerima, $task->nilai_penerima) as $member) {
            foreach ($this->assignees->supervisorsOf($member) as $supervisor) {
                $users[$supervisor->id] = $supervisor;
            }
        }

        return array_values($users);
    }

    /** @return Builder<Tugasan> */
    private function openTasks()
    {
        return Tugasan::query()->where('status', Tugasan::MENUNGGU)->whereNotNull('tarikh_akhir_sla')
            ->whereHas('instans', fn ($q) => $q->withoutGlobalScope('access')->where('status', Instans::BERJALAN))
            ->with(['instans', 'peringkat'])->orderBy('id');
    }

    /** @param array<string, mixed> $extra */
    private function history(Tugasan $task, string $event, array $extra = []): void
    {
        Sejarah::create([
            'id_instans' => $task->id_instans,
            'id_tugasan' => $task->id,
            'id_peringkat' => $task->id_peringkat,
            'nama_peringkat' => $task->peringkat?->nama_peringkat,
            'peristiwa' => $event,
            'butiran' => $extra['butiran'] ?? null,
            'tarikh_masa' => now(),
        ]);
    }
}
