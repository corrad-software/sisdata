<?php

namespace App\Modules\Ptn\Reminders;

use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use App\Modules\Ptn\Models\TindakanPembetulan;
use Carbon\CarbonImmutable;

/** SRS-PTN-19: corrective action nearing its target date → responsible officer (offsets configurable in MOD-NTF). */
class TindakanHampirTamat implements ReminderSource
{
    public const TYPE = 'ptn.tindakan_hampir_tamat';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [7, 3, 1];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $rows = TindakanPembetulan::query()->withoutGlobalScope('access')->with(['pegawai', 'isu' => fn ($q) => $q->withoutGlobalScope('access')])
            ->whereIn('status_kemajuan', ['belum_mula', 'dalam_tindakan'])->whereDate('tarikh_sasaran', $today->addDays($daysBefore)->toDateString())->get();
        foreach ($rows as $t) {
            if ($t->pegawai === null) {
                continue;
            }
            yield new Reminder('tindakan_pembetulan:'.$t->id, $t->pegawai, [
                'no_rujukan' => (string) ($t->isu->no_rujukan ?? ''), 'langkah' => mb_substr($t->langkah_tindakan, 0, 120),
                'tarikh_sasaran' => $t->tarikh_sasaran->format('d/m/Y'), 'baki_hari' => $daysBefore,
            ], '/admin/ptn/isu/'.$t->id_isu);
        }
    }
}
