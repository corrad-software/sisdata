<?php

namespace Tests\Modules\Pro\Support;

use App\Models\User;
use App\Modules\Pro\Contracts\FinancialSeries;
use App\Modules\Pro\Contracts\ProfileSection;

/** What MOD-HKM (titles) or a G3 module would register: a section + timeline + money events. */
class FakeTitleSection implements FinancialSeries, ProfileSection
{
    public function key(): string
    {
        return 'hak_milik';
    }

    public function tab(): string
    {
        return 'identiti';
    }

    public function label(): string
    {
        return 'Hak Milik';
    }

    public function forLot(string $lotId, User $user): ?array
    {
        return ['items' => [['label' => 'No. hak milik', 'nilai' => 'GRN 12345']], 'flags' => [['level' => 'warning', 'label' => 'Hak milik tamat dalam 90 hari']], 'link' => '/admin/hkm/hak-milik/1'];
    }

    public function timeline(string $lotId, User $user): array
    {
        return [['date' => '2001-05-01', 'type' => 'pendaftaran_hak_milik', 'title' => 'GRN 12345 didaftarkan', 'link' => '/admin/hkm/hak-milik/1']];
    }

    public function financialSeries(string $lotId, User $user): array
    {
        return [
            ['tarikh' => '2026-01-15', 'jenis' => 'hasil_sewaan', 'amaun' => 1000.0],
            ['tarikh' => '2026-01-20', 'jenis' => 'kos_cukai', 'amaun' => 150.0],
            ['tarikh' => '2026-02-15', 'jenis' => 'hasil_sewaan', 'amaun' => 1000.0],
            ['tarikh' => '2025-12-01', 'jenis' => 'kos_guaman', 'amaun' => 300.0],
        ];
    }
}
