<?php

namespace App\Modules\Lap\Services;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Lap\Models\CadanganAi;
use App\Modules\Lap\Models\HasilAnalitik;
use App\Modules\Lap\Support\Wang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Value-added analytics (SRS-LAP-15..19), rule-based first (PLAN §10:
 * "model ringkas berasaskan peraturan dulu, ML kemudian"). Runs entirely
 * inside SISDATA on the `laporan` views — no data leaves MAIS (SRS-LAP-21).
 * Each result is a scored suggestion per lot; high / critical results
 * produce recommendations an officer accepts or rejects with justification.
 */
class Analitik
{
    public const VERSI = 'peraturan-1.0';

    public const TINDAKAN = [
        'risiko_tunggakan' => 'notis_tuntutan_tunggakan',
        'tanah_tidak_produktif' => 'jadual_lawatan_pemantauan',
        'potensi_hasil' => 'tawar_untuk_sewaan',
        'potensi_pembangunan' => 'kajian_kebolehlaksanaan_pembangunan',
    ];

    private const SESUAI_BANGUN = ['BANGUNAN', 'PERUMAHAN', 'PERNIAGAAN', 'PERDAGANGAN', 'KOMERSIAL', 'INDUSTRI', 'BERCAMPUR'];

    public function __construct(private Enjin $enjin, private Skop $skop, private Parameters $parameters) {}

