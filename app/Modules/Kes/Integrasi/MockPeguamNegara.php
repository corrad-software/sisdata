<?php

namespace App\Modules\Kes\Integrasi;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Kes\Contracts\AttorneyGeneralChambers;
use App\Modules\Kes\Contracts\CourtSystemUnavailable;

/** Local stand-in for the AG Chambers / State Legal Adviser referral channel. */
class MockPeguamNegara implements AttorneyGeneralChambers
{
    public function __construct(private Parameters $parameters) {}

    public function refer(array $referral): string
    {
        $this->semak();

        return 'AGC/'.now()->format('Y').'/'.strtoupper(substr(sha1($referral['no_rujukan'].$referral['jenis_rujukan']), 0, 6));
    }

    public function status(string $reference): ?array
    {
        $this->semak();
        if (! str_starts_with($reference, 'AGC/')) {
            return null;
        }

        return ['status' => 'dalam_semakan', 'pegawai' => 'Penasihat Undang-Undang Negeri (mock)', 'ulasan' => null];
    }

    private function semak(): void
    {
        if ((bool) $this->parameters->get('kes.mock_mahkamah_gagal', false)) {
            throw new CourtSystemUnavailable('Saluran Jabatan Peguam Negara tidak dapat dihubungi (simulasi)');
        }
    }
}
