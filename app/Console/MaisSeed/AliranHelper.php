<?php

namespace App\Console\MaisSeed;

use App\Models\User;
use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\Assignees;
use App\Modules\Wfe\Services\Engine;
use Throwable;

/**
 * Decides open MOD-WFE tasks the way an approver would through the API —
 * Engine::decide, so segregation of duties, history, audit and the module
 * listeners (WorkflowCompleted) all run. Used to leave demo instances in a
 * mix of states: some pending, some approved, some rejected.
 */
class AliranHelper
{
    public function __construct(private Engine $engine, private Assignees $assignees) {}

    /**
     * "lulus": approve stage after stage until the flow completes;
     * "tolak": reject at the first open stage.
     * Returns the instance status afterwards (null when not found).
     *
     * @param  list<User>  $approvers
     */
    public function putus(string $instanceId, string $outcome, array $approvers, string $note): ?string
    {
        for ($guard = 0; $guard < 8; $guard++) {
            $instance = Instans::query()->withoutGlobalScope('access')->find($instanceId);
            if ($instance === null || $instance->status !== Instans::BERJALAN) {
                return $instance?->status;
            }
            $tasks = Tugasan::query()->where('id_instans', $instanceId)->where('jenis', Tugasan::KELULUSAN)
                ->where('kitaran', $instance->kitaran)->whereIn('status', [Tugasan::MENUNGGU, Tugasan::DITANGGUH])->orderBy('id')->get();
            $decided = false;
            foreach ($tasks as $task) {
                foreach ($approvers as $user) {
                    if ($user->id === $instance->id_pemohon || ! array_key_exists($task->penerima_kunci, $this->assignees->actingKeys($user))) {
                        continue;
                    }
                    try {
                        $this->engine->decide($task, $user, $outcome, $outcome === 'tolak' ? $note.' — tidak memenuhi syarat (SINTETIK)' : $note, ['mfa_at' => now()]);
                        $decided = true;
                        break 2;
                    } catch (Throwable) {
                        continue; // segregation of duties etc.: try the next approver
                    }
                }
            }
            if (! $decided) {
                return $instance->status;
            }
        }

        return Instans::query()->withoutGlobalScope('access')->find($instanceId)?->status;
    }
}