    /**
     * Recompute every analytic and refresh the current set.
     *
     * @param  list<string>|null  $only
     * @return array<string, int> results per type
     */
    public function run(?array $only = null): array
    {
        $now = Carbon::now();
        $rows = $this->enjin->transaction($this->skop->settings(null), fn ($db) => [
            'lot' => $db->select('SELECT id, no_lot, kod_daerah, kod_mukim, klasifikasi, kategori_guna_tanah, zoning, status_lot, bahagian, keluasan_m2, nilai_pasaran,
                ada_pajakan_aktif, ada_kes_aktif, ada_isu_pencerobohan, ada_isu_terbiar, lawatan_terakhir, keadaan_tapak_terakhir FROM laporan.v_lot'),
            'cukai' => $db->select('SELECT lot_id, coalesce(sum(baki) FILTER (WHERE tertunggak), 0) AS baki, coalesce(max(umur_tunggakan_hari), 0) AS umur,
                count(*) FILTER (WHERE tertunggak OR (tarikh_bayar IS NOT NULL AND tarikh_bayar > tarikh_akhir)) AS lewat, count(*) AS jumlah, bool_or(amaun_denda > 0) AS denda
                FROM laporan.v_cukai WHERE lot_id IS NOT NULL GROUP BY lot_id'),
            'sewa' => $db->select('SELECT k.lot_id, coalesce(sum(c.baki) FILTER (WHERE c.tertunggak), 0) AS baki, coalesce(max(c.umur_tunggakan_hari), 0) AS umur,
                count(*) FILTER (WHERE c.tertunggak) AS lewat, count(*) AS jumlah
                FROM laporan.v_caj_sewaan c JOIN laporan.v_kontrak_pajakan k ON k.id = c.kontrak_id WHERE k.lot_id IS NOT NULL GROUP BY k.lot_id'),
        ]);
        $lots = [];
        foreach ($rows['lot'] as $l) {
            $lots[(string) $l->id] = $l;
        }

        $results = [];
        $types = $only ?? HasilAnalitik::JENIS;
        if (in_array('risiko_tunggakan', $types, true)) {
            $results['risiko_tunggakan'] = $this->arrears($lots, $rows['cukai'], $rows['sewa']);
        }
        if (in_array('tanah_tidak_produktif', $types, true)) {
            $results['tanah_tidak_produktif'] = $this->idle($lots, $now);
        }
        if (in_array('potensi_hasil', $types, true)) {
            $results['potensi_hasil'] = $this->revenue($lots);
        }
        if (in_array('potensi_pembangunan', $types, true)) {
            $results['potensi_pembangunan'] = $this->development($lots);
        }

        $counts = [];
        DB::transaction(function () use ($results, $now, &$counts) {
            foreach ($results as $type => $items) {
                HasilAnalitik::query()->withoutGlobalScope('access')->where('jenis_analitik', $type)->where('semasa', true)->update(['semasa' => false, 'updated_at' => $now]);
                foreach ($items as $it) {
                    $lot = $it['lot'];
                    // Bulk analytic output: not audited row by row (the run itself is audited).
                    $h = HasilAnalitik::withoutEvents(fn () => HasilAnalitik::query()->create([
                        'jenis_analitik' => $type, 'id_lot' => (string) $lot->id, 'no_lot' => $lot->no_lot, 'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim,
                        'klasifikasi' => $lot->klasifikasi ?? 'dalaman', 'skor' => number_format($it['skor'], 2, '.', ''), 'aras_risiko' => $this->level($it['skor']),
                        'faktor' => $it['faktor'], 'tarikh_kira' => $now, 'versi_model' => self::VERSI, 'semasa' => true,
                    ]));
                    if (in_array($h->aras_risiko, ['tinggi', 'kritikal'], true)) {
                        $this->recommend($h, $it['asas']);
                    }
                }
                $counts[$type] = count($items);
            }
        });

        return $counts;
    }

    public function level(float $score): string
    {
        return match (true) {
            $score >= 75 => 'kritikal',
            $score >= 50 => 'tinggi',
            $score >= 25 => 'sederhana',
            default => 'rendah',
        };
    }

    private function recommend(HasilAnalitik $h, string $basis): void
    {
        $open = CadanganAi::query()->withoutGlobalScope('access')->where('id_lot', $h->id_lot)->where('jenis_analitik', $h->jenis_analitik)->where('status_keputusan', 'baharu')->first();
        if ($open) {
            $open->forceFill(['id_hasil' => $h->id, 'asas_cadangan' => $basis, 'versi_model' => self::VERSI])->save();

            return;
        }
        CadanganAi::query()->create([
            'id_hasil' => $h->id, 'id_lot' => $h->id_lot, 'no_lot' => $h->no_lot, 'kod_daerah' => $h->kod_daerah, 'kod_mukim' => $h->kod_mukim, 'klasifikasi' => $h->klasifikasi,
            'jenis_analitik' => $h->jenis_analitik, 'jenis_tindakan' => self::TINDAKAN[$h->jenis_analitik], 'asas_cadangan' => $basis,
            'status_keputusan' => 'baharu', 'versi_model' => self::VERSI,
        ]);
    }

    /**
     * SRS-LAP-16: probability of arrears from payment history, arrears age and
     * balance, with ownership category recorded as a factor.
     *
     * @return list<array{lot: object, skor: float, faktor: array<string, mixed>, asas: string}>
     */
    private function arrears(array $lots, array $cukai, array $sewa): array
    {
        $by = [];
        foreach ([['cukai', $cukai], ['sewa', $sewa]] as [$k, $rows]) {
            foreach ($rows as $r) {
                $by[(string) $r->lot_id][$k] = $r;
            }
        }
        $high = (string) $this->parameters->get('lap.ambang_baki_tunggakan', 10000);
        $out = [];
        foreach ($by as $lotId => $d) {
            $lot = $lots[$lotId] ?? null;
            if (! $lot) {
                continue;
            }
            $baki = Wang::jumlah([$d['cukai']->baki ?? 0, $d['sewa']->baki ?? 0]);
            $umur = max((int) ($d['cukai']->umur ?? 0), (int) ($d['sewa']->umur ?? 0));
            $lewat = (int) ($d['cukai']->lewat ?? 0) + (int) ($d['sewa']->lewat ?? 0);
            $jumlah = max(1, (int) ($d['cukai']->jumlah ?? 0) + (int) ($d['sewa']->jumlah ?? 0));
            $denda = (bool) ($d['cukai']->denda ?? false);

            $fUmur = min(1, $umur / 365) * 40;
            $fSejarah = ($lewat / $jumlah) * 25;
            $fBaki = min(1.0, (float) bcdiv($baki, bccomp($high, '0', 2) > 0 ? $high : '1', 4)) * 25;
            $score = round($fUmur + $fSejarah + $fBaki + ($denda ? 10 : 0), 2);
            if ($score <= 0) {
                continue;
            }
            $out[] = ['lot' => $lot, 'skor' => $score, 'faktor' => [
                'baki_tertunggak' => $baki, 'umur_tunggakan_hari' => $umur, 'bil_lewat' => $lewat, 'bil_jumlah' => $jumlah, 'ada_denda' => $denda,
                'kategori_pemilikan' => $lot->bahagian, 'skor_umur' => round($fUmur, 2), 'skor_sejarah' => round($fSejarah, 2), 'skor_baki' => round($fBaki, 2),
            ], 'asas' => __('lap::lap.asas.risiko_tunggakan', ['baki' => Wang::papar($baki), 'umur' => $umur, 'lewat' => $lewat, 'jumlah' => $jumlah])];
        }

        return $out;
    }

    /**
     * SRS-LAP-17: idle / unused land — no active lease plus monitoring evidence.
     *
     * @return list<array{lot: object, skor: float, faktor: array<string, mixed>, asas: string}>
     */
    private function idle(array $lots, Carbon $now): array
    {
        $out = [];
        foreach ($lots as $lot) {
            if ($lot->ada_pajakan_aktif) {
                continue;
            }
            $score = 40;
            $why = [__('lap::lap.asas.tiada_pajakan')];
            if ($lot->keadaan_tapak_terakhir === 'terbiar') {
                $score += 30;
                $why[] = __('lap::lap.asas.tapak_terbiar');
            } elseif (in_array($lot->keadaan_tapak_terakhir, ['buruk', 'kritikal'], true)) {
                $score += 15;
                $why[] = __('lap::lap.asas.tapak_buruk');
            }
            if ($lot->ada_isu_terbiar) {
                $score += 20;
                $why[] = __('lap::lap.asas.isu_terbiar');
            }
            if (! $lot->lawatan_terakhir || Carbon::parse($lot->lawatan_terakhir)->lt($now->copy()->subYear())) {
                $score += 10;
                $why[] = __('lap::lap.asas.tiada_lawatan');
            }
            $out[] = ['lot' => $lot, 'skor' => (float) min(100, $score), 'faktor' => [
                'pajakan_aktif' => false, 'keadaan_tapak' => $lot->keadaan_tapak_terakhir, 'isu_terbiar' => (bool) $lot->ada_isu_terbiar,
                'lawatan_terakhir' => $lot->lawatan_terakhir, 'kategori_guna_tanah' => $lot->kategori_guna_tanah,
            ], 'asas' => implode('; ', $why)];
        }

        return $out;
    }

    /**
     * SRS-LAP-15: additional rent potential — unleased lots of commercial /
     * building use, valued from the latest market valuation × yield.
     *
     * @return list<array{lot: object, skor: float, faktor: array<string, mixed>, asas: string}>
     */
    private function revenue(array $lots): array
    {
        $yield = (string) $this->parameters->get('lap.kadar_pulangan_sewa_peratus', 5);
        $high = (string) $this->parameters->get('lap.ambang_hasil_tinggi', 120000);
        $out = [];
        foreach ($lots as $lot) {
            $use = mb_strtoupper((string) $lot->kategori_guna_tanah).' '.mb_strtoupper((string) $lot->zoning);
            $suitable = (bool) preg_match('/BANGUNAN|PERNIAGAAN|PERDAGANGAN|KOMERSIAL|BERCAMPUR/', $use);
            if ($lot->ada_pajakan_aktif || ! $suitable || $lot->ada_kes_aktif) {
                continue;
            }
            if ($lot->nilai_pasaran !== null) {
                $estimate = Wang::nilai(bcdiv(Wang::darab($lot->nilai_pasaran, $yield, 4), '100', 4));
                $ratio = (float) bcdiv($estimate, bccomp($high, '0', 2) > 0 ? $high : '1', 4);
                $score = round(40 + min(1, $ratio) * 50 + ($lot->ada_isu_pencerobohan ? 0 : 10), 2);
            } else {
                $estimate = null;
                $score = 30.0;
            }
            $out[] = ['lot' => $lot, 'skor' => min(100, $score), 'faktor' => [
                'anggaran_hasil_tahunan' => $estimate, 'kadar_pulangan_peratus' => $yield, 'kategori_guna_tanah' => $lot->kategori_guna_tanah, 'zoning' => $lot->zoning,
                'kod_daerah' => $lot->kod_daerah, 'ada_penilaian' => $lot->nilai_pasaran !== null,
            ], 'asas' => $estimate !== null ? __('lap::lap.asas.potensi_hasil', ['kadar' => $yield]) : __('lap::lap.asas.potensi_hasil_tanpa_nilai')];
        }

        return $out;
    }

    /**
     * SRS-LAP-18: development potential — large, unencumbered, unleased lots
     * with suitable use / zoning; published as an MOD-GIS analytic layer.
     *
     * @return list<array{lot: object, skor: float, faktor: array<string, mixed>, asas: string}>
     */
    private function development(array $lots): array
    {
        $min = (float) $this->parameters->get('lap.keluasan_min_pembangunan_m2', 4047);
        $out = [];
        foreach ($lots as $lot) {
            $area = (float) ($lot->keluasan_m2 ?? 0);
            if ($area < $min || $lot->ada_pajakan_aktif || $lot->ada_kes_aktif || $lot->ada_isu_pencerobohan || $lot->klasifikasi === 'rahsia') {
                continue;
            }
            $use = mb_strtoupper((string) $lot->kategori_guna_tanah).' '.mb_strtoupper((string) $lot->zoning);
            $suitable = false;
            foreach (self::SESUAI_BANGUN as $s) {
                $suitable = $suitable || str_contains($use, $s);
            }
            $score = 30 + ($suitable ? 25 : 0) + ($area >= 2 * $min ? 20 : 10) + ($lot->nilai_pasaran !== null ? 15 : 0) + 10;
            $out[] = ['lot' => $lot, 'skor' => (float) min(100, $score), 'faktor' => [
                'keluasan_m2' => round($area, 2), 'sesuai_guna_tanah' => $suitable, 'kategori_guna_tanah' => $lot->kategori_guna_tanah, 'zoning' => $lot->zoning,
                'tiada_bebanan' => true,
            ], 'asas' => __('lap::lap.asas.potensi_pembangunan', ['keluasan' => number_format($area, 0, '.', ',')])];
        }

        return $out;
    }
}
