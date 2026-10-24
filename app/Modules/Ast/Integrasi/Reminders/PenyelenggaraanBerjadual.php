<?php

namespace App\Modules\Ast\Integrasi\Reminders;

use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/**
 * SRS-AST-16 / SRS-NTF-07 "jadual penyelenggaraan aset": reminder to the
 * responsible officer before a scheduled maintenance. MOD-NTF asks once per
 * configured offset (admins edit the offsets); a schedule only answers for
 * offsets inside its own warning period (tempoh_amaran_hari), so the warning
 * period stays configurable per schedule.
 */
class PenyelenggaraanBerjadual implements ReminderSource
{
    public const TYPE = 'ast.penyelenggaraan_berjadual';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [30, 14, 7, 3, 1];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $rows = JadualPenyelenggaraan::query()->withoutGlobalScope('access')->with(['pegawai', 'aset' => fn ($q) => $q->withoutGlobalScope('access')])
            ->where('status', 'aktif')->where('tempoh_amaran_hari', '>=', $daysBefore)
            ->whereDate('tarikh_jatuh_tempoh', $today->addDays($daysBefore)->toDateString())->get();
        foreach ($rows as $j) {
            if ($j->aset === null || $j->aset->status_arkib || $j->sintetik) {
                continue;
            }
            yield new Reminder('jadual_penyelenggaraan:'.$j->id.':'.$j->tarikh_jatuh_tempoh->toDateString(), $j->pegawai ?? [], [
                'jenis' => Lalai::JENIS_PENYELENGGARAAN[$j->jenis_penyelenggaraan] ?? $j->jenis_penyelenggaraan, 'nama_aset' => $j->aset->nama_aset,
                'no_rujukan' => $j->aset->no_rujukan_aset, 'tarikh' => $j->tarikh_jatuh_tempoh->format('d/m/Y'), 'baki_hari' => $daysBefore,
            ], '/admin/ast/aset/'.$j->id_aset);
        }
    }
}
