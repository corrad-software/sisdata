<?php

namespace App\Modules\Ast\Integrasi\Reminders;

use App\Modules\Ast\Models\AsetInsurans;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/** Insurance policy expiry reminder to the asset's responsible officer. */
class InsuransTamat implements ReminderSource
{
    public const TYPE = 'ast.insurans_tamat';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [60, 30, 14, 1];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $rows = AsetInsurans::query()->withoutGlobalScope('access')->with(['aset' => fn ($q) => $q->withoutGlobalScope('access')->with('pegawai')])
            ->where('sintetik', false)->whereDate('tarikh_tamat', $today->addDays($daysBefore)->toDateString())->get();
        foreach ($rows as $p) {
            if ($p->aset === null || $p->aset->status_arkib) {
                continue;
            }
            yield new Reminder('aset_insurans:'.$p->id, $p->aset->pegawai ?? [], [
                'no_polisi' => $p->no_polisi, 'nama_aset' => $p->aset->nama_aset, 'no_rujukan' => $p->aset->no_rujukan_aset,
                'tarikh' => $p->tarikh_tamat->format('d/m/Y'), 'baki_hari' => $daysBefore,
            ], '/admin/ast/aset/'.$p->id_aset);
        }
    }
}
