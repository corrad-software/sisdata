<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ptn\Models\KekerapanPemantauan;
use App\Modules\Ptn\Models\TemplatSenaraiSemak;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\Lalai;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SRS-PTN-01/02, acceptance #1: annual monitoring schedule generated from
 * each lot's risk priority. Priority score = current risk score (six
 * categories) + issue history (5 per finding in the last 24 months, max 20)
 * + high asset value (+10, nilai pasaran ≥ ptn.nilai_aset_tinggi_rm) + time
 * since the last visit (+10 when more than a year or never). Level: an open
 * critical finding → kritikal; otherwise the colour of the priority score
 * (merah tinggi, kuning sederhana, hijau rendah). Visits per year come from
 * kekerapan_pemantauan for (lot category, level), "*" as fallback, spread
 * evenly over the year and shifted so the first visit is due one interval
 * after the last one.
 */
class Penjadualan
{
    public function __construct(
        private RisikoService $risiko,
        private KodWarna $warna,
        private AccessControl $access,
        private Parameters $parameters,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{tahun: int, lot: int, dijana: int, dilangkau: int, dipadam: int}
     */
    public function janaTahunan(int $tahun, ?User $by = null, ?string $kodDaerah = null, bool $semula = false, bool $sintetik = false): array
    {
        $lots = DB::table('lot_tanah')->whereNull('deleted_at')->whereNotIn('status_lot', ['dilupus', 'diarkib', 'arkib'])
            ->when($kodDaerah, fn ($q, $v) => $q->where('kod_daerah', $v));
        if ($by !== null) {
            foreach (['daerah' => 'kod_daerah', 'mukim' => 'kod_mukim', 'klasifikasi' => 'klasifikasi'] as $type => $col) {
                $allowed = $this->access->scope($by, $type);
                if ($allowed !== null) {
                    $lots->whereIn($col, $allowed);
                }
            }
        }
        $lots = $lots->orderBy('id')->get(['id', 'kod_daerah', 'kod_mukim', 'klasifikasi', 'kategori_guna_tanah']);
        $result = ['tahun' => $tahun, 'lot' => $lots->count(), 'dijana' => 0, 'dilangkau' => 0, 'dipadam' => 0];

        foreach ($lots->chunk(300) as $chunk) {
            $ids = $chunk->pluck('id')->map(fn ($v) => (string) $v)->all();
            if ($semula) {
                $result['dipadam'] += DB::table('jadual_pemantauan')->whereIn('lot_id', $ids)->where('tahun', $tahun)->where('dijana_automatik', true)
                    ->where('status_pelaksanaan', 'dirancang')->whereNull('id_pegawai_bertugas')
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('rekod_lawatan')->whereColumn('rekod_lawatan.id_jadual', 'jadual_pemantauan.id'))
                    ->delete();
            }
            $priorities = $this->keutamaan($ids);
            $existing = DB::table('jadual_pemantauan')->whereIn('lot_id', $ids)->where('tahun', $tahun)->where('dijana_automatik', true)
                ->get(['lot_id', 'bil_dalam_tahun'])->groupBy('lot_id')->map(fn ($r) => $r->pluck('bil_dalam_tahun')->map(fn ($v) => (int) $v)->all());
            $rows = [];
            foreach ($chunk as $lot) {
                $id = (string) $lot->id;
                $p = $priorities[$id];
                $n = $this->kekerapan($lot->kategori_guna_tanah, $p['tahap']);
                $have = $existing->get($id, []);
                $templat = $this->templatUntuk($lot->kategori_guna_tanah, 'rutin');
                foreach ($this->tarikhSasaran($tahun, $n, $p['lawatan_terakhir']) as $i => $date) {
                    if (in_array($i + 1, $have, true)) {
                        $result['dilangkau']++;

                        continue;
                    }
                    $rows[] = [
                        'id' => (string) Str::uuid(), 'lot_id' => $id, 'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim,
                        'klasifikasi' => $lot->klasifikasi ?? 'dalaman', 'tahun' => $tahun, 'kekerapan' => $n, 'bil_dalam_tahun' => $i + 1,
                        'tarikh_sasaran' => $date->toDateString(),
                        'objektif' => 'Pemantauan berkala '.($i + 1).'/'.$n.' — keutamaan risiko '.Lalai::TAHAP_RISIKO[$p['tahap']],
                        'jenis_pemeriksaan' => 'rutin', 'id_templat' => $templat?->id, 'keutamaan_risiko' => $p['tahap'],
                        'skor_keutamaan' => $p['skor'], 'faktor_keutamaan' => json_encode($p['faktor']), 'status_pelaksanaan' => 'dirancang',
                        'dijana_automatik' => true, 'versi' => 1, 'id_pencipta' => $by?->id, 'sintetik' => $sintetik,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
            foreach (array_chunk($rows, 500) as $part) {
                DB::table('jadual_pemantauan')->insert($part);
            }
            $result['dijana'] += count($rows);
        }

        $this->audit->record('jana_jadual', 'ptn', 'jadual_pemantauan', null, meta: $result + ['kod_daerah' => $kodDaerah, 'semula' => $semula]);

        return $result;
    }

    /**
     * @param  list<string>  $lotIds
     * @return array<string, array{tahap: string, skor: float, faktor: array<string, mixed>, lawatan_terakhir: ?Carbon}>
     */
    public function keutamaan(array $lotIds): array
    {
        if ($lotIds === []) {
            return [];
        }
        $missing = array_diff($lotIds, DB::table('risk_rating_lot')->whereIn('lot_id', $lotIds)->where('semasa', true)->pluck('lot_id')->map(fn ($v) => (string) $v)->all());
        if ($missing !== []) {
            $this->risiko->kira(array_values($missing));
        }
        $risk = DB::table('risk_rating_lot')->whereIn('lot_id', $lotIds)->where('semasa', true)->get(['lot_id', 'skor_keseluruhan', 'paksa_merah'])->keyBy('lot_id');
        $issues = DB::table('isu_pemantauan')->whereIn('lot_id', $lotIds)->where('tarikh_dibuka', '>=', now()->subMonths(24)->toDateString())
            ->groupBy('lot_id')->selectRaw('lot_id, COUNT(*) AS n')->get()->mapWithKeys(fn ($r) => [$r->lot_id => $r->n]);
        $last = DB::table('rekod_lawatan')->whereIn('lot_id', $lotIds)->whereIn('status_lawatan', ['dihantar', 'disahkan'])
            ->groupBy('lot_id')->selectRaw('lot_id, MAX(tarikh_lawatan) AS t')->get()->mapWithKeys(fn ($r) => [$r->lot_id => $r->t]);
        $threshold = (float) $this->parameters->get('ptn.nilai_aset_tinggi_rm', 1000000);
        $marks = implode(',', array_fill(0, count($lotIds), '?'));
        $value = collect(DB::select("SELECT DISTINCT ON (lot_id) lot_id, nilai_pasaran FROM penilaian WHERE deleted_at IS NULL AND lot_id IN ({$marks}) ORDER BY lot_id, tarikh_penilaian DESC", $lotIds))
            ->pluck('nilai_pasaran', 'lot_id');

        $out = [];
        foreach ($lotIds as $id) {
            $r = $risk->get($id);
            $base = $r ? (float) $r->skor_keseluruhan : 0.0;
            $nIssues = (int) ($issues[$id] ?? 0);
            $lastVisit = isset($last[$id]) ? Carbon::parse($last[$id]) : null;
            $high = isset($value[$id]) && (float) $value[$id] >= $threshold;
            $stale = $lastVisit === null || $lastVisit->lt(now()->subDays(365));
            $score = min(100.0, round($base + min(20, $nIssues * 5) + ($high ? 10 : 0) + ($stale ? 10 : 0), 2));
            $tahap = ($r && $r->paksa_merah) ? 'kritikal' : match ($this->warna->untukSkor($score)) {
                'merah' => 'tinggi',
                'kuning' => 'sederhana',
                default => 'rendah',
            };
            $out[$id] = ['tahap' => $tahap, 'skor' => $score, 'lawatan_terakhir' => $lastVisit, 'faktor' => [
                'skor_risiko' => $base, 'bil_isu_24_bulan' => $nIssues, 'nilai_aset_tinggi' => $high,
                'lawatan_terakhir' => $lastVisit?->toDateString(), 'penemuan_kritikal_terbuka' => (bool) ($r->paksa_merah ?? false),
            ]];
        }

        return $out;
    }

    public function kekerapan(?string $kategoriLot, string $tahap): int
    {
        $rows = KekerapanPemantauan::query()->where('aktif', true)->where('tahap_risiko', $tahap)->whereIn('kategori_lot', array_filter([$kategoriLot, '*']))->get()->keyBy('kategori_lot');
        $row = ($kategoriLot !== null ? $rows->get($kategoriLot) : null) ?? $rows->get('*');
        if ($row !== null) {
            return max(1, min(52, $row->kekerapan_setahun));
        }

        return ['rendah' => 1, 'sederhana' => 2, 'tinggi' => 4, 'kritikal' => 12][$tahap] ?? 1;
    }

    /** @return list<Carbon> $n target dates spread over the year, first one interval after the last visit */
    public function tarikhSasaran(int $tahun, int $n, ?Carbon $lastVisit): array
    {
        $start = Carbon::create($tahun, 1, 1)->startOfDay();
        $days = $start->isLeapYear() ? 366 : 365;
        $interval = $days / $n;
        $offset = 0.0;
        if ($lastVisit !== null) {
            $due = $lastVisit->copy()->addDays((int) round($interval));
            $offset = max(0.0, min($interval - 1, (float) $start->diffInDays($due, false)));
        }
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $start->copy()->addDays((int) floor($offset + $i * $interval));
        }

        return $out;
    }

    public function templatUntuk(?string $kategoriLot, string $jenis = 'rutin'): ?TemplatSenaraiSemak
    {
        $q = TemplatSenaraiSemak::query()->where('status_aktif', true)->orderByDesc('versi');

        return (clone $q)->where('jenis_pemeriksaan', $jenis)->where('kategori_lot', $kategoriLot)->first()
            ?? (clone $q)->where('jenis_pemeriksaan', $jenis)->whereNull('kategori_lot')->first()
            ?? (clone $q)->whereNull('kategori_lot')->first();
    }
}
