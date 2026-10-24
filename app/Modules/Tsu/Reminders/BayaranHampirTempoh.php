<?php

namespace App\Modules\Tsu\Reminders;

use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Support\Lalai;
use Carbon\CarbonImmutable;

/** SRS-TSU-23: premium / fee payment falling due → responsible officer (+ Bahagian Kewangan via the type's recipient rules). */
class BayaranHampirTempoh implements ReminderSource
{
    public const TYPE = 'tsu.bayaran_hampir_tempoh';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [14, 7, 3, 1];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $rows = KosUrusan::query()->with(['urusan.pegawai'])->whereIn('status_bayaran', ['belum_bayar', 'dalam_proses'])
            ->whereDate('tarikh_akhir_bayaran', $today->addDays($daysBefore)->toDateString())->get();
        foreach ($rows as $k) {
            if ($k->urusan->dikunci || $k->urusan->sintetik) {
                continue;
            }
            yield new Reminder('kos_urusan:'.$k->id, $k->urusan->pegawai ? [$k->urusan->pegawai] : [], [
                'no_rujukan' => $k->urusan->no_rujukan_mais, 'kategori' => Lalai::KATEGORI_KOS[$k->kategori_kos] ?? $k->kategori_kos,
                'tarikh_akhir' => $k->tarikh_akhir_bayaran?->format('d/m/Y'), 'baki_hari' => $daysBefore,
            ], '/admin/tsu/urusan/'.$k->urusan_id.'?tab=kos');
        }
    }
}
