<?php

namespace App\Modules\Pjk\Reminders;

use App\Models\User;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Support\Wang;
use Carbon\CarbonImmutable;

/** Payment due reminders: issued, unpaid charges whose due date is N days away (default 7 and 1). */
class BayaranHampirTamatTempoh implements ReminderSource
{
    public const TYPE = 'pjk.bayaran_perlu_dibayar';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [7, 1];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $date = $today->addDays($daysBefore)->format('Y-m-d');
        $charges = JadualCaj::query()->where('dikeluarkan', true)->whereIn('status_caj', ['belum_bayar', 'separa'])->whereDate('tarikh_akhir', $date)->get()->groupBy('kontrak_id');
        foreach ($charges as $kontrakId => $rows) {
            $k = Kontrak::query()->withoutGlobalScope('access')->find($kontrakId);
            if ($k === null) {
                continue;
            }
            $officer = $k->id_pendaftar ? User::query()->find($k->id_pendaftar) : null;
            $sum = $rows->sum(fn (JadualCaj $c) => $c->bakiSen());
            yield new Reminder(
                'jadual_caj:'.$kontrakId.':'.$date,
                $officer ? [$officer] : [],
                ['no_kontrak' => (string) $k->no_kontrak, 'tarikh_akhir' => CarbonImmutable::parse($date)->format('d/m/Y'), 'amaun' => Wang::rm($sum), 'baki_hari' => $daysBefore],
                '/admin/pjk/kontrak/'.$k->id.'?tab=caj',
            );
        }
    }
}
