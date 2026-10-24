<?php

namespace App\Modules\Adm\Services\Tugasan;

use App\Modules\Adm\Contracts\JobProgress;

/** In-process counter behind the JobProgress contract (SRS-ADM-08 "bilangan rekod diproses"). */
class PembilangRekod implements JobProgress
{
    private ?int $count = null;

    public function processed(int $count = 1): void
    {
        $this->count = ($this->count ?? 0) + $count;
    }

    public function reset(): void
    {
        $this->count = null;
    }

    public function count(): ?int
    {
        return $this->count;
    }
}
