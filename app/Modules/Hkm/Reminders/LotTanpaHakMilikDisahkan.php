<?php

namespace App\Modules\Hkm\Reminders;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * SRS-HKM-17: lots without a verified title beyond MAIS's target
 * (parameter hkm.sasaran_hari_hak_milik_disahkan). Uses the view
 * vw_lot_tanpa_hak_milik_disahkan; sent when a lot crosses the target and
 * then weekly while it stays unresolved. Recipients come from the type's
 * admin-configured rules (MOD-NTF).
 */
class LotTanpaHakMilikDisahkan implements ReminderSource
{
    public const TYPE = 'hkm.lot_tanpa_hak_milik';

    public function __construct(private Parameters $parameters) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [0];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        if ($daysBefore !== 0) {
            return;
        }

        $target = max(1, (int) $this->parameters->get('hkm.sasaran_hari_hak_milik_disahkan', 180));
        $rows = DB::table('vw_lot_tanpa_hak_milik_disahkan')
            ->whereRaw('(?::date - created_at::date) >= ?', [$today->toDateString(), $target])
            ->whereRaw('mod(?::date - created_at::date - ?, 7) = 0', [$today->toDateString(), $target])
            ->orderBy('created_at')->limit(1000)->get();

        foreach ($rows as $row) {
            yield new Reminder(
                'lot_tanpa_hak_milik:'.$row->id_lot,
                [],
                ['no_lot' => $row->no_lot, 'kod_mukim' => $row->kod_mukim, 'sasaran_hari' => $target],
                '/admin/hkm/laporan?jenis=lot_tanpa_hak_milik',
            );
        }
    }
}
