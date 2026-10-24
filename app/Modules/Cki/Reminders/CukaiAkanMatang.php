<?php

namespace App\Modules\Cki\Reminders;

use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Penerima;
use App\Modules\Cki\Support\Wang;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/**
 * SRS-CKI-17 / SRS-NTF-07 "cukai akan matang": reminders exactly 30, 14 and 7
 * days before a bill's due date (defaults — admins change the offsets on the
 * notification type, ntf_jenis.hari_peringatan) for every unpaid bill, sent
 * to the district's tax officers.
 */
class CukaiAkanMatang implements ReminderSource
{
    public const TYPE = 'cki.cukai_akan_matang';

    public function __construct(private Penerima $penerima) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [30, 14, 7];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $date = $today->addDays($daysBefore)->format('Y-m-d');
        $bills = Cukai::query()->withoutGlobalScope('access')->whereNotNull('obligasi_id')->where('status_bayaran', '<>', 'dibayar')
            ->whereDate('tarikh_akhir', $date)->orderBy('id')->get();
        foreach ($bills as $c) {
            yield new Reminder(
                'cukai:'.$c->id,
                $this->penerima->untuk('cki.bil.lihat', $c->kod_daerah),
                [
                    'no_bil' => (string) $c->no_bil, 'jenis_cukai' => Kod::JENIS_CUKAI[$c->jenis] ?? $c->jenis, 'amaun' => Wang::rm($c->baki()),
                    'tarikh_akhir' => CarbonImmutable::parse($date)->format('d/m/Y'), 'baki_hari' => $daysBefore, 'kod_daerah' => (string) $c->kod_daerah,
                ],
                '/admin/cki/bil/'.$c->id,
            );
        }
    }
}
