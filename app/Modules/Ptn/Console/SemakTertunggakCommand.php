<?php

namespace App\Modules\Ptn\Console;

use App\Modules\Ptn\Services\TindakanService;
use Illuminate\Console\Command;

/** `php artisan ptn:semak-tertunggak` — daily overdue corrective-action colours + notifications (SRS-PTN-14/19). */
class SemakTertunggakCommand extends Command
{
    protected $signature = 'ptn:semak-tertunggak';

    protected $description = 'Kemas kini kod warna tindakan pembetulan dan maklumkan yang telah melepasi tarikh akhir (MOD-PTN)';

    public function handle(TindakanService $t): int
    {
        $r = $t->semakTertunggak();
        $this->components->info("{$r['dikemas_kini']} kod warna dikemas kini; {$r['lewat']} tindakan lewat dimaklumkan.");

        return self::SUCCESS;
    }
}
