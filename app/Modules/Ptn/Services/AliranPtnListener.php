<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Ptn\Contracts\LotMasterUpdates;
use App\Modules\Ptn\Models\CadanganKemaskiniLot;
use App\Modules\Ptn\Models\LaporanLawatan;
use App\Modules\Ptn\Models\TindakanPembetulan;
use App\Modules\Ptn\Support\AliranPtn;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Throwable;

/** Applies MOD-WFE outcomes for MOD-PTN's three flows (SRS-GEN-03, SRS-WFE-20). */
class AliranPtnListener
{
    public function __construct(private LaporanService $laporan, private TindakanService $tindakan, private LotMasterUpdates $master) {}

    public function handle(WorkflowCompleted $e): void
    {
        match ($e->flowCode) {
            AliranPtn::LAPORAN => $this->laporan($e),
            AliranPtn::TINDAKAN => $this->tindakan($e),
            AliranPtn::KEMASKINI_LOT => $this->cadangan($e),
            default => null,
        };
    }

    private function laporan(WorkflowCompleted $e): void
    {
        $r = LaporanLawatan::query()->withoutGlobalScope('access')->find($e->subjectId);
        if ($r !== null) {
            $this->laporan->keputusan($r, $e->outcome, $e->decidedBy, $e->remarks);
        }
    }

    private function tindakan(WorkflowCompleted $e): void
    {
        $t = TindakanPembetulan::query()->withoutGlobalScope('access')->find($e->subjectId);
        if ($t !== null && $t->status_kemajuan === 'menunggu_pengesahan') {
            $this->tindakan->keputusan($t, $e->outcome, $e->decidedBy, $e->remarks);
        }
    }

    private function cadangan(WorkflowCompleted $e): void
    {
        $c = CadanganKemaskiniLot::query()->withoutGlobalScope('access')->find($e->subjectId);
        if ($c === null || $c->status !== 'menunggu') {
            return;
        }
        if ($e->outcome !== 'lulus') {
            $c->forceFill(['status' => 'tolak', 'id_pelulus' => $e->decidedBy, 'tarikh_keputusan' => now(), 'ulasan_keputusan' => $e->remarks])->save();

            return;
        }
        $approver = $e->decidedBy ? User::query()->find($e->decidedBy) : null;
        try {
            $this->master->apply($c->lot_id, [$c->medan => $c->nilai_dicadang], $approver ?? User::query()->findOrFail($c->id_pencadang), 'Cadangan lawatan PTN #'.$c->id.': '.$c->justifikasi);
            $c->forceFill(['status' => 'lulus', 'id_pelulus' => $e->decidedBy, 'tarikh_keputusan' => now(), 'ulasan_keputusan' => $e->remarks])->save();
        } catch (Throwable $ex) {
            report($ex);
            $c->forceFill(['status' => 'gagal', 'id_pelulus' => $e->decidedBy, 'tarikh_keputusan' => now(), 'ulasan_keputusan' => 'Gagal dikenakan: '.$ex->getMessage()])->save();
        }
    }
}
