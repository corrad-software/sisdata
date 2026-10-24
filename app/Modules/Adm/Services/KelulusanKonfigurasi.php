<?php

namespace App\Modules\Adm\Services;

use App\Models\User;
use App\Modules\Adm\Models\KonfigKelulusan;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * SRS-ADM-18: a critical configuration change is stored as "menunggu" and
 * only takes effect when a SECOND administrator approves it. SRS-ADM-19:
 * the request keeps before/after, justification, requester (+ IP) and
 * approver; the approval decision is written to the audit trail.
 *
 * Approval runs through MOD-WFE flow `adm.konfigurasi_kritikal` when a
 * WorkflowEngine is bound and the flow is active; the outcome comes back as
 * Events\WorkflowCompleted. Without WFE (or without an active flow) ADM falls
 * back to a built-in two-person rule: any holder of adm.konfigurasi.lulus
 * other than the requester decides from the "Kelulusan Konfigurasi" screen.
 */
class KelulusanKonfigurasi
{
    public const FLOW = 'adm.konfigurasi_kritikal';

    public const SUBJECT = 'adm_konfig_kelulusan';

    public const PELULUS = 'adm.konfigurasi.lulus';

    public const SECRET_MASK = '••••••••';

    public function __construct(
        private Container $app,
        private ParameterService $parameters,
        private IntegrasiService $integrations,
        private SecretStore $secrets,
        private AuditLogger $audit,
        private Notifier $notifier,
        private Amaran $amaran,
    ) {}

    public function usesWorkflow(): bool
    {
        return $this->app->bound(WorkflowEngine::class);
    }

    public function request(string $type, string $key, mixed $before, mixed $after, string $justification, User $requester, ?string $secret = null): KonfigKelulusan
    {
        $pending = KonfigKelulusan::query()
            ->where('jenis_konfig', $type)->where('kunci', $key)->where('status', KonfigKelulusan::MENUNGGU)
            ->exists();
        if ($pending) {
            throw new HttpException(409, __('adm::adm.kelulusan.already_pending'));
        }

        $request = KonfigKelulusan::query()->create([
            'jenis_konfig' => $type,
            'kunci' => $key,
            'nilai_sebelum' => $before,
            'nilai_selepas' => $secret !== null ? ['nilai' => self::SECRET_MASK, 'keterangan' => is_array($after) ? ($after['keterangan'] ?? null) : null] : $after,
            'nilai_rahsia_tersulit' => $secret !== null ? Crypt::encryptString($secret) : null,
            'justifikasi' => $justification,
            'id_pemohon' => $requester->id,
            'alamat_ip_pemohon' => app()->bound('request') ? request()->ip() : null,
            'status' => KonfigKelulusan::MENUNGGU,
        ]);

        $instance = $this->startWorkflow($request, $requester);
        if ($instance !== null) {
            $request->update(['id_instans_aliran' => $instance]);
        } else {
            // Built-in fallback: tell every other approver there is something to decide.
            $this->amaran->toPermission(self::PELULUS, 'adm.kelulusan_konfigurasi', [
                'jenis' => $type,
                'kunci' => $key,
                'pemohon' => $requester->name,
            ], '/admin/pentadbiran/kelulusan', 'kelulusan:'.$request->id, $requester->id);
        }

        return $request;
    }

    public function approve(KonfigKelulusan $request, User $approver, ?string $remarks, bool $viaWorkflow = false): KonfigKelulusan
    {
        $this->guardDecision($request, $approver, $viaWorkflow);

        DB::transaction(function () use ($request, $approver, $remarks) {
            $this->apply($request);
            $request->update([
                'status' => KonfigKelulusan::LULUS,
                'id_pelulus' => $approver->id,
                'catatan_pelulus' => $remarks,
                'diputuskan_pada' => now(),
                'nilai_rahsia_tersulit' => null, // applied: the only copy is now in adm_rahsia
            ]);
        });

        $this->record('lulus', $request, $remarks);
        $this->notifyRequester($request);

        return $request;
    }

    public function reject(KonfigKelulusan $request, User $approver, ?string $remarks, bool $viaWorkflow = false): KonfigKelulusan
    {
        $this->guardDecision($request, $approver, $viaWorkflow);

        $request->update([
            'status' => KonfigKelulusan::TOLAK,
            'id_pelulus' => $approver->id,
            'catatan_pelulus' => $remarks,
            'diputuskan_pada' => now(),
            'nilai_rahsia_tersulit' => null,
        ]);

        $this->record('tolak', $request, $remarks);
        $this->notifyRequester($request);

        return $request;
    }

    public function cancel(KonfigKelulusan $request, User $user): KonfigKelulusan
    {
        if ($request->status !== KonfigKelulusan::MENUNGGU) {
            throw new HttpException(409, __('adm::adm.kelulusan.not_pending'));
        }
        if ($request->id_pemohon !== $user->id) {
            throw new HttpException(403, __('adm::adm.kelulusan.only_requester_cancels'));
        }

        $request->update(['status' => KonfigKelulusan::DIBATALKAN, 'diputuskan_pada' => now(), 'nilai_rahsia_tersulit' => null]);

        return $request;
    }

