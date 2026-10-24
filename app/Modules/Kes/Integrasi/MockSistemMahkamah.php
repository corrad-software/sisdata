<?php

namespace App\Modules\Kes\Integrasi;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Kes\Contracts\CourtFilingSystem;
use App\Modules\Kes\Contracts\CourtSystemUnavailable;

/**
 * Local stand-in for the courts' e-Filing / e-Court systems: deterministic
 * hearing dates derived from the case number (so repeated syncs are stable),
 * `kes.mock_mahkamah_gagal` simulates an outage (manual fallback), case
 * numbers starting "TIADA" are unknown to the court.
 */
class MockSistemMahkamah implements CourtFilingSystem
{
    public function __construct(private Parameters $parameters) {}

    public function name(): string
    {
        return 'mock';
    }

    public function caseInfo(string $noKes, ?string $mahkamah = null): ?array
    {
        $this->semak();
        if (str_starts_with(strtoupper($noKes), 'TIADA')) {
            return null;
        }
        $h = crc32($noKes);
        $first = now()->startOfDay()->addDays(10 + $h % 40)->setTime(9, 0);
        $jenis = ['sebutan', 'pengurusan_kes', 'bicara'];

        return [
            'no_kes' => $noKes,
            'status' => 'aktif',
            'prosiding' => [
                ['tarikh' => $first->toDateTimeString(), 'jenis' => $jenis[$h % 3], 'catatan' => 'Tarikh daripada sistem mahkamah (mock)'],
                ['tarikh' => $first->copy()->addDays(30 + $h % 20)->toDateTimeString(), 'jenis' => 'bicara', 'catatan' => 'Tarikh daripada sistem mahkamah (mock)'],
            ],
        ];
    }

    public function fileDocument(string $noKes, string $kategori, string $namaFail, string $kandungan): string
    {
        $this->semak();

        return 'EF-'.strtoupper(substr(sha1($noKes.$namaFail.strlen($kandungan)), 0, 10));
    }

    private function semak(): void
    {
        if ((bool) $this->parameters->get('kes.mock_mahkamah_gagal', false)) {
            throw new CourtSystemUnavailable('Sistem mahkamah tidak dapat dihubungi (simulasi)');
        }
    }
}
