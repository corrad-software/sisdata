<?php

namespace App\Modules\Pjk\Services;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SRS-PJK-12: the charge schedule is generated from the contract terms
 * (rate × billing cycle over the contract period); due charges are posted to
 * the ledger daily and arrears / ageing are derived from unpaid posted
 * charges. Late-payment penalties are charges too (`penalti`).
 */
class JadualCajService
{
    public function __construct(private LejarService $lejar, private Parameters $parameters) {}

    /** Build every rent period of the contract (idempotent per period). Returns the number created. */
    public function generate(Kontrak $kontrak): int
    {
        $months = Kod::BULAN_KITARAN[$kontrak->kitaran_caj] ?? 1;
        $start = Carbon::parse($kontrak->tarikh_mula)->startOfDay();
        $end = Carbon::parse($kontrak->tarikh_tamat)->startOfDay();
        $rate = Wang::sen($kontrak->kadar);          // per month
        $created = 0;

        for ($from = $start->copy(); $from->lte($end); $from = $from->copy()->addMonthsNoOverflow($months)) {
            $to = $from->copy()->addMonthsNoOverflow($months)->subDay();
            $amount = $rate * $months;
            if ($to->gt($end)) {
                // Pro-rate the last, shorter period by days.
                $full = max(1, (int) $from->diffInDays($from->copy()->addMonthsNoOverflow($months)));
                $amount = intdiv($amount * ((int) $from->diffInDays($end) + 1) + intdiv($full, 2), $full);
            }
            $tempoh = $this->periodKey($from, $kontrak->kitaran_caj);
            $exists = JadualCaj::query()->where('kontrak_id', $kontrak->id)->where('kod_caj', 'sewa')->where('tempoh', $tempoh)->exists();
            if ($exists || $amount <= 0) {
                continue;
            }
            JadualCaj::query()->create([
                'kontrak_id' => $kontrak->id,
                'no_caj' => Penomboran::next('caj'),
                'kod_caj' => 'sewa',
                'tempoh' => $tempoh,
                'tarikh_caj' => $from->format('Y-m-d'),
                'tarikh_akhir' => $from->copy()->addDays($kontrak->hari_tempoh_bayar)->format('Y-m-d'),
                'amaun' => Wang::dec($amount),
                'status_caj' => 'dijadualkan',
                'keterangan' => __('pjk::pjk.caj.sewa_tempoh', ['tempoh' => $tempoh]),
            ]);
            $created++;
        }

        return $created;
    }

    public function periodKey(Carbon $from, string $cycle): string
    {
        return match ($cycle) {
            'tahunan' => $from->format('Y'),
            'suku_tahunan' => $from->format('Y').'-Q'.$from->quarter,
            default => $from->format('Y-m'),
        };
    }

    /** One-off charge (deposit, other) posted immediately. */
    public function oneOff(Kontrak $kontrak, string $kod, string $tempoh, int $amountSen, string $keterangan, ?int $userId = null, ?Carbon $on = null): JadualCaj
    {
        $on ??= Carbon::today();

        return DB::transaction(function () use ($kontrak, $kod, $tempoh, $amountSen, $keterangan, $userId, $on) {
            $caj = JadualCaj::query()->firstOrCreate(
                ['kontrak_id' => $kontrak->id, 'kod_caj' => $kod, 'tempoh' => $tempoh],
                [
                    'no_caj' => Penomboran::next('caj'),
                    'tarikh_caj' => $on->format('Y-m-d'),
                    'tarikh_akhir' => $on->copy()->addDays($kontrak->hari_tempoh_bayar)->format('Y-m-d'),
                    'amaun' => Wang::dec($amountSen),
                    'status_caj' => 'dijadualkan',
                    'keterangan' => $keterangan,
                ],
            );
            $this->issue($caj, $userId);

            return $caj;
        });
    }

    /** Post one scheduled charge to the ledger (once). */
    public function issue(JadualCaj $caj, ?int $userId = null): void
    {
        if ($caj->dikeluarkan || $caj->status_caj === 'dibatalkan') {
            return;
        }
        DB::transaction(function () use ($caj, $userId) {
            $caj->dikeluarkan = true;
            $caj->refreshStatus();
            $caj->save();
            $this->lejar->post(Kontrak::query()->withoutGlobalScope('access')->findOrFail($caj->kontrak_id), 'caj', Wang::sen($caj->amaun), 0, [
                'kod_caj' => $caj->kod_caj,
                'caj_id' => $caj->id,
                'keterangan' => $caj->keterangan ?? Kod::KOD_CAJ[$caj->kod_caj] ?? $caj->kod_caj,
                'id_pegawai' => $userId,
            ], $caj->tarikh_caj->lt(Carbon::today()) ? $caj->tarikh_caj : Carbon::today());
        });
    }

