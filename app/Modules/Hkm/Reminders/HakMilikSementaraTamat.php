<?php

namespace App\Modules\Hkm\Reminders;

use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Support\RefLookup;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/**
 * SRS-HKM-17 (process step 8): temporary titles (HS(D)/HS(M) — ref
 * jenis_hak_milik.sementara and a registered title) expiring in 180, 90 and
 * 30 days (offsets editable by admins in MOD-NTF). TOLs are a separate type.
 */
class HakMilikSementaraTamat implements ReminderSource
{
    public const TYPE = 'hkm.hak_milik_sementara_tamat';

    public function __construct(private RefLookup $ref) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [180, 90, 30];
    }

    /** @return list<string> */
    protected function types(): array
    {
        return array_values(array_map(fn ($j) => $j['kod'], array_filter($this->ref->jenisHakMilik(), fn ($j) => $j['sementara'] && $j['hak_milik'])));
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $types = $this->types();
        if ($types === []) {
            return;
        }

        $titles = HakMilik::query()->withoutGlobalScope('access')
            ->whereIn('jenis_hak_milik', $types)
            ->whereNotIn('status_semasa', ['diganti', 'dilupuskan'])
            ->whereDate('tarikh_tamat_tempoh', $today->addDays($daysBefore)->toDateString())
            ->with('pendaftar')
            ->cursor();

        foreach ($titles as $h) {
            yield new Reminder(
                'hak_milik:'.$h->id,
                $h->pendaftar ? [$h->pendaftar] : [],
                [
                    'jenis' => $this->ref->namaJenis($h->jenis_hak_milik),
                    'kod_daerah' => $h->kod_daerah,
                    'tarikh_tamat' => $h->tarikh_tamat_tempoh?->format('d/m/Y'),
                ],
                '/admin/hkm/hak-milik/'.$h->id,
            );
        }
    }
}
