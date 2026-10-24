<?php

namespace App\Modules\Tsu\Reminders;

use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Support\Lalai;
use Carbon\CarbonImmutable;

/** SRS-TSU-03/23: the current stage nears its target date (incl. stages awaiting approval or the Land Office). */
class PeringkatHampirSasaran implements ReminderSource
{
    public const TYPE = 'tsu.peringkat_hampir_sasaran';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [7, 2];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $rows = UrusanPeringkat::query()->with('urusan.pegawai')->whereIn('status', ['aktif', 'menunggu_kelulusan'])
            ->whereDate('tarikh_sasaran', $today->addDays($daysBefore)->toDateString())->get();
        foreach ($rows as $p) {
            $u = $p->urusan;
            if ($u->sintetik || $u->pegawai === null) {
                continue;
            }
            yield new Reminder('urusan_peringkat:'.$p->id, [$u->pegawai], [
                'no_rujukan' => $u->no_rujukan_mais, 'peringkat' => $p->nama_peringkat, 'pihak' => Lalai::PIHAK[$p->pihak_bertanggungjawab] ?? $p->pihak_bertanggungjawab,
                'tarikh_sasaran' => $p->tarikh_sasaran?->format('d/m/Y'), 'baki_hari' => $daysBefore,
            ], '/admin/tsu/urusan/'.$u->id);
        }
    }
}
