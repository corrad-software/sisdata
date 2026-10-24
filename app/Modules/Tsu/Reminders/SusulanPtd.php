<?php

namespace App\Modules\Tsu\Reminders;

use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;
use Carbon\CarbonImmutable;

/** SRS-TSU-22: automatic follow-up reminder for urusan whose Land Office status is updated manually. */
class SusulanPtd implements ReminderSource
{
    public const TYPE = 'tsu.susulan_ptd';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [1, 0];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $rows = UrusanTanah::query()->withoutGlobalScope('access')->with('pegawai')->whereIn('status', Lalai::URUSAN_TERBUKA)->where('sintetik', false)
            ->where('sumber_status_ptd', 'manual')->whereDate('tarikh_susulan_ptd', $today->addDays($daysBefore)->toDateString())->get();
        foreach ($rows as $u) {
            if ($u->pegawai === null) {
                continue;
            }
            yield new Reminder('urusan_tanah:susulan:'.$u->id.':'.$u->tarikh_susulan_ptd?->format('Ymd'), [$u->pegawai], [
                'no_rujukan' => $u->no_rujukan_mais, 'status' => $u->status_ptd ? (Lalai::STATUS_PTD[$u->status_ptd] ?? $u->status_ptd) : 'belum dikemas kini',
                'pejabat_tanah' => $u->pejabat_tanah ?? 'Pejabat Tanah Daerah', 'tarikh_susulan' => $u->tarikh_susulan_ptd?->format('d/m/Y'),
            ], '/admin/tsu/urusan/'.$u->id.'?tab=ptd');
        }
    }
}
