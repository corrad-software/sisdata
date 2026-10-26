<?php

namespace App\Console\MaisSeed;

use App\Modules\Cki\Contracts\PenyediaCukaiBerpusat;
use App\Modules\Cki\Integration\MockPenyediaMbi;
use App\Modules\Cki\Models\Resit;
use App\Modules\Cki\Services\BilService;
use App\Modules\Cki\Services\LiputanService;
use App\Modules\Cki\Services\ObligasiService;
use App\Modules\Cki\Support\Wang;
use App\Modules\Pro\Models\LotTanah;
use Illuminate\Support\Carbon;

/**
 * MOD-CKI through ObligasiService / BilService, like `cki:sample`: a land-tax
 * obligation for every lot plus a PBT assessment for built properties, bills
 * 2023–2026 — older years mostly paid (some late, with penalty), a few
 * overdue, the current year partly unpaid. All SYNTHETIC.
 */
class CukaiBuilder
{
    public const DARI = 2023;

    public const HINGGA = 2026;

    private bool $liputan = false;

    public function __construct(private LiputanService $coverage, private ObligasiService $obligasi, private BilService $bil, private PenyediaCukaiBerpusat $provider) {}

    /** @param array<string, mixed> $h */
    public function bina(LotTanah $lot, array $h, Rawak $r, Konteks $k): void
    {
        if (! $this->liputan) {
            $this->coverage->lalai();
            $this->liputan = true;
        }
        $types = ['tanah'];
        if (! in_array($h['jenis'], ['pertanian', 'tanah_kosong', 'kubur'], true)) {
            $types[] = 'taksiran_pbt';
        }
        $mock = $this->provider instanceof MockPenyediaMbi && ! $this->provider->isDown() ? $this->provider : null;
        $today = Carbon::today();
        // Waqf mosques/schools are often exempted or remitted; still recorded, smaller amounts.
        $scale = in_array($h['jenis'], ['masjid', 'surau', 'sekolah_agama', 'kubur'], true) ? 0.25 : 1.0;

        foreach ($types as $jenis) {
            $o = $this->obligasi->cipta([
                'lot_id' => $lot->id, 'jenis_cukai' => $jenis,
                'no_akaun_cukai' => ($jenis === 'taksiran_pbt' ? 'TKS' : 'CT').$lot->kod_daerah.str_pad((string) $r->int(1, 9999999), 7, '0', STR_PAD_LEFT),
            ], null, true);
            $k->tambah('obligasi_cukai');
            [$min, $span, $due] = $jenis === 'taksiran_pbt' ? [240, 9000, '11-30'] : [80, 4800, '05-31'];
            $base = (int) round(($min + $r->int(0, $span)) * $scale);
            $payer = $r->int(1, 100); // 1–80 good payer, 81–93 sometimes late, 94–100 in arrears
            for ($y = self::DARI; $y <= self::HINGGA; $y++) {
                $pokok = Wang::norm(bcmul((string) $base, bcadd('1', bcmul('0.02', (string) ($y - self::DARI), 4), 4), 2));
                $akhir = Carbon::parse($y.'-'.$due);
                $noBil = strtoupper(substr($jenis, 0, 2)).'-'.$y.'-'.substr(sha1($o->id.$y.'m'), 0, 8);
                if ($o->status_liputan_api === 'dalam' && $mock !== null) {
                    $api = $mock->semakBil(['kod_agensi' => $o->kod_agensi, 'jenis_cukai' => $jenis, 'no_akaun' => $o->no_akaun_cukai, 'tahun' => $y])[0];
                    [$pokok, $akhir, $noBil] = [$api['amaun_pokok'], Carbon::parse($api['tarikh_akhir']), $api['no_bil']];
                }
                $isPast = $akhir->lt($today);
                $paid = $isPast ? ($payer <= 93 && ! ($payer > 80 && $y >= self::HINGGA - 1 && $r->chance(50))) : $r->chance($payer <= 80 ? 45 : 10);
                $late = $paid && $isPast && ($payer > 80 || $r->chance(8));
                $tarikhBayar = $paid ? ($late ? $akhir->copy()->addDays($r->int(5, 120)) : $akhir->copy()->subDays($r->int(1, 110))) : null;
                if ($tarikhBayar !== null && $tarikhBayar->gt($today)) {
                    $tarikhBayar = $today->copy();
                }
                $denda = ($late || (! $paid && $isPast)) ? Wang::max(Wang::percent($pokok, '10'), '10.00') : '0.00';
                $noResit = $paid ? 'R'.$y.'-'.strtoupper(substr(sha1($o->id.$y), 0, 8)) : null;
                $b = $this->bil->cipta($o, [
                    'tahun_taksiran' => $y, 'no_bil' => $noBil,
                    'amaun_pokok' => $pokok, 'amaun_denda' => $denda, 'tarikh_bil' => $y.'-01-02', 'tarikh_akhir' => $akhir->format('Y-m-d'),
                    'status_bayaran' => $paid ? 'dibayar' : 'belum_bayar', 'tarikh_bayar' => $tarikhBayar?->format('Y-m-d'), 'no_resit' => $noResit,
                ], null, $o->status_liputan_api === 'dalam' ? 'api' : 'manual', null, true);
                $k->tambah('cukai');
                if ($paid) {
                    $b->forceFill(['kaedah_bayaran' => $o->status_liputan_api === 'dalam' ? 'fpx' : $r->pick(['eft', 'bank_draf', 'kaunter_pbt'])])->save();
                    Resit::query()->create([
                        'id_bil' => $b->id, 'no_resit' => (string) $noResit, 'no_bil_rujukan' => $b->no_bil, 'kod_agensi' => $b->kod_agensi, 'amaun' => $b->amaun_dibayar,
                        'tarikh_resit' => $tarikhBayar?->format('Y-m-d'), 'sumber' => $o->status_liputan_api === 'dalam' ? 'api' : 'manual', 'kod_daerah' => $b->kod_daerah, 'sintetik' => true,
                    ]);
                    $k->tambah('cukai_dibayar');
                } elseif ($isPast) {
                    $k->tambah('cukai_tertunggak');
                }
            }
        }
    }
}
