<?php

namespace App\Modules\Rbc\Console;

use App\Modules\Rbc\Services\PermissionSync;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'rbc:segerak-kebenaran';

    protected $description = 'Persist every module\'s registered permissions and the 7 base roles (SRS-RBC-05); also runs after migrate';

    public function handle(PermissionSync $sync): int
    {
        $result = $sync->sync();
        $this->info("Peranan asas baharu: {$result['peranan_baharu']}, kebenaran baharu: {$result['kebenaran_baharu']}.");

        return self::SUCCESS;
    }
}
