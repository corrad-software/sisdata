<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\KosGuaman;
use App\Modules\Kes\Models\Peguam;
use App\Modules\Kes\Models\Penyelesaian;
use App\Modules\Kes\Models\Rayuan;
use App\Modules\Kes\Models\TindakanSusulan;
use App\Modules\Kes\Support\Aliran;
use App\Modules\Wfe\Events\WorkflowCompleted;

/** Applies MOD-WFE outcomes of the six MOD-KES flows (SRS-GEN-03 — WFE decides, KES applies). */
class AliranKes
{
    public function __construct(
        private KesService $kes,
        private KosService $kos,
        private KeputusanService $keputusan,
        private PanelService $panel,
    ) {}

    public function handle(WorkflowCompleted $e): void
    {
        if (! array_key_exists($e->flowCode, Aliran::FLOWS)) {
            return;
        }
        $by = $e->decidedBy !== null ? User::query()->find($e->decidedBy) : null;
        $match = fn ($model) => $model !== null && $model->id_instans_aliran === $e->instanceId;

        switch ($e->flowCode) {
            case 'kes.pemfailan':
                $k = KesMahkamah::query()->withoutGlobalScope('access')->find($e->subjectId);
                if ($match($k)) {
                    $this->kes->selesaiPemfailan($k, $e->outcome, $by, $e->remarks);
                }
                break;
            case 'kes.bayaran_kos':
                $k = KosGuaman::query()->find($e->subjectId);
                if ($match($k)) {
                    $this->kos->selesaiKelulusan($k, $e->outcome);
                }
                break;
            case 'kes.lantikan_panel':
                $p = Peguam::query()->find($e->subjectId);
                if ($match($p)) {
                    $this->panel->selesai($p, $e->outcome);
                }
                break;
            case 'kes.rayuan':
                $r = Rayuan::query()->find($e->subjectId);
                if ($match($r)) {
                    $this->keputusan->selesaiRayuan($r, $e->outcome, $by);
                }
                break;
            case 'kes.penyelesaian':
                $p = Penyelesaian::query()->find($e->subjectId);
                if ($match($p)) {
                    $this->keputusan->selesaiPenyelesaian($p, $e->outcome);
                }
                break;
            case 'kes.tindakan_susulan':
                $t = TindakanSusulan::query()->find($e->subjectId);
                if ($t !== null && $t->id_tugasan_workflow === $e->instanceId) {
                    $this->keputusan->selesaiTindakan($t, $e->outcome);
                }
                break;
        }
    }
}
