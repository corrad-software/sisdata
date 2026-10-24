<?php

namespace App\Modules\Hkm\Reminders;

use App\Models\User;
use App\Modules\Hkm\Models\PengeluaranDokumen;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/**
 * SRS-HKM-07: original documents not returned by the expected date — to the
 * vault custodian, the issuing officer and the head of division (holders of
 * hkm.pemantauan.lihat). Offset 0 = every overdue document, daily until it is
 * returned; a positive offset N = a heads-up N days before the date.
 */
class DokumenLewatDipulangkan implements ReminderSource
{
    public const TYPE = 'hkm.dokumen_lewat';

    public function __construct(private RecipientDirectory $directory) {}

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
        $query = PengeluaranDokumen::query()->where('status_pengeluaran', PengeluaranDokumen::DIKELUARKAN)
            ->with(['daftar.petiBesi.penjaga', 'pegawaiKeluar']);
        $daysBefore === 0
            ? $query->whereDate('tarikh_jangka_pulang', '<', $today->toDateString())
            : $query->whereDate('tarikh_jangka_pulang', $today->addDays($daysBefore)->toDateString());

        $heads = null;
        foreach ($query->orderBy('tarikh_jangka_pulang')->cursor() as $issue) {
            $heads ??= $this->directory->resolve(RecipientDirectory::KEBENARAN, 'hkm.pemantauan.lihat');
            $recipients = collect([$issue->daftar->petiBesi->penjaga, $issue->pegawaiKeluar, ...$heads])
                ->filter()->unique(fn (User $u) => $u->id)->values()->all();

            yield new Reminder(
                'pengeluaran_dokumen:'.$issue->id,
                $recipients,
                [
                    'kod_peti_besi' => $issue->daftar->petiBesi->kod_peti_besi,
                    'no_fail' => $issue->daftar->no_fail ?? '-',
                    'penerima' => $issue->penerima_nama,
                    'tarikh_jangka_pulang' => $issue->tarikh_jangka_pulang->format('d/m/Y'),
                    'hari_lewat' => max(0, (int) $issue->tarikh_jangka_pulang->diffInDays($today, false)),
                ],
                '/admin/hkm/peti-besi/pengeluaran?lewat=1',
            );
        }
    }
}
