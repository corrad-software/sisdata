<?php

namespace Tests\Modules\Kes\Support;

use App\Modules\Cki\Contracts\GrpPembayaran;
use App\Modules\Cki\Contracts\PenyediaTidakTersedia;
use Carbon\CarbonImmutable;

class FakeGrp implements GrpPembayaran
{
    /** @var array<string, array<string, string>> */
    public array $sent = [];

    public bool $down = false;

    public string $status = 'dibayar';

    public function hantarPermohonan(array $permohonan): string
    {
        if ($this->down) {
            throw new PenyediaTidakTersedia('GRP tiada (ujian)');
        }
        $ref = 'GRP-'.(count($this->sent) + 1);
        $this->sent[$ref] = $permohonan;

        return $ref;
    }

    public function status(string $rujukanGrp): ?array
    {
        return isset($this->sent[$rujukanGrp]) ? ['status' => $this->status, 'no_baucar' => 'B1', 'no_rujukan_bayaran' => 'P1', 'tarikh_bayar' => now()->toDateString(), 'amaun' => $this->sent[$rujukanGrp]['amaun']] : null;
    }

    public function bayaran(CarbonImmutable $dari, CarbonImmutable $hingga): array
    {
        return [];
    }
}
