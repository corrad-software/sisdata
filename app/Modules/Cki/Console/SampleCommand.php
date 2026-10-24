<?php

namespace App\Modules\Cki\Console;

use App\Modules\Cki\Contracts\PenyediaCukaiBerpusat;
use App\Modules\Cki\Integration\MockPenyediaMbi;
use App\Modules\Cki\Models\Liputan;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Models\Resit;
use App\Modules\Cki\Services\BilService;
use App\Modules\Cki\Services\LiputanService;
use App\Modules\Cki\Services\ObligasiService;
use App\Modules\Cki\Support\Wang;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dev data: SYNTHETIC tax obligations and bills (flagged sintetik) for the
 * existing lots, assessment years 2024–2026, with a realistic mix of paid on
 * time, paid late, unpaid and overdue (with penalty). Idempotent — lots that
 * already have synthetic obligations are skipped. Bills are never deleted
 * (DB trigger), so there is no "reset" option.
 *
 *   php artisan cki:sample [--had=500] [--dari=2024] [--hingga=2026]
 */
class SampleCommand extends Command
{
    protected $signature = 'cki:sample {--had=0 : Bilangan lot maksimum (0 = semua)} {--dari=2024} {--hingga=2026} {--benih=6}';

    protected $description = 'Jana bil cukai SINTETIK (2024–2026) bagi lot sedia ada';

