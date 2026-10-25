<?php

namespace App\Modules\Kba\Console;

use App\Modules\Kba\Services\Analitik;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use Illuminate\Console\Command;

/**
 * Weekly content-gap digest to content admins through MOD-NTF (SRS-KBA-18):
 * questions with insufficient sources or negative feedback in the last 7 days.
 * Only counts and normalised question text — no source content.
 */
class RingkasanJurangCommand extends Command
{
    protected $signature = 'kba:ringkasan-jurang {--hari=7}';

    protected $description = 'Hantar ringkasan jurang kandungan Pangkalan Pengetahuan kepada pentadbir kandungan';

    public function handle(Analitik $analitik, Notifier $notifier, RecipientDirectory $directory): int
    {
        $hari = max(1, (int) $this->option('hari'));
        $hingga = now()->endOfDay();
        $dari = $hingga->copy()->subDays($hari - 1)->startOfDay();
        $jurang = $analitik->jurang($dari, $hingga, 20);
        if ($jurang === []) {
            $this->info('Tiada jurang kandungan dalam tempoh ini.');

            return self::SUCCESS;
        }
        $admins = $directory->resolve(RecipientDirectory::KEBENARAN, 'kba.analitik.lihat');
        $contoh = implode('; ', array_map(fn ($j) => mb_substr($j['soalan'], 0, 80), array_slice($jurang, 0, 3)));
        $notifier->send('kba.ringkasan_jurang', $admins, [
            'bil_jurang' => count($jurang),
            'bil_soalan' => array_sum(array_column($jurang, 'bilangan')),
            'tempoh' => $dari->toDateString().' – '.$hingga->toDateString(),
            'contoh' => $contoh,
        ], '/admin/kba/analitik', 'kba-jurang-'.$hingga->toDateString());
        $this->info(count($jurang).' jurang kandungan dihantar kepada '.count($admins).' pentadbir.');

        return self::SUCCESS;
    }
}