    /** Listener for MOD-WFE's outcome. */
    public function onWorkflowCompleted(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== self::FLOW || $event->subjectType !== self::SUBJECT) {
            return;
        }

        $request = KonfigKelulusan::query()->find($event->subjectId);
        if (! $request || $request->status !== KonfigKelulusan::MENUNGGU) {
            return;
        }

        $decider = $event->decidedBy ? User::query()->find($event->decidedBy) : null;

        try {
            match ($event->outcome) {
                'lulus' => $decider
                    ? $this->approve($request, $decider, $event->remarks, true)
                    : throw new HttpException(422, 'Workflow approval without a decider.'),
                'tolak' => $decider
                    ? $this->reject($request, $decider, $event->remarks, true)
                    : $request->update(['status' => KonfigKelulusan::TOLAK, 'catatan_pelulus' => $event->remarks, 'diputuskan_pada' => now(), 'nilai_rahsia_tersulit' => null]),
                default => $request->update(['status' => KonfigKelulusan::DIBATALKAN, 'catatan_pelulus' => $event->remarks, 'diputuskan_pada' => now(), 'nilai_rahsia_tersulit' => null]),
            };
        } catch (HttpException $e) {
            // e.g. the workflow let the requester approve their own change: refuse to apply it.
            Log::error('Critical config approval from workflow refused: '.$e->getMessage(), ['modul' => 'adm', 'kod_ralat' => 'ADM_KELULUSAN_DITOLAK_SISTEM', 'id_permohonan' => $request->id]);
        }
    }

    private function guardDecision(KonfigKelulusan $request, User $approver, bool $viaWorkflow): void
    {
        if ($request->status !== KonfigKelulusan::MENUNGGU) {
            throw new HttpException(409, __('adm::adm.kelulusan.not_pending'));
        }
        if ($request->id_pemohon === $approver->id) {
            throw new HttpException(403, __('adm::adm.kelulusan.second_admin_required'));
        }
        if (! $viaWorkflow && $request->id_instans_aliran !== null && $this->usesWorkflow()) {
            throw new HttpException(409, __('adm::adm.kelulusan.decide_in_workflow'));
        }
    }

    private function apply(KonfigKelulusan $request): void
    {
        $requester = User::query()->find($request->id_pemohon);
        $justification = $request->justifikasi;

        match ($request->jenis_konfig) {
            'parameter' => $this->parameters->store($request->kunci, $request->nilai_selepas, $justification, $requester),
            'parameter_reset' => $this->parameters->reset($request->kunci, $justification, $requester),
            'integrasi' => $this->integrations->apply($request->kunci, (array) $request->nilai_selepas, $justification, $requester),
            'rahsia' => $this->secrets->put($request->kunci, Crypt::decryptString((string) $request->nilai_rahsia_tersulit), $request->nilai_selepas['keterangan'] ?? null, $requester),
            default => throw new InvalidArgumentException("Unknown config type [{$request->jenis_konfig}]."),
        };
    }

    private function startWorkflow(KonfigKelulusan $request, User $requester): ?string
    {
        if (! $this->usesWorkflow()) {
            return null;
        }

        try {
            return $this->app->make(WorkflowEngine::class)->start(
                self::FLOW,
                self::SUBJECT,
                $request->id,
                $requester,
                ['jenis_konfig' => $request->jenis_konfig, 'kunci' => $request->kunci],
                __('adm::adm.kelulusan.workflow_title', ['kunci' => $request->kunci]),
            );
        } catch (InvalidArgumentException) {
            return null; // flow not configured yet → built-in two-person approval
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function record(string $action, KonfigKelulusan $request, ?string $remarks): void
    {
        $this->audit->record(
            action: $action,
            module: 'adm',
            entityType: self::SUBJECT,
            entityId: $request->id,
            reason: $request->justifikasi,
            changes: [$request->kunci => [$request->nilai_sebelum, $request->nilai_selepas]],
            meta: [
                'jenis_konfig' => $request->jenis_konfig,
                'id_pemohon' => $request->id_pemohon,
                'alamat_ip_pemohon' => $request->alamat_ip_pemohon,
                'catatan_pelulus' => $remarks,
            ],
        );
    }

    private function notifyRequester(KonfigKelulusan $request): void
    {
        $requester = User::query()->find($request->id_pemohon);
        if (! $requester) {
            return;
        }

        try {
            $this->notifier->send('adm.keputusan_konfigurasi', $requester, [
                'kunci' => $request->kunci,
                'keputusan' => $request->status,
            ], '/admin/pentadbiran/kelulusan');
        } catch (Throwable $e) {
            report($e);
        }
    }
}