    public function handle(LiputanService $liputan, ObligasiService $obligasi, BilService $bil, PenyediaCukaiBerpusat $provider): int
    {
        mt_srand((int) $this->option('benih'));
        $liputan->lalai();
        // Two PBTs outside API coverage, so the manual-fallback path has data.
        $pbts = Liputan::query()->where('jenis_agensi', 'pbt')->orderBy('kod_agensi')->get();
        foreach ($pbts->take(-2) as $p) {
            $liputan->kemaskini($p, ['perkhidmatan_semakan' => false, 'perkhidmatan_bayaran' => false, 'catatan' => 'Sintetik: belum dalam liputan penyedia berpusat']);
        }

        $done = Obligasi::query()->withoutGlobalScope('access')->where('sintetik', true)->distinct()->pluck('lot_id')->all();
        $lots = DB::table('lot_tanah')->whereNull('deleted_at')->whereNotIn('id', $done)->orderBy('id')
            ->when((int) $this->option('had') > 0, fn ($q) => $q->limit((int) $this->option('had')))->get(['id', 'no_lot', 'kod_daerah']);
        $today = Carbon::today();
        $from = (int) $this->option('dari');
        $to = (int) $this->option('hingga');
        $mock = $provider instanceof MockPenyediaMbi && ! $provider->isDown() ? $provider : null;
        $stats = ['lot' => 0, 'obligasi' => 0, 'bil' => 0, 'dibayar' => 0, 'tertunggak' => 0, 'belum' => 0];
        $bar = $this->output->createProgressBar(count($lots));

        foreach ($lots as $lot) {
            $types = ['tanah'];
            if (mt_rand(1, 100) <= 45) {
                $types[] = 'taksiran_pbt';
            }
            if (mt_rand(1, 100) <= 6) {
                $types[] = 'petak';
            }
            foreach ($types as $jenis) {
                DB::transaction(function () use ($obligasi, $bil, $lot, $jenis, $from, $to, $today, $mock, &$stats) {
                    $o = $obligasi->cipta([
                        'lot_id' => $lot->id, 'jenis_cukai' => $jenis,
                        'no_akaun_cukai' => ($jenis === 'taksiran_pbt' ? 'TKS' : 'CT').$lot->kod_daerah.str_pad((string) mt_rand(1, 9999999), 7, '0', STR_PAD_LEFT),
                    ], null, true);
                    $stats['obligasi']++;
                    [$min, $span, $due] = match ($jenis) {
                        'taksiran_pbt' => [180, 4200, '11-30'],
                        'petak' => [40, 900, '05-31'],
                        default => [60, 2400, '05-31'],
                    };
                    $base = $min + mt_rand(0, $span);
                    for ($y = $from; $y <= $to; $y++) {
                        $pokok = Wang::norm(bcmul((string) $base, bcadd('1', bcmul('0.015', (string) ($y - $from), 4), 4), 2));
                        $akhir = Carbon::parse($y.'-'.$due);
                        $noBil = strtoupper(substr($jenis, 0, 2)).'-'.$y.'-'.substr(sha1($o->id.$y.'b'), 0, 8);
                        if ($o->status_liputan_api === 'dalam' && $mock !== null) {
                            // Same figures the (mock) central API returns, so the next cki:semak-bil finds no false differences.
                            $api = $mock->semakBil(['kod_agensi' => $o->kod_agensi, 'jenis_cukai' => $jenis, 'no_akaun' => $o->no_akaun_cukai, 'tahun' => $y])[0];
                            [$pokok, $akhir, $noBil] = [$api['amaun_pokok'], Carbon::parse($api['tarikh_akhir']), $api['no_bil']];
                        }
                        $roll = mt_rand(1, 100);
                        $isPast = $akhir->lt($today);
                        // Older years: ~88 % paid (most on time); current year: ~68 % paid.
                        $paidShare = $y < (int) $today->format('Y') ? 88 : 68;
                        $paid = $isPast ? $roll <= $paidShare : $roll <= 25;
                        $late = $paid && $isPast && mt_rand(1, 100) <= 12;
                        $tarikhBayar = $paid ? ($late ? $akhir->copy()->addDays(mt_rand(5, 120)) : $akhir->copy()->subDays(mt_rand(1, 110))) : null;
                        if ($tarikhBayar && $tarikhBayar->gt($today)) {
                            $tarikhBayar = $today->copy();
                        }
                        $denda = ($late || (! $paid && $isPast)) ? Wang::max(Wang::percent($pokok, '10'), '10.00') : '0.00';
                        $noResit = $paid ? 'R'.$y.'-'.strtoupper(substr(sha1($o->id.$y), 0, 8)) : null;
                        $b = $bil->cipta($o, [
                            'tahun_taksiran' => $y, 'no_bil' => $noBil,
                            'amaun_pokok' => $pokok, 'amaun_denda' => $denda, 'tarikh_bil' => $y.'-01-02', 'tarikh_akhir' => $akhir->format('Y-m-d'),
                            'status_bayaran' => $paid ? 'dibayar' : 'belum_bayar', 'tarikh_bayar' => $tarikhBayar?->format('Y-m-d'), 'no_resit' => $noResit,
                        ], null, $o->status_liputan_api === 'dalam' ? 'api' : 'manual', null, true);
                        if ($paid) {
                            $b->forceFill(['kaedah_bayaran' => $o->status_liputan_api === 'dalam' ? 'fpx' : ['eft', 'bank_draf', 'kaunter_pbt'][mt_rand(0, 2)]])->save();
                            // ~3 % of paid bills deliberately lack a receipt (reconciliation demo).
                            if (mt_rand(1, 100) > 3) {
                                Resit::query()->create([
                                    'id_bil' => $b->id, 'no_resit' => (string) $noResit, 'no_bil_rujukan' => $b->no_bil, 'kod_agensi' => $b->kod_agensi, 'amaun' => $b->amaun_dibayar,
                                    'tarikh_resit' => $tarikhBayar?->format('Y-m-d'), 'sumber' => $o->status_liputan_api === 'dalam' ? 'api' : 'manual', 'kod_daerah' => $b->kod_daerah, 'sintetik' => true,
                                ]);
                            }
                            $stats['dibayar']++;
                        } elseif ($isPast) {
                            $stats['tertunggak']++;
                        } else {
                            $stats['belum']++;
                        }
                        $stats['bil']++;
                    }
                });
            }
            $stats['lot']++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info(sprintf('SINTETIK: %d lot, %d obligasi, %d bil (%d dibayar, %d tertunggak, %d belum matang).', $stats['lot'], $stats['obligasi'], $stats['bil'], $stats['dibayar'], $stats['tertunggak'], $stats['belum']));

        return self::SUCCESS;
    }
}
