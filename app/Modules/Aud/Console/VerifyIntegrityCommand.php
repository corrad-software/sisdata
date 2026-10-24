<?php

namespace App\Modules\Aud\Console;

use App\Modules\Aud\Services\IntegrityChecker;
use Illuminate\Console\Command;

/** SRS-AUD-07: scheduled (hourly by default) hash-chain verification. */
class VerifyIntegrityCommand extends Command
{
    protected $signature = 'aud:semak-integriti';

    protected $description = 'Semak integriti rantaian cincangan audit trail (SRS-AUD-07)';

    public function handle(IntegrityChecker $checker): int
    {
        $result = $checker->run('jadual');

        if ($result['status'] === 'lulus') {
            $this->info("Integriti audit trail sah: {$result['checked']} rekod disemak.");

            return self::SUCCESS;
        }

        $this->error("Integriti audit trail GAGAL pada no_urutan {$result['bad']}: {$result['detail']}");

        return self::FAILURE;
    }
}