    /** Daily: post every scheduled charge whose date has come, for running contracts. */
    public function issueDue(?Carbon $today = null): int
    {
        $today ??= Carbon::today();
        $count = 0;
        JadualCaj::query()->where('dikeluarkan', false)->where('status_caj', 'dijadualkan')->whereDate('tarikh_caj', '<=', $today)
            ->whereHas('kontrak', fn ($q) => $q->withoutGlobalScope('access')->whereIn('status', Kod::BERKUAT_KUASA))
            ->with(['kontrak' => fn ($q) => $q->withoutGlobalScope('access')])
            ->orderBy('tarikh_caj')->chunkById(500, function ($rows) use (&$count) {
                foreach ($rows as $caj) {
                    $this->issue($caj);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Late-payment penalty for the month: pjk.penalti_peratus_bulanan % of the
     * contract's rent overdue more than pjk.penalti_hari_tangguh days. One
     * penalty charge per contract per month.
     */
    public function penalties(?Carbon $today = null): int
    {
        $today ??= Carbon::today();
        $rate = (string) $this->parameters->get('pjk.penalti_peratus_bulanan', '1.00');
        $grace = (int) $this->parameters->get('pjk.penalti_hari_tangguh', 30);
        if (Wang::scaled($rate) <= 0) {
            return 0;
        }
        $month = $today->format('Y-m');
        $rows = DB::table('jadual_caj as c')
            ->join('kontrak_pajakan as k', 'k.id', '=', 'c.kontrak_id')
            ->whereNull('k.deleted_at')
            ->whereIn('k.status', [...Kod::BERKUAT_KUASA, 'tamat_tempoh'])
            ->where('c.kod_caj', 'sewa')->where('c.dikeluarkan', true)
            ->whereIn('c.status_caj', ['belum_bayar', 'separa'])
            ->whereDate('c.tarikh_akhir', '<', $today->copy()->subDays($grace))
            ->groupBy('c.kontrak_id')
            ->selectRaw('c.kontrak_id, sum(c.amaun - c.amaun_dibayar - c.amaun_dikecualikan) as tertunggak')
            ->get();

        $count = 0;
        foreach ($rows as $r) {
            $amount = Wang::percent(Wang::sen((string) $r->tertunggak), $rate);
            if ($amount <= 0 || JadualCaj::query()->where('kontrak_id', $r->kontrak_id)->where('kod_caj', 'penalti')->where('tempoh', $month)->exists()) {
                continue;
            }
            $kontrak = Kontrak::query()->withoutGlobalScope('access')->findOrFail($r->kontrak_id);
            $this->oneOff($kontrak, 'penalti', $month, $amount, __('pjk::pjk.caj.penalti', ['tempoh' => $month, 'kadar' => $rate]));
            $count++;
        }

        return $count;
    }

    /**
     * Ageing of a contract's (or every contract's) unpaid posted charges.
     *
     * @return array{jumlah: string, kumpulan: array<string, string>}
     */
    public function ageing(?string $kontrakId = null, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $buckets = array_fill_keys(array_keys(Kod::KUMPULAN_UMUR), 0);
        $total = 0;
        JadualCaj::query()->where('dikeluarkan', true)->whereIn('status_caj', ['belum_bayar', 'separa'])
            ->whereDate('tarikh_akhir', '<', $today)
            ->when($kontrakId, fn ($q) => $q->where('kontrak_id', $kontrakId))
            ->get()->each(function (JadualCaj $c) use (&$buckets, &$total, $today) {
                $baki = $c->bakiSen();
                if ($baki <= 0) {
                    return;
                }
                $buckets[Kod::umur($c->umurTunggakan($today))] += $baki;
                $total += $baki;
            });

        return ['jumlah' => Wang::dec($total), 'kumpulan' => array_map(fn (int $s) => Wang::dec($s), $buckets)];
    }

    /** Cancel unposted future charges (termination / renewal). */
    public function cancelFrom(Kontrak $kontrak, Carbon $from): int
    {
        return JadualCaj::query()->where('kontrak_id', $kontrak->id)->where('dikeluarkan', false)
            ->whereDate('tarikh_caj', '>', $from)->where('status_caj', 'dijadualkan')
            ->get()->each(fn (JadualCaj $c) => $c->forceFill(['status_caj' => 'dibatalkan'])->save())->count();
    }
}
