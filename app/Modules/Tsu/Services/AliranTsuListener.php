<?php

namespace App\Modules\Tsu\Services;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Tsu\Models\PemindahanRekod;
use App\Modules\Tsu\Models\SebutHargaUkur;
use App\Modules\Tsu\Models\UrusanKeputusan;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\AliranTsu;
use App\Modules\Wfe\Events\StepDecided;
use App\Modules\Wfe\Events\WorkflowCompleted;

/**
 * Applies MOD-WFE outcomes for MOD-TSU's flows (SRS-GEN-03, SRS-WFE-20) and
 * keeps every approver's step in the immutable decision history
 * (urusan_keputusan, SRS-TSU-24) with justification and MFA indicator.
 */
class AliranTsuListener
{
    public const FLOWS = [AliranTsu::URUSAN, AliranTsu::PELUPUSAN, AliranTsu::LANTIKAN, AliranTsu::PEMINDAHAN];

    public function __construct(
        private UrusanService $urusan,
        private SebutHargaService $sebutHarga,
        private PemindahanService $pemindahan,
        private Parameters $parameters,
    ) {}

    public function completed(WorkflowCompleted $e): void
    {
        if (! in_array($e->flowCode, self::FLOWS, true)) {
            return;
        }
        match ($e->subjectType) {
            AliranTsu::SUBJEK_PERINGKAT => ($s = UrusanPeringkat::query()->find((int) $e->subjectId)) ? $this->urusan->onApproval($s, $e->outcome, $e->decidedBy, $e->remarks, $e->instanceId) : null,
            AliranTsu::SUBJEK_SEBUTHARGA => ($sh = SebutHargaUkur::query()->find($e->subjectId)) ? $this->sebutHarga->onApproval($sh, $e->outcome, $e->decidedBy, $e->remarks) : null,
            AliranTsu::SUBJEK_PEMINDAHAN => ($u = UrusanTanah::query()->withoutGlobalScope('access')->find($e->subjectId)) ? $this->pemindahan->onCompleted($u, $e->instanceId, $e->outcome, $e->decidedBy, $e->remarks) : null,
            default => null,
        };
    }

    public function step(StepDecided $e): void
    {
        if (! in_array($e->flowCode, self::FLOWS, true)) {
            return;
        }
        [$urusanId, $peringkatId, $konteks] = $this->subject($e);
        if ($urusanId === null) {
            return;
        }
        if ($e->flowCode === AliranTsu::PEMINDAHAN) {
            $this->pemindahan->onStep($e->instanceId, $e->stageCode, $e->action, $e->userId);
        }
        $mfaStages = (array) $this->parameters->get('tsu.peringkat_wfe_mfa', ['kelulusan_ketua_bahagian', AliranTsu::PERINGKAT_TERTINGGI]);
        UrusanKeputusan::query()->create([
            'urusan_id' => $urusanId, 'peringkat_id' => $peringkatId, 'konteks' => $konteks, 'jenis_keputusan' => $e->action,
            'kod_peringkat_wfe' => $e->stageCode, 'justifikasi' => $e->justification, 'id_pelulus' => $e->userId, 'id_aliran' => $e->instanceId,
            // MFA is enforced by MOD-WFE on the stages configured perlu_mfa; this records that the decision was taken on such a stage.
            'indikator_mfa' => $e->action === 'lulus' && in_array($e->stageCode, $mfaStages, true),
        ]);
    }

    /** @return array{0: string|null, 1: int|null, 2: string} */
    private function subject(StepDecided $e): array
    {
        if (in_array($e->flowCode, [AliranTsu::URUSAN, AliranTsu::PELUPUSAN], true)) {
            $s = UrusanPeringkat::query()->where('id_aliran_kerja', $e->instanceId)->first();

            return [$s?->urusan_id, $s?->id, 'urusan'];
        }
        if ($e->flowCode === AliranTsu::LANTIKAN) {
            return [SebutHargaUkur::query()->where('id_aliran_kerja', $e->instanceId)->value('urusan_id'), null, 'sebutharga'];
        }

        return [PemindahanRekod::query()->where('id_aliran_kerja', $e->instanceId)->value('urusan_id'), null, 'pemindahan'];
    }
}
