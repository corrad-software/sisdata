<?php

namespace App\Modules\Per\Integration;

use App\Modules\Per\Contracts\EFaraid;

/**
 * Local stand-in for e-Faraid MAIS: returns SYNTHETIC, deterministic heirs for
 * any case number matching FRD-…; "FRD-TIADA…" simulates "not found".
 */
class MockEFaraid implements EFaraid
{
    private const NAMA = ['Ahmad', 'Siti', 'Mohd Rizal', 'Nur Aina', 'Hafiz', 'Zulaikha', 'Kamal', 'Faridah'];

    public function lookup(string $faraidCaseNumber): ?array
    {
        if (! preg_match('/^FRD-/i', $faraidCaseNumber) || str_starts_with(strtoupper($faraidCaseNumber), 'FRD-TIADA')) {
            return null;
        }

        $seed = crc32($faraidCaseNumber);
        $count = 2 + $seed % 3;
        $heirs = [];
        for ($i = 0; $i < $count; $i++) {
            $heirs[] = [
                'nama' => self::NAMA[($seed + $i) % count(self::NAMA)].' (SINTETIK)',
                'hubungan' => $i === 0 ? 'Isteri' : 'Anak',
                'no_pengenalan' => sprintf('9%05d-10-%04d', ($seed + $i) % 100000, ($seed >> 3) % 10000),
                'bahagian' => $i === 0 ? '1/8' : 'Asabah',
            ];
        }

        return [
            'no_kes_faraid' => strtoupper($faraidCaseNumber),
            'nama_si_mati' => 'Si Mati '.($seed % 1000).' (SINTETIK)',
            'tarikh_kematian' => now()->subDays(200 + $seed % 400)->toDateString(),
            'bahagian_mais' => 'Baki (Baitulmal)',
            'rujukan' => 'EFRD-'.substr(md5($faraidCaseNumber), 0, 8),
            'waris' => $heirs,
        ];
    }
}
