<?php

namespace App\Modules\Per\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Per\Models\Kelulusan;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PeringkatPemerolehan;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\StepDecided;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SRS-PER-29 / SRS-GEN-03: layered approval by channel and value runs in
 * MOD-WFE — one flow, `per.kelulusan`, whose branch conditions test
 * `kod_saluran`, `nilai_pemerolehan`, `kod_bahagian` and `kod_daerah`
 * (admins configure layers in the designer; a recommended definition ships
 * in Database/wfe/per.kelulusan.json). MOD-PER only starts it and applies
 * the outcome.
 *
 * Segregation of duties: the case registrar is the WFE applicant (WFE never
 * lets the applicant approve), and the outcome is refused here too if the
 * registrar or the submitter approved any layer.
 */
class ApprovalService
{
    public const FLOW = 'per.kelulusan';

    public const SUBJECT = 'pemerolehan';

    public function __construct(
        private WorkflowEngine $engine,
        private AuditLogger $audit,
    ) {}

    public function start(Pemerolehan $case, User $submitter): string
    {
        $initiator = $case->pendaftar ?? $submitter;

        try {
            $instance = $this->engine->start(self::FLOW, self::SUBJECT, $case->id, $initiator, [
                'kod_saluran' => $case->kodSaluran(),
                'nilai_pemerolehan' => $case->nilaiUntukKelulusan(),
                'kod_bahagian' => $case->kod_bahagian,
                'kod_daerah' => $case->kod_daerah,
                'pautan' => $case->pautan(),
            ], __('per::per.tajuk_kelulusan', ['no_kes' => $case->no_kes, 'saluran' => $case->saluran->nama_saluran]));
        } catch (InvalidArgumentException) {
            throw new HttpException(503, __('per::per.aliran_tiada'));
        }

        $case->forceFill(['id_aliran_kerja' => $instance, 'status_pemerolehan' => Pemerolehan::MENUNGGU_KELULUSAN])->save();

        Kelulusan::query()->create([
            'id_pemerolehan' => $case->id,
            'id_aliran_kerja' => $instance,
            'aras_kelulusan' => 'penghantaran',
            'id_pegawai_pelulus' => $submitter->id,
            'jawatan_pelulus' => $submitter->jawatan,
            'tindakan' => 'hantar',
            'tarikh_masa' => now(),
        ]);

        return $instance;
    }

    /** Listener: keep each WFE layer decision in the case's own approval history. */
    public function onStepDecided(StepDecided $event): void
    {
        if ($event->flowCode !== self::FLOW) {
            return;
        }
        $case = Pemerolehan::query()->withoutGlobalScope('access')->where('id_aliran_kerja', $event->instanceId)->first();
        if ($case === null) {
            return;
        }

        $user = User::query()->find($event->userId);
        Kelulusan::query()->create([
            'id_pemerolehan' => $case->id,
            'id_aliran_kerja' => $event->instanceId,
            'aras_kelulusan' => $event->stageCode ?? 'peringkat',
            'id_pegawai_pelulus' => $event->userId,
            'jawatan_pelulus' => $user?->jawatan,
            'tindakan' => $event->action,
            'tarikh_masa' => now(),
            'justifikasi' => $event->justification,
        ]);
    }

    /** Listener: apply the final outcome (SRS-WFE-20). */
    public function onWorkflowCompleted(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== self::FLOW || $event->subjectType !== self::SUBJECT) {
            return;
        }

        DB::transaction(function () use ($event) {
            $case = Pemerolehan::query()->withoutGlobalScope('access')->with(['peringkat', 'saluran'])
                ->whereKey($event->subjectId)->where('id_aliran_kerja', $event->instanceId)->lockForUpdate()->first();
            if ($case === null || $case->status_pemerolehan !== Pemerolehan::MENUNGGU_KELULUSAN) {
                return;
            }

            $approved = $event->outcome === 'lulus';
            $remarks = $event->remarks;
            if ($approved && ($violator = $this->sodViolation($case, $event)) !== null) {
                $approved = false;
                $remarks = trim(($remarks ?? '').' '.__('per::per.sod_ditolak', ['peranan' => $violator]));
            }

            Kelulusan::query()->create([
                'id_pemerolehan' => $case->id,
                'id_aliran_kerja' => $event->instanceId,
                'aras_kelulusan' => 'keputusan_akhir',
                'id_pegawai_pelulus' => $event->decidedBy,
                'jawatan_pelulus' => $event->decidedBy ? User::query()->find($event->decidedBy)?->jawatan : null,
                'tindakan' => $approved ? 'lulus' : ($event->outcome === 'lulus' ? 'tolak' : $event->outcome),
                'tarikh_masa' => now(),
                'justifikasi' => $remarks,
            ]);

            if ($approved) {
                $this->advancePastApproval($case);
                $case->forceFill(['status_pemerolehan' => Pemerolehan::DILULUSKAN])->save();
            } else {
                $case->forceFill([
                    'status_pemerolehan' => in_array($event->outcome, ['ditarik_balik', 'dibatalkan'], true) ? Pemerolehan::DALAM_PROSES : Pemerolehan::DITOLAK,
                ])->save();
            }

            $this->audit->record($approved ? 'lulus' : 'tolak', 'per', 'pemerolehan', $case->id, reason: $remarks, meta: [
                'no_kes' => $case->no_kes, 'id_aliran_kerja' => $event->instanceId, 'keputusan_wfe' => $event->outcome,
                'saluran' => $case->kodSaluran(), 'klasifikasi' => 'sulit',
            ]);
        });
    }

    private function sodViolation(Pemerolehan $case, WorkflowCompleted $event): ?string
    {
        $approvers = Kelulusan::query()->where('id_aliran_kerja', $event->instanceId)->where('tindakan', 'lulus')
            ->pluck('id_pegawai_pelulus')->filter()->map(fn ($id) => (int) $id)->all();
        if ($event->decidedBy !== null) {
            $approvers[] = $event->decidedBy;
        }
        $submitter = Kelulusan::query()->where('id_aliran_kerja', $event->instanceId)->where('tindakan', 'hantar')->value('id_pegawai_pelulus');

        if ($case->id_pendaftar !== null && in_array($case->id_pendaftar, $approvers, true)) {
            return __('per::per.peranan_pendaftar');
        }
        if ($submitter !== null && in_array((int) $submitter, $approvers, true)) {
            return __('per::per.peranan_penghantar');
        }

        return null;
    }

    private function advancePastApproval(Pemerolehan $case): void
    {
        $current = $case->peringkatSemasa();
        if ($current === null || $current->jenis !== 'kelulusan') {
            return;
        }
        $next = $case->peringkat->first(fn (PeringkatPemerolehan $s) => $s->susunan > $current->susunan);

        app(StageMover::class)->move($case, $current, $next, null);
    }
}
