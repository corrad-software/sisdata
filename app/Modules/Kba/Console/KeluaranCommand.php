<?php

namespace App\Modules\Kba\Console;

use App\Modules\Kba\Models\Keluaran;
use App\Modules\Kba\Services\PengurusArtikel;
use Illuminate\Console\Command;

/** SRS-KBA-12: record a new system release (e.g. from the deploy pipeline) and flag outdated articles. */
class KeluaranCommand extends Command
{
    protected $signature = 'kba:keluaran {versi} {--catatan=}';

    protected $description = 'Rekod keluaran versi sistem baharu dan tanda artikel yang merujuk versi lama';

    public function handle(PengurusArtikel $artikel): int
    {
        $versi = (string) $this->argument('versi');
        if (! preg_match('/^\d+(\.\d+){1,3}$/', $versi) || Keluaran::query()->where('versi', $versi)->exists()) {
            $this->error('Versi tidak sah atau sudah direkod.');

            return self::FAILURE;
        }
        $k = $artikel->keluaran($versi, $this->option('catatan'), null);
        $this->info("Keluaran {$versi} direkod; {$k->bilangan_artikel_ditanda} artikel ditanda perlu semakan.");

        return self::SUCCESS;
    }
}
