<?php

namespace App\Modules\Ptn\Reminders;

use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use App\Modules\Ptn\Models\JadualPemantauan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Reminder to the assigned field officer before a scheduled monitoring visit (offsets editable in MOD-NTF). */
class LawatanDijadualkan implements ReminderSource
{
    public const TYPE = 'ptn.lawatan_dijadualkan';

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
        $rows = JadualPemantauan::query()->withoutGlobalScope('access')->with('pegawai')->where('status_pelaksanaan', 'ditugaskan')
            ->whereNotNull('id_pegawai_bertugas')->whereDate('tarikh_sasaran', $today->addDays($daysBefore)->toDateString())->get();
        $lots = DB::table('lot_tanah')->whereIn('id', $rows->pluck('lot_id')->all())->pluck('no_lot', 'id');
        foreach ($rows as $j) {
            if ($j->pegawai === null) {
                continue;
            }
            yield new Reminder('jadual_pemantauan:'.$j->id, $j->pegawai, [
                'no_lot' => (string) ($lots[$j->lot_id] ?? ''), 'tarikh_sasaran' => $j->tarikh_sasaran->format('d/m/Y'), 'baki_hari' => $daysBefore,
            ], '/admin/ptn/lapangan');
        }
    }
}
