<?php

namespace App\Modules\Pjk\Reminders;

use App\Models\User;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PremisAset;
use Carbon\CarbonImmutable;

/**
 * SRS-PJK-08: contract expiry reminders at 6, 3 and 1 month(s) (180 / 90 / 30
 * days) before the end date. The offsets live on the NTF type
 * (ntf_jenis.hari_peringatan) and are edited by admins without code.
 */
class KontrakTamatTempoh implements ReminderSource
{
    public const TYPE = 'pjk.kontrak_tamat';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [180, 90, 30];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $date = $today->addDays($daysBefore)->format('Y-m-d');
        foreach (Kontrak::query()->withoutGlobalScope('access')->whereIn('status', ['aktif', 'hampir_tamat'])->whereDate('tarikh_tamat', $date)->get() as $k) {
            $premis = PremisAset::query()->withoutGlobalScope('access')->find($k->premis_id);
            $officer = $k->id_pendaftar ? User::query()->find($k->id_pendaftar) : null;
            yield new Reminder(
                'kontrak_pajakan:'.$k->id,
                $officer ? [$officer] : [],
                ['no_kontrak' => (string) $k->no_kontrak, 'nama_premis' => (string) ($premis->nama_premis ?? '—'), 'kod_daerah' => (string) $k->kod_daerah, 'tarikh_tamat' => $k->tarikh_tamat?->format('d/m/Y'), 'baki_hari' => $daysBefore],
                '/admin/pjk/kontrak/'.$k->id,
            );
        }
    }
}
