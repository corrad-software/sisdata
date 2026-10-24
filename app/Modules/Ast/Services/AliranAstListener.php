<?php

namespace App\Modules\Ast\Services;

use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetPelupusan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Support\AliranAst;
use App\Modules\Wfe\Events\WorkflowCompleted;

/** Applies MOD-WFE outcomes for MOD-AST's three flows (SRS-GEN-03, SRS-WFE-20). */
class AliranAstListener
{
    public function __construct(private PelupusanService $pelupusan, private PenyelenggaraanService $penyelenggaraan) {}

    public function handle(WorkflowCompleted $e): void
    {
        match ($e->flowCode) {
            AliranAst::PELUPUSAN => ($p = AsetPelupusan::query()->withoutGlobalScope('access')->find($e->subjectId)) ? $this->pelupusan->keputusan($p, $e->outcome, $e->decidedBy, $e->remarks) : null,
            AliranAst::PEROLEHAN => ($a = AsetFizikal::query()->withoutGlobalScope('access')->find($e->subjectId)) ? $this->pelupusan->keputusanPerolehan($a, $e->outcome, $e->remarks) : null,
            AliranAst::PENYELENGGARAAN => ($k = RekodPenyelenggaraan::query()->withoutGlobalScope('access')->find($e->subjectId)) ? $this->penyelenggaraan->keputusan($k, $e->outcome, $e->decidedBy, $e->remarks) : null,
            default => null,
        };
    }
}
