<?php

namespace App\Modules\Adu\Reminders;

use App\Modules\Adu\Models\LawatanTapak;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/** SRS-ADU-17/23: reminder to the officer before a scheduled complaint site visit. */
class LawatanTapakAduan implements ReminderSource
{
    public const TYPE = 'adu.lawatan_tapak';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [1];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $visits = LawatanTapak::query()->with(['pegawai', 'aduan' => fn ($q) => $q->withoutGlobalScope('access')])
            ->where('status', 'dijadualkan')->whereDate('tarikh_jadual', $today->addDays($daysBefore)->toDateString())->get();

        foreach ($visits as $v) {
            if ($v->pegawai === null) {
                continue;
            }
            yield new Reminder('lawatan_tapak_aduan:'.$v->id, $v->pegawai, [
                'no_rujukan' => $v->no_rujukan_aduan,
                'tarikh_jadual' => $v->tarikh_jadual->format('d/m/Y'),
                'baki_hari' => $daysBefore,
            ], '/admin/adu/aduan/'.$v->aduan_id);
        }
    }
}
