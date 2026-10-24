<?php

namespace App\Modules\Wfe\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Wfe\Contracts\SubjectLinks;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\StepDecided;
use App\Modules\Wfe\Events\TaskAssigned;
use App\Modules\Wfe\Events\WorkflowCompleted;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Peralihan;
use App\Modules\Wfe\Models\Peringkat;
use App\Modules\Wfe\Models\PindahVersi;
use App\Modules\Wfe\Models\Sejarah;
use App\Modules\Wfe\Models\Susulan;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Support\WfeException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The central approval engine (SRS-GEN-03, SRS-WFE-01..22).
 *
 * Every rule that matters for control — who may act, segregation of duties,
 * mandatory justification, MFA on critical stages — is enforced here, not
 * in the controllers or the UI (security control: "pengasingan tugas
 * dikuatkuasakan pada peringkat enjin"), so a direct API call gets exactly
 * the same answer as the task board.
 */
class Engine implements WorkflowEngine
{
    public const ACTIONS = ['lulus', 'tolak', 'kembali', 'tangguh'];

    public const NEEDS_JUSTIFICATION = ['tolak', 'kembali', 'tangguh'];

    public function __construct(
        private ConditionEvaluator $conditions,
        private WorkingCalendar $calendar,
        private Assignees $assignees,
        private Notifier $notifier,
        private AuditLogger $audit,
        private AccessControl $access,
        private SubjectLinks $links,
        private Parameters $parameters,
    ) {}

    // ---------------------------------------------------------------- contract

    public function start(string $flowCode, string $subjectType, string|int $subjectId, User $initiator, array $context = [], ?string $title = null): string
    {
        $definition = Definisi::query()->aktif()->where('kod_aliran', $flowCode)->orderByDesc('no_versi')->first();
        if (! $definition) {
            throw new InvalidArgumentException(__('wfe::wfe.error.tiada_aliran_aktif', ['kod' => $flowCode]));
        }
        if ($this->activeInstanceFor($subjectType, $subjectId)) {
            throw new WfeException(__('wfe::wfe.error.instans_wujud'), 'instans_wujud', 409);
        }

        return DB::transaction(function () use ($definition, $subjectType, $subjectId, $initiator, $context, $title) {
            $link = isset($context['pautan']) && is_string($context['pautan']) && str_starts_with($context['pautan'], '/')
                ? $context['pautan']
                : $this->links->linkFor($subjectType, $subjectId);

            $instance = Instans::create([
                'id_definisi' => $definition->id,
                'no_versi_definisi' => $definition->no_versi,
                'kod_aliran' => $definition->kod_aliran,
                'modul' => $definition->modul,
                'jenis_subjek' => $subjectType,
                'id_rekod' => (string) $subjectId,
                'tajuk' => $title ?? $definition->nama.' #'.$subjectId,
                'konteks' => $context,
                'kod_daerah' => isset($context['kod_daerah']) && is_scalar($context['kod_daerah']) ? (string) $context['kod_daerah'] : null,
                'pautan' => $link,
                'id_pemohon' => $initiator->id,
                'status' => Instans::BERJALAN,
                'kitaran' => 0,
                'laluan' => [],
                'tarikh_mula' => now(),
            ]);

            $this->history($instance, 'mula', user: $initiator, extra: [
                'peranan_semasa' => $this->access->roles($initiator),
                'alamat_ip' => request()->ip(),
                'butiran' => ['versi' => $definition->no_versi],
            ]);
            $this->route($instance, Peralihan::MULA, $initiator);

            return $instance->id;
        });
    }

    public function status(string $instanceId): string
    {
        return Instans::query()->withoutGlobalScope('access')->findOrFail($instanceId)->status;
    }

    public function activeInstanceFor(string $subjectType, string|int $subjectId): ?string
    {
        return Instans::query()->withoutGlobalScope('access')
            ->where('jenis_subjek', $subjectType)->where('id_rekod', (string) $subjectId)
            ->where('status', Instans::BERJALAN)->value('id');
    }

    // ---------------------------------------------------------------- decisions

    /**
     * Act on an approval task (SRS-WFE-16..19).
     *
     * @param  array{ip?: string|null, mfa_at?: Carbon|null, tangguh_hingga?: Carbon|null}  $meta
     *
     * @throws AuthorizationException when the user isn't an assignee
     * @throws WfeException on segregation of duties / MFA / state rules
     * @throws ValidationException when justification is missing
     */
    public function decide(Tugasan $task, User $actor, string $action, ?string $justification, array $meta = []): Tugasan
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException("Unknown action {$action}");
        }

        DB::transaction(function () use ($task, $actor, $action, $justification, $meta) {
            $instance = Instans::query()->withoutGlobalScope('access')->lockForUpdate()->findOrFail($task->id_instans);
            $task->refresh();

            if ($task->jenis !== Tugasan::KELULUSAN || ! $task->isOpen() || $instance->status !== Instans::BERJALAN || $task->kitaran !== $instance->kitaran) {
                throw new WfeException(__('wfe::wfe.error.tugasan_tidak_aktif'), 'tugasan_tidak_aktif', 409);
            }

            $onBehalf = $this->authorise($task, $actor);
            /** @var Peringkat $stage */
            $stage = $task->peringkat()->with('pelulus')->firstOrFail();

            $justification = trim((string) $justification);
            if ($justification === '' && (in_array($action, self::NEEDS_JUSTIFICATION, true) || $stage->perlu_justifikasi)) {
                throw ValidationException::withMessages(['justifikasi' => __('wfe::wfe.validation.justifikasi_wajib')]);
            }

            $this->enforceSegregation($instance, $stage, $task, $actor, $onBehalf);

            $mfaAt = $meta['mfa_at'] ?? null;
            if ($stage->perlu_mfa && ! $this->mfaFresh($mfaAt)) {
                throw new WfeException(__('wfe::wfe.error.mfa_diperlukan'), 'mfa_diperlukan', 403);
            }

            $roles = $this->access->roles($actor);

            if ($action === 'tangguh') {
                $this->defer($task, $meta['tangguh_hingga'] ?? null);
            } else {
                $task->forceFill([
                    'status' => Tugasan::SELESAI,
                    'keputusan' => $action,
                    'diselesaikan_oleh' => $actor->id,
                    'diselesaikan_pada' => now(),
                ])->save();
            }

            $this->history($instance, $action, $stage, $task, $actor, [
                'id_bagi_pihak' => $onBehalf,
                'peranan_semasa' => $roles,
                'justifikasi' => $justification !== '' ? $justification : null,
                'alamat_ip' => $meta['ip'] ?? null,
                'mfa_pada' => $stage->perlu_mfa ? $mfaAt : null,
                'butiran' => $action === 'tangguh' ? ['hingga' => $task->ditangguh_hingga?->toIso8601String()] : null,
            ]);

            // SRS-AUD-18: decisions are explicit audit actions, with justification.
            $this->audit->record($action, 'wfe', 'wf_instans', $instance->id, reason: $justification ?: null, meta: [
                'kod_aliran' => $instance->kod_aliran,
                'tugasan' => $task->id,
                'peringkat' => $stage->kod,
                'bagi_pihak' => $onBehalf,
                'peranan_semasa' => $roles,
                'mfa' => $stage->perlu_mfa,
            ]);

            DB::afterCommit(fn () => StepDecided::dispatch($instance->id, $task->id, $instance->kod_aliran, $stage->kod, $action, $actor->id, $onBehalf, $justification ?: null));

            match ($action) {
                'lulus' => $this->afterApproval($instance, $stage, $actor),
                'tolak' => $this->afterRejection($instance, $stage, $actor, $justification),
                'kembali' => $this->returnInstance($instance, $actor, $justification),
                default => null,
            };
        });

        return $task->refresh();
    }

    /** SRS-WFE-20: complete a follow-up task. */
    public function completeFollowUp(Tugasan $task, User $actor, ?string $note = null): Tugasan
    {
        DB::transaction(function () use ($task, $actor, $note) {
            $task->refresh();
            if ($task->jenis !== Tugasan::SUSULAN || ! $task->isOpen()) {
                throw new WfeException(__('wfe::wfe.error.tugasan_tidak_aktif'), 'tugasan_tidak_aktif', 409);
            }
            $onBehalf = $this->authorise($task, $actor);
            $task->forceFill(['status' => Tugasan::SELESAI, 'keputusan' => 'selesai', 'diselesaikan_oleh' => $actor->id, 'diselesaikan_pada' => now()])->save();
            $instance = $task->instans;
            $this->history($instance, 'susulan_selesai', null, $task, $actor, [
                'id_bagi_pihak' => $onBehalf, 'peranan_semasa' => $this->access->roles($actor), 'justifikasi' => $note,
                'alamat_ip' => request()->ip(), 'butiran' => ['tajuk' => $task->tajuk],
            ]);
            DB::afterCommit(fn () => StepDecided::dispatch($instance->id, $task->id, $instance->kod_aliran, null, 'selesai', $actor->id, $onBehalf, $note));
        });

        return $task->refresh();
    }

    /** SRS-WFE-22: the applicant withdraws a request that has no final decision. */
    public function withdraw(string $instanceId, User $user, string $reason): Instans
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['justifikasi' => __('wfe::wfe.validation.justifikasi_wajib')]);
        }

        return DB::transaction(function () use ($instanceId, $user, $reason) {
            $instance = Instans::query()->withoutGlobalScope('access')->lockForUpdate()->findOrFail($instanceId);
            if ($instance->id_pemohon !== $user->id) {
                throw new WfeException(__('wfe::wfe.error.bukan_pemohon'), 'bukan_pemohon', 403);
            }
            if ($instance->status !== Instans::BERJALAN) {
                throw new WfeException(__('wfe::wfe.error.sudah_tamat'), 'instans_tamat', 409);
            }
            $this->history($instance, 'tarik_balik', $instance->peringkat, null, $user, [
                'peranan_semasa' => $this->access->roles($user), 'justifikasi' => $reason, 'alamat_ip' => request()->ip(),
            ]);
            $this->audit->record('tarik_balik', 'wfe', 'wf_instans', $instance->id, reason: $reason, meta: ['kod_aliran' => $instance->kod_aliran]);
            $this->finish($instance, Instans::DITARIK_BALIK, $user, $reason);

            return $instance->refresh();
        });
    }

    /** After a return at the first stage, the applicant sends the request in again. */
    public function resubmit(string $instanceId, User $user, ?string $note = null): Instans
    {
        return DB::transaction(function () use ($instanceId, $user, $note) {
            $instance = Instans::query()->withoutGlobalScope('access')->lockForUpdate()->findOrFail($instanceId);
            if ($instance->id_pemohon !== $user->id) {
                throw new WfeException(__('wfe::wfe.error.bukan_pemohon'), 'bukan_pemohon', 403);
            }
            if ($instance->status !== Instans::BERJALAN || $instance->keadaan !== Instans::MENUNGGU_PEMOHON) {
                throw new WfeException(__('wfe::wfe.error.tidak_menunggu_pemohon'), 'tidak_menunggu_pemohon', 409);
            }
            $this->history($instance, 'hantar_semula', null, null, $user, ['justifikasi' => $note, 'alamat_ip' => request()->ip()]);
            $instance->forceFill(['keadaan' => null, 'laluan' => []])->save();
            $this->route($instance, Peralihan::MULA, $user);

            return $instance->refresh();
        });
    }

    // ---------------------------------------------------------------- version migration (SRS-WFE-12)

    public function requestMigration(Instans $instance, Definisi $target, User $user, string $reason): PindahVersi
    {
        if ($instance->status !== Instans::BERJALAN) {
            throw new WfeException(__('wfe::wfe.error.sudah_tamat'), 'instans_tamat', 409);
        }
        if ($target->kod_aliran !== $instance->kod_aliran || $target->id === $instance->id_definisi || $target->status !== Definisi::AKTIF) {
            throw new WfeException(__('wfe::wfe.error.versi_sasaran'), 'versi_sasaran', 422);
        }
        if (PindahVersi::query()->where('id_instans', $instance->id)->where('status', 'menunggu')->exists()) {
            throw new WfeException(__('wfe::wfe.error.pindah_wujud'), 'pindah_wujud', 409);
        }

        $request = PindahVersi::create([
            'id_instans' => $instance->id,
            'id_definisi_asal' => $instance->id_definisi,
            'id_definisi_baru' => $target->id,
            'sebab' => $reason,
            'status' => 'menunggu',
            'diminta_oleh' => $user->id,
        ]);
        $this->history($instance, 'mohon_pindah_versi', null, null, $user, [
            'justifikasi' => $reason, 'butiran' => ['dari' => $instance->no_versi_definisi, 'ke' => $target->no_versi],
        ]);

        return $request;
    }

    public function decideMigration(PindahVersi $request, User $admin, bool $approve, ?string $note = null): PindahVersi
    {
        if ($request->status !== 'menunggu') {
            throw new WfeException(__('wfe::wfe.error.bukan_menunggu'), 'bukan_menunggu', 409);
        }
        if ($request->diminta_oleh === $admin->id) {
            throw new WfeException(__('wfe::wfe.error.pentadbir_kedua'), 'pengasingan_tugas', 403);
        }

        DB::transaction(function () use ($request, $admin, $approve, $note) {
            $request->forceFill(['status' => $approve ? 'lulus' : 'tolak', 'diputuskan_oleh' => $admin->id, 'diputuskan_pada' => now()])->save();
            $instance = Instans::query()->withoutGlobalScope('access')->lockForUpdate()->findOrFail($request->id_instans);

            $this->audit->record($approve ? 'lulus' : 'tolak', 'wfe', 'wf_instans', $instance->id, reason: $note ?: $request->sebab, meta: [
                'tindakan' => 'pindah_versi', 'ke_definisi' => $request->id_definisi_baru,
            ]);

            if (! $approve) {
                $this->history($instance, 'tolak_pindah_versi', null, null, $admin, ['justifikasi' => $note]);

                return;
            }
            if ($instance->status !== Instans::BERJALAN) {
                throw new WfeException(__('wfe::wfe.error.sudah_tamat'), 'instans_tamat', 409);
            }

            $target = $request->definisiBaru()->with('peringkat')->firstOrFail();
            $current = $instance->peringkat;
            $this->cancelOpenTasks($instance);

            $oldStages = Peringkat::query()->whereIn('id', $instance->laluan ?? [])->pluck('kod', 'id');
            $newByKod = $target->peringkat->keyBy('kod');
            $path = [];
            foreach ($instance->laluan ?? [] as $id) {
                $kod = $oldStages[$id] ?? null;
                if ($kod !== null && isset($newByKod[$kod])) {
                    $path[] = $newByKod[$kod]->id;
                }
            }

            $instance->forceFill([
                'id_definisi' => $target->id,
                'no_versi_definisi' => $target->no_versi,
            ])->save();
            $this->history($instance, 'pindah_versi', null, null, $admin, [
                'justifikasi' => $note ?: $request->sebab,
                'peranan_semasa' => $this->access->roles($admin),
                'butiran' => ['ke_versi' => $target->no_versi, 'diminta_oleh' => $request->diminta_oleh],
            ]);

            if ($instance->keadaan === Instans::MENUNGGU_PEMOHON) {
                $instance->forceFill(['laluan' => []])->save();

                return;
            }

            $instance->unsetRelation('definisi');
            if ($current && isset($newByKod[$current->kod])) {
                array_pop($path);
                $instance->forceFill(['laluan' => $path])->save();
                $this->enterStage($instance, $newByKod[$current->kod]);
            } else {
                $instance->forceFill(['laluan' => []])->save();
                $this->route($instance, Peralihan::MULA, $admin);
            }
        });

        return $request->refresh();
    }

    // ---------------------------------------------------------------- routing

    private function route(Instans $instance, string $from, ?User $actor): void
    {
        $definition = $instance->definisi()->with(['peralihan.syarat', 'peringkat.pelulus'])->firstOrFail();
        $edges = $definition->peralihan->where('dari', $from)->sortBy([['keutamaan', 'asc'], ['id', 'asc']])->values();
        $context = $instance->konteks ?? [];

        $chosen = $edges->first(fn (Peralihan $e) => $e->syarat->isNotEmpty() && $this->conditions->matchesAll($e->syarat, $context, $e->logik))
            ?? $edges->first(fn (Peralihan $e) => $e->syarat->isEmpty());

        if (! $chosen || $chosen->ke === Peralihan::TAMAT) {
            $this->finish($instance, Instans::LULUS, $actor, null);

            return;
        }

        /** @var Peringkat $stage */
        $stage = $definition->peringkat->firstWhere('kod', $chosen->ke);
        $this->enterStage($instance, $stage);
    }

    private function enterStage(Instans $instance, Peringkat $stage): void
    {
        $stage->loadMissing('pelulus');
        $path = $instance->laluan ?? [];
        $path[] = $stage->id;
        $instance->forceFill([
            'kitaran' => $instance->kitaran + 1,
            'peringkat_semasa' => $stage->id,
            'keadaan' => null,
            'laluan' => $path,
        ])->save();

        $this->history($instance, 'masuk_peringkat', $stage);

        foreach ($stage->pelulus as $slot) {
            $this->createTask($instance, $stage, $slot->jenis, $slot->nilai, $slot->id);
        }
    }

    private function createTask(Instans $instance, ?Peringkat $stage, string $type, ?string $value, ?int $slotId = null, string $kind = Tugasan::KELULUSAN, ?string $title = null, ?int $slaValue = null, string $slaUnit = 'hari_bekerja'): Tugasan
    {
        $origin = null;
        if ($type === 'penyelia_pemohon' || $type === 'pemohon') {
            $target = $type === 'pemohon' ? $instance->pemohon : ($this->assignees->supervisorsOf($instance->pemohon)[0] ?? null);
            [$type, $value] = $target ? ['individu', (string) $target->id] : ['tiada', null];
        }
        if ($type === 'individu' && $value !== null) {
            $person = User::query()->find((int) $value);
            if ($person && ($delegate = $this->assignees->delegateOf($person))) {
                $origin = $person->id;
                $value = (string) $delegate->id;
            }
        }

        $slaValue ??= $stage?->sla_nilai;
        $slaUnit = $stage ? $stage->sla_unit : $slaUnit;

        $task = Tugasan::create([
            'id_instans' => $instance->id,
            'id_peringkat' => $stage?->id,
            'jenis' => $kind,
            'tajuk' => $title,
            'kitaran' => $instance->kitaran,
            'id_pelulus' => $slotId,
            'jenis_penerima' => $type,
            'nilai_penerima' => $value,
            'penerima_kunci' => Assignees::key($type, $value),
            'id_pelulus_asal' => $origin,
            'status' => Tugasan::MENUNGGU,
            'tarikh_akhir_sla' => $slaValue ? $this->calendar->due(now(), $slaValue, $slaUnit) : null,
        ]);

        $recipients = $this->assignees->recipients($type, $value);
        $this->history($instance, $kind === Tugasan::SUSULAN ? 'susulan' : 'tugasan', $stage, $task, null, [
            'id_bagi_pihak' => $origin,
            'butiran' => ['penerima' => $task->penerima_kunci, 'label' => $this->assignees->label($type, $value), 'tajuk' => $title],
        ]);

        $ids = array_map(fn (User $u) => $u->id, $recipients);
        DB::afterCommit(function () use ($task, $instance, $stage, $recipients, $ids, $title) {
            if ($recipients !== []) {
                $this->notifier->send('wfe.tugasan_baharu', $recipients, [
                    'tajuk' => $instance->tajuk,
                    'peringkat' => $stage->nama_peringkat ?? $title,
                    'tarikh_akhir' => $task->tarikh_akhir_sla?->setTimezone(WorkingCalendar::TZ)->format('d/m/Y H:i') ?? '-',
                ], '/admin/wfe/tugasan/'.$task->id, 'wfe.tugasan.'.$task->id);
            }
            TaskAssigned::dispatch($task->id, $instance->id, $instance->kod_aliran, $task->penerima_kunci, $ids, $task->tarikh_akhir_sla?->toIso8601String());
        });

        return $task;
    }

    private function afterApproval(Instans $instance, Peringkat $stage, User $actor): void
    {
        $tasks = $this->stageTasks($instance, $stage);
        $approved = $tasks->where('keputusan', 'lulus')->count();
        if ($approved < $stage->approvalsNeeded()) {
            return;
        }
        $this->cancelOpenTasks($instance);
        $this->history($instance, 'peringkat_selesai', $stage, null, null, ['butiran' => ['kelulusan' => $approved]]);
        $this->route($instance, $stage->kod, $actor);
    }

    private function afterRejection(Instans $instance, Peringkat $stage, User $actor, string $justification): void
    {
        $tasks = $this->stageTasks($instance, $stage);
        $approved = $tasks->where('keputusan', 'lulus')->count();
        $open = $tasks->filter(fn (Tugasan $t) => $t->isOpen())->count();

        // Rejected once the stage can no longer reach the approvals it needs.
        if ($approved + $open < $stage->approvalsNeeded()) {
            $this->finish($instance, Instans::TOLAK, $actor, $justification);
        }
    }

    /** "Kembalikan": back to the previous stage, or to the applicant from the first one. */
    private function returnInstance(Instans $instance, User $actor, string $justification): void
    {
        $this->cancelOpenTasks($instance);
        $path = $instance->laluan ?? [];
        array_pop($path);
        $previous = array_pop($path);

        if ($previous !== null && ($stage = Peringkat::query()->find($previous))) {
            $instance->forceFill(['laluan' => $path])->save();
            $this->enterStage($instance, $stage);

            return;
        }

        $instance->forceFill(['keadaan' => Instans::MENUNGGU_PEMOHON, 'peringkat_semasa' => null, 'laluan' => []])->save();
        DB::afterCommit(fn () => $this->notifier->send('wfe.keputusan', $instance->pemohon, [
            'tajuk' => $instance->tajuk,
            'keputusan' => __('wfe::wfe.status.dikembalikan'),
            'ulasan' => mb_substr($justification, 0, 200),
        ], '/admin/wfe/permohonan', 'wfe.kembali.'.$instance->id.'.'.$instance->kitaran));
    }

    private function finish(Instans $instance, string $outcome, ?User $actor, ?string $remarks): void
    {
        $this->cancelOpenTasks($instance);
        $instance->forceFill([
            'status' => $outcome,
            'tarikh_tamat' => now(),
            'peringkat_semasa' => null,
            'keadaan' => null,
            'ulasan_keputusan' => $remarks,
        ])->save();
        $this->history($instance, 'selesai', null, null, $actor, ['butiran' => ['keputusan' => $outcome]]);

        if (in_array($outcome, [Instans::LULUS, Instans::TOLAK], true)) {
            $this->createFollowUps($instance, $outcome);
        }

        DB::afterCommit(function () use ($instance, $outcome, $actor, $remarks) {
            WorkflowCompleted::dispatch($instance->id, $instance->kod_aliran, $instance->jenis_subjek, $instance->id_rekod, $outcome, $actor?->id, $remarks);
            $this->notifier->send('wfe.keputusan', $instance->pemohon, [
                'tajuk' => $instance->tajuk,
                'keputusan' => __('wfe::wfe.status.'.$outcome),
                'ulasan' => $remarks ? mb_substr($remarks, 0, 200) : '-',
            ], '/admin/wfe/permohonan', 'wfe.keputusan.'.$instance->id);
        });
    }

    private function createFollowUps(Instans $instance, string $outcome): void
    {
        $followUps = Susulan::query()->where('id_definisi', $instance->id_definisi)->whereIn('bila', [$outcome, 'semua'])->get();
        foreach ($followUps as $f) {
            $this->createTask($instance, null, $f->jenis_penerima, $f->nilai_penerima, null, Tugasan::SUSULAN, $f->tajuk, $f->sla_nilai, $f->sla_unit);
        }
    }

    // ---------------------------------------------------------------- rules

    /**
     * Returns the user the actor acts for (pemangku), or null for themselves.
     *
     * @throws AuthorizationException
     */
    private function authorise(Tugasan $task, User $actor): ?int
    {
        $keys = $this->assignees->actingKeys($actor);
        if (! array_key_exists($task->penerima_kunci, $keys)) {
            throw new AuthorizationException(__('wfe::wfe.error.bukan_penerima'));
        }

        return $keys[$task->penerima_kunci] ?? $task->id_pelulus_asal;
    }

    /** SRS-WFE-18, enforced in the engine. */
    private function enforceSegregation(Instans $instance, Peringkat $stage, Tugasan $task, User $actor, ?int $onBehalf): void
    {
        $people = array_values(array_filter([$actor->id, $onBehalf]));

        if (in_array($instance->id_pemohon, $people, true)) {
            throw new WfeException(__('wfe::wfe.error.sod_pemohon'), 'pengasingan_tugas', 403);
        }

        $sameStage = $this->stageTasks($instance, $stage)->where('id', '!=', $task->id)->whereNotNull('diselesaikan_oleh');
        if ($this->decidersOf($sameStage)->intersect($people)->isNotEmpty()) {
            throw new WfeException(__('wfe::wfe.error.sod_peringkat_sama'), 'pengasingan_tugas', 403);
        }

        $path = $instance->laluan ?? [];
        $previousStageId = count($path) >= 2 ? $path[count($path) - 2] : null;
        if ($previousStageId !== null) {
            $lastRound = Tugasan::query()->where('id_instans', $instance->id)->where('id_peringkat', $previousStageId)
                ->where('kitaran', '<', $instance->kitaran)->max('kitaran');
            $previous = Tugasan::query()->where('id_instans', $instance->id)->where('id_peringkat', $previousStageId)
                ->where('kitaran', $lastRound)->where('keputusan', 'lulus')->get();
            if ($this->decidersOf($previous)->intersect($people)->isNotEmpty()) {
                throw new WfeException(__('wfe::wfe.error.sod_berturutan'), 'pengasingan_tugas', 403);
            }
        }
    }

    /**
     * Actors and the people they acted for on these tasks.
     *
     * @param  Collection<int, Tugasan>  $tasks
     * @return Collection<int, int>
     */
    private function decidersOf($tasks)
    {
        if ($tasks->isEmpty()) {
            return collect();
        }
        $behalf = Sejarah::query()->whereIn('id_tugasan', $tasks->pluck('id'))->whereIn('peristiwa', self::ACTIONS)->whereNotNull('id_bagi_pihak')->pluck('id_bagi_pihak');

        return $tasks->pluck('diselesaikan_oleh')->filter()->merge($behalf)->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function mfaFresh(?Carbon $at): bool
    {
        $minutes = (int) $this->parameters->get('wfe.mfa_sah_minit', 10);

        return $at !== null && $at->greaterThanOrEqualTo(now()->subMinutes($minutes)) && $at->lessThanOrEqualTo(now()->addMinute());
    }

    private function defer(Tugasan $task, ?Carbon $until): void
    {
        $max = now()->addDays(90);
        $until = $until === null
            ? Carbon::instance($this->calendar->addWorkingDays(now(), (int) $this->parameters->get('wfe.tangguh_hari_lalai', 5)))
            : $until->copy()->utc(); // stored as UTC, like every other timestamp
        if ($until->lessThanOrEqualTo(now()) || $until->greaterThan($max)) {
            throw ValidationException::withMessages(['tangguh_hingga' => __('wfe::wfe.validation.tangguh_hingga')]);
        }

        $due = $task->tarikh_akhir_sla;
        if ($due !== null) {
            // The SLA clock stops while deferred: what was left continues from $until.
            $left = max(0, $this->calendar->workingMinutesBetween(now(), $due));
            $due = $this->calendar->addWorkingMinutes($until, $left);
        }

        $task->forceFill(['status' => Tugasan::DITANGGUH, 'ditangguh_hingga' => $until, 'tarikh_akhir_sla' => $due, 'amaran_sla_pada' => null])->save();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Tugasan> */
    private function stageTasks(Instans $instance, Peringkat $stage)
    {
        return Tugasan::query()->where('id_instans', $instance->id)->where('id_peringkat', $stage->id)->where('kitaran', $instance->kitaran)->get();
    }

    private function cancelOpenTasks(Instans $instance): void
    {
        Tugasan::query()->where('id_instans', $instance->id)->where('jenis', Tugasan::KELULUSAN)
            ->whereIn('status', [Tugasan::MENUNGGU, Tugasan::DITANGGUH])
            ->update(['status' => Tugasan::DIBATALKAN, 'updated_at' => now()]);
    }

    /**
     * @param  array{id_bagi_pihak?: int|null, peranan_semasa?: list<string>|null, justifikasi?: string|null, alamat_ip?: string|null, mfa_pada?: Carbon|null, butiran?: array<string, mixed>|null}  $extra
     */
    private function history(Instans $instance, string $event, ?Peringkat $stage = null, ?Tugasan $task = null, ?User $user = null, array $extra = []): void
    {
        Sejarah::create([
            'id_instans' => $instance->id,
            'id_tugasan' => $task?->id,
            'id_peringkat' => $stage?->id,
            'nama_peringkat' => $stage?->nama_peringkat,
            'peristiwa' => $event,
            'id_pengguna' => $user?->id,
            'id_bagi_pihak' => $extra['id_bagi_pihak'] ?? null,
            'peranan_semasa' => $extra['peranan_semasa'] ?? null,
            'justifikasi' => $extra['justifikasi'] ?? null,
            'alamat_ip' => $extra['alamat_ip'] ?? null,
            'mfa_pada' => $extra['mfa_pada'] ?? null,
            'butiran' => $extra['butiran'] ?? null,
            'tarikh_masa' => now(),
        ]);
    }
}
