<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ptn\Models\RiskRatingLot;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\Lalai;
use Illuminate\Support\Facades\DB;

/**
 * SRS-PTN-11/12: weighted risk score per lot from the six categories —
 * skor_keseluruhan = Σ(pemberat × skor) / Σ pemberat, each 0–100, weights
 * from konfigurasi_ambang_warna (admin-configurable). An open KRITIKAL
 * finding forces the lot red (SRS-PTN-17). Run on schedule (ptn:kira-risiko)
 * and right after a visit is processed. Each run keeps history; colour
 * changes are audited.
 */
class RisikoService
{
    public function __construct(private DaftarIsyaratRisiko $signals, private KodWarna $warna, private AuditLogger $audit) {}

    /**
     * @param  list<string>|null  $lotIds  null = every lot
     * @return array{lot: int, berubah: int}
     */
    public function kira(?array $lotIds = null): array
    {
        $total = 0;
        $changed = 0;
        $query = DB::table('lot_tanah')->whereNull('deleted_at')->orderBy('id')->select(['id', 'kod_daerah', 'kod_mukim', 'klasifikasi']);
        if ($lotIds !== null) {
            $query->whereIn('id', $lotIds);
        }
        $query->chunk(500, function ($lots) use (&$total, &$changed) {
            $changed += $this->kiraKumpulan($lots->all());
            $total += count($lots);
        });

        return ['lot' => $total, 'berubah' => $changed];
    }

    /** @param list<object> $lots */
    private function kiraKumpulan(array $lots): int
    {
        $ids = array_map(fn ($l) => (string) $l->id, $lots);
        $scores = [];
        foreach ($this->signals->all() as $category => $signal) {
            $scores[$category] = $signal->scores($ids);
        }
        $weights = $this->warna->pemberat();
        $sumW = array_sum($weights) ?: 1.0;
        $critical = DB::table('isu_pemantauan')->whereIn('lot_id', $ids)->where('tahap_keterukan', 'kritikal')->whereIn('status_isu', ['terbuka', 'dalam_tindakan'])
            ->distinct()->pluck('lot_id')->map(fn ($v) => (string) $v)->flip()->all();
        $previous = RiskRatingLot::query()->withoutGlobalScope('access')->whereIn('lot_id', $ids)->where('semasa', true)->get()->keyBy('lot_id');

        $now = now();
        $rows = [];
        $changed = 0;
        foreach ($lots as $lot) {
            $id = (string) $lot->id;
            $parts = [];
            $detail = [];
            $total = 0.0;
            foreach (array_keys(Lalai::KATEGORI_RISIKO) as $category) {
                $s = (float) ($scores[$category][$id]['skor'] ?? 0);
                $s = max(0.0, min(100.0, $s));
                $parts[$category] = $s;
                if (isset($scores[$category][$id]['butiran'])) {
                    $detail[$category] = $scores[$category][$id]['butiran'];
                }
                $total += $weights[$category] * $s;
            }
            $overall = round($total / $sumW, 2);
            $force = isset($critical[$id]);
            $colour = $force ? 'merah' : $this->warna->untukSkor($overall);
            $old = $previous->get($id);
            if ($old !== null && $old->kod_warna !== $colour) {
                $changed++;
                $this->audit->record('kemas_kini', 'ptn', 'risk_rating_lot', $id, changes: [
                    'kod_warna' => [$old->kod_warna, $colour],
                    'skor_keseluruhan' => [(float) $old->skor_keseluruhan, $overall],
                ], meta: ['sebab' => 'kiraan_risiko']);
            }
            $rows[] = [
                'lot_id' => $id, 'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim, 'klasifikasi' => $lot->klasifikasi ?? 'dalaman',
                'tarikh_kiraan' => $now,
                'skor_pencerobohan' => $parts['pencerobohan'], 'skor_tunggakan_cukai' => $parts['tunggakan_cukai'], 'skor_pertikaian' => $parts['pertikaian'],
                'skor_dokumen' => $parts['dokumen'], 'skor_tamat_kontrak' => $parts['tamat_kontrak'], 'skor_potensi_hasil' => $parts['potensi_hasil'],
                'skor_keseluruhan' => $overall, 'kod_warna' => $colour, 'paksa_merah' => $force,
                'butiran' => json_encode(['butiran' => $detail, 'pemberat' => $weights, 'sumber' => $this->signals->sumber()]),
                'semasa' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($ids, $rows) {
            DB::table('risk_rating_lot')->whereIn('lot_id', $ids)->where('semasa', true)->update(['semasa' => false]);
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('risk_rating_lot')->insert($chunk);
            }
        });

        return $changed;
    }

