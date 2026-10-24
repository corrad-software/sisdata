<?php

namespace App\Modules\Ast\Integrasi\Reminders;

use App\Modules\Ast\Models\AsetJaminan;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/** Warranty expiry reminder to the asset's responsible officer. */
class JaminanTamat implements ReminderSource
{
    public const TYPE = 'ast.jaminan_tamat';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [60, 30, 7];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $rows = AsetJaminan::query()->withoutGlobalScope('access')->with(['aset' => fn ($q) => $q->withoutGlobalScope('access')->with('pegawai')])
            ->where('sintetik', false)->whereDate('tarikh_tamat', $today->addDays($daysBefore)->toDateString())->get();
        foreach ($rows as $j) {
            if ($j->aset === null || $j->aset->status_arkib) {
                continue;
            }
            yield new Reminder('aset_jaminan:'.$j->id, $j->aset->pegawai ?? [], [
                'perkara' => $j->perkara, 'nama_aset' => $j->aset->nama_aset, 'no_rujukan' => $j->aset->no_rujukan_aset,
                'tarikh' => $j->tarikh_tamat->format('d/m/Y'), 'baki_hari' => $daysBefore,
            ], '/admin/ast/aset/'.$j->id_aset);
        }
    }
}
