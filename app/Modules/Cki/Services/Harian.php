<?php

namespace App\Modules\Cki\Services;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Penerima;
use App\Modules\Cki\Support\Wang;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily housekeeping (`cki:harian`), SRS-CKI-18: bills past their due date
 * become tertunggak with an alert; a penalty is applied (manual / imported
 * bills: configurable rate; API bills carry the issuer's penalty) with a
 * penalty alert; and district officers get the list of out-of-coverage lots
 * that still need a manual bill for the year.
 */
class Harian
{
    public function __construct(private BilService $bil, private Parameters $parameters, private Notifier $notifier, private Penerima $penerima, private TugasanManual $tugasan) {}

    /** @return array{tertunggak: int, denda: int, luar_liputan: int} */
    public function jalankan(?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $out = ['tertunggak' => 0, 'denda' => 0, 'luar_liputan' => 0];
        $rate = (string) $this->parameters->get('cki.denda_peratus', '10.00');
        $min = Wang::norm((string) $this->parameters->get('cki.denda_minimum', '10.00'));

        Cukai::query()->withoutGlobalScope('access')->whereIn('status_bayaran', ['belum_bayar', 'dalam_proses', 'tertunggak'])
            ->whereDate('tarikh_akhir', '<', $today)->orderBy('id')
            ->chunk(500, function ($bills) use (&$out, $today, $rate, $min) {
                foreach ($bills as $b) {
                    /** @var Cukai $b */
                    DB::transaction(function () use ($b, &$out, $today, $rate, $min) {
                        $before = $b->status_bayaran;
                        if (! $b->denda_dikenakan && $b->sumber_rekod !== 'api') {
                            $denda = Wang::max(Wang::percent($b->amaun_pokok, $rate), $min);
                            $b->amaun_denda = Wang::add($b->amaun_denda, $denda);
                            $b->denda_dikenakan = true;
                            $this->bil->lejar($b, 'denda', $denda, '0', $b->no_bil, __('cki::cki.lejar.denda_lewat', ['peratus' => $rate]), null, $today);
                            $this->amaranDenda($b, $denda);
                            $out['denda']++;
                        } elseif (! $b->denda_dikenakan && ! Wang::isZero($b->amaun_denda)) {
                            $b->denda_dikenakan = true;
                            $this->amaranDenda($b, $b->amaun_denda);
                            $out['denda']++;
                        }
                        $this->bil->kemaskiniStatus($b, $today);
                        if ($before !== 'tertunggak' && $b->status_bayaran === 'tertunggak') {
                            $out['tertunggak']++;
                            $this->notifier->send('cki.tertunggak', $this->penerima->untuk('cki.bil.lihat', $b->kod_daerah), [
                                'no_bil' => (string) $b->no_bil, 'jenis_cukai' => Kod::JENIS_CUKAI[$b->jenis] ?? $b->jenis, 'amaun' => Wang::rm($b->baki()),
                                'tarikh_akhir' => $b->tarikh_akhir?->format('d/m/Y'), 'kod_daerah' => (string) $b->kod_daerah,
                            ], '/admin/cki/bil/'.$b->id, 'cki.tertunggak:'.$b->id);
                        }
                    });
                }
            });

        foreach ($this->tugasan->ringkasanDaerah((int) $today->format('Y')) as $daerah => $count) {
            $out['luar_liputan'] += $count;
            $this->notifier->send('cki.luar_liputan', $this->penerima->untuk('cki.bil.cipta', (string) $daerah), [
                'kod_daerah' => (string) $daerah, 'bil_lot' => $count, 'tahun' => (int) $today->format('Y'),
            ], '/admin/cki/tugasan?kod_daerah='.$daerah, 'cki.luar_liputan:'.$daerah.':'.$today->format('YW'));
        }

        return $out;
    }

    private function amaranDenda(Cukai $b, string $denda): void
    {
        $this->notifier->send('cki.denda', $this->penerima->untuk('cki.bil.lihat', $b->kod_daerah), [
            'no_bil' => (string) $b->no_bil, 'denda' => Wang::rm($denda), 'tarikh_akhir' => $b->tarikh_akhir?->format('d/m/Y'), 'kod_daerah' => (string) $b->kod_daerah,
        ], '/admin/cki/bil/'.$b->id, 'cki.denda:'.$b->id);
    }
}