    /** Current rating of one lot; computed on demand when missing. */
    public function semasa(string $lotId): ?RiskRatingLot
    {
        $row = RiskRatingLot::query()->withoutGlobalScope('access')->where('lot_id', $lotId)->where('semasa', true)->first();
        if ($row === null) {
            $this->kira([$lotId]);
            $row = RiskRatingLot::query()->withoutGlobalScope('access')->where('lot_id', $lotId)->where('semasa', true)->first();
        }

        return $row;
    }

    /**
     * Colour of a rating under the CURRENT thresholds (SRS-PTN-15: a threshold
     * change shows at once, without recomputing).
     */
    public function warna(RiskRatingLot $r): string
    {
        return $r->paksa_merah ? 'merah' : $this->warna->untukSkor((float) $r->skor_keseluruhan);
    }

    /** @return array<string, mixed> */
    public function papar(RiskRatingLot $r, bool $detail): array
    {
        $colour = $this->warna($r);
        $out = [
            'lot_id' => $r->lot_id,
            'skor_keseluruhan' => (float) $r->skor_keseluruhan,
            'kod_warna' => $colour,
            'warna' => KodWarna::papar($colour),
            'paksa_merah' => $r->paksa_merah,
            'tarikh_kiraan' => $r->tarikh_kiraan->toIso8601String(),
        ];
        if ($detail) {
            $b = $r->butiran;
            $out['kategori'] = [];
            foreach (Lalai::KATEGORI_RISIKO as $kod => [$label]) {
                $s = (float) $r->getAttribute('skor_'.$kod);
                $out['kategori'][] = [
                    'kod' => $kod, 'label' => $label, 'skor' => $s, 'kod_warna' => $this->warna->untukSkor($s, $kod),
                    'pemberat' => (float) ($b['pemberat'][$kod] ?? 0), 'butiran' => $b['butiran'][$kod] ?? null,
                    'sumber' => ($b['sumber'][$kod] ?? 'terbina') === 'terbina' ? 'terbina' : 'modul',
                ];
            }
        }

        return $out;
    }

    /**
     * SRS-PTN-24 trend: lots per colour at the end of each of the last $months months.
     *
     * @param  array<string, mixed>  $f
     * @return list<array{tempoh: string, hijau: int, kuning: int, merah: int, purata: float}>
     */
    public function trend(User $user, array $f = [], int $months = 12): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $end = now()->startOfMonth()->subMonths($i)->endOfMonth();
            $q = RiskRatingLot::query()->untuk($user)->where('tarikh_kiraan', '<=', $end)
                ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kod_daerah', $v));
            $sub = $q->toBase()->selectRaw('DISTINCT ON (lot_id) lot_id, kod_warna, skor_keseluruhan')->orderBy('lot_id')->orderByDesc('tarikh_kiraan');
            $agg = DB::query()->fromSub($sub, 's')->selectRaw("COUNT(*) FILTER (WHERE kod_warna = 'hijau') AS hijau, COUNT(*) FILTER (WHERE kod_warna = 'kuning') AS kuning, COUNT(*) FILTER (WHERE kod_warna = 'merah') AS merah, COALESCE(AVG(skor_keseluruhan), 0) AS purata")->first();
            $out[] = ['tempoh' => $end->format('Y-m'), 'hijau' => (int) $agg->hijau, 'kuning' => (int) $agg->kuning, 'merah' => (int) $agg->merah, 'purata' => round((float) $agg->purata, 1)];
        }

        return $out;
    }
}
