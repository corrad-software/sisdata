<?php

namespace App\Modules\Ptn\Support;

use App\Models\User;
use App\Modules\Ptn\Models\KonfigurasiAmbangWarna;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * SRS-PTN-14/15/16: the single place that turns a score or a deadline into
 * hijau / kuning / merah, from the CURRENT admin-configured thresholds
 * (konfigurasi_ambang_warna, effective-dated). Every view — lists, record
 * card, GIS layers, dashboard, KPIs — calls this, so a threshold change shows
 * everywhere at once without a deploy (acceptance #5).
 *
 * Score categories: score >= ambang_merah → merah; >= ambang_kuning → kuning.
 * Deadline categories (tindakan, lawatan): more than tempoh_kelewatan_merah
 * days past the deadline → merah; within tempoh_kelewatan_kuning days of it
 * (or overdue but inside the red tolerance) → kuning.
 */
class KodWarna
{
    /** @var array<string, KonfigurasiAmbangWarna|null>|null */
    private ?array $current = null;

    /** @return array<string, KonfigurasiAmbangWarna|null> */
    public function semasa(): array
    {
        if ($this->current !== null) {
            return $this->current;
        }
        $rows = KonfigurasiAmbangWarna::query()->where('tarikh_berkuat_kuasa', '<=', now())
            ->orderBy('kategori_risiko')->orderByDesc('tarikh_berkuat_kuasa')->orderByDesc('id')->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->kategori_risiko] ??= $row;
        }

        return $this->current = $out;
    }

    public function lupakan(): void
    {
        $this->current = null;
    }

    public function ambang(string $kategori): ?KonfigurasiAmbangWarna
    {
        return $this->semasa()[$kategori] ?? null;
    }

    public function untukSkor(float $skor, string $kategori = 'keseluruhan'): string
    {
        $a = $this->ambang($kategori);
        $kuning = $a?->ambang_kuning !== null ? (float) $a->ambang_kuning : 30.0;
        $merah = $a?->ambang_merah !== null ? (float) $a->ambang_merah : 60.0;

        return match (true) {
            $skor >= $merah => 'merah',
            $skor >= $kuning => 'kuning',
            default => 'hijau',
        };
    }

    public function untukTarikh(CarbonInterface $tarikhAkhir, string $kategori = 'tindakan', bool $selesai = false, ?CarbonInterface $hariIni = null): string
    {
        if ($selesai) {
            return 'hijau';
        }
        $a = $this->ambang($kategori);
        $kuning = $a->tempoh_kelewatan_kuning ?? 7;
        $merah = $a->tempoh_kelewatan_merah ?? 0;
        $today = Carbon::parse(($hariIni ?? now())->toDateString());
        $deadline = Carbon::parse($tarikhAkhir->toDateString());
        $lewat = (int) $deadline->diffInDays($today, false); // > 0 = days past the deadline

        return match (true) {
            $lewat > $merah => 'merah',
            $lewat > 0 || -$lewat <= $kuning => 'kuning',
            default => 'hijau',
        };
    }

    /** @return array<string, float> category => weight (SRS-PTN-11) */
    public function pemberat(): array
    {
        $out = [];
        foreach (Lalai::KATEGORI_RISIKO as $kod => [, $lalai]) {
            $w = $this->ambang($kod)?->pemberat;
            $out[$kod] = $w !== null ? max(0.0, (float) $w) : (float) $lalai;
        }

        return $out;
    }

    /** @return array{kod: string, label: string, hex: string} */
    public static function papar(string $warna): array
    {
        return ['kod' => $warna, 'label' => Lalai::WARNA[$warna] ?? $warna, 'hex' => Lalai::HEX[$warna] ?? '#6B7280'];
    }

    /**
     * SQL CASE turning a numeric column into the colour with current thresholds.
     *
     * @return array{0: string, 1: list<float>}
     */
    public function sqlSkor(string $column, string $kategori = 'keseluruhan'): array
    {
        $a = $this->ambang($kategori);

        return ["CASE WHEN {$column} >= ? THEN 'merah' WHEN {$column} >= ? THEN 'kuning' ELSE 'hijau' END", [
            $a?->ambang_merah !== null ? (float) $a->ambang_merah : 60.0,
            $a?->ambang_kuning !== null ? (float) $a->ambang_kuning : 30.0,
        ]];
    }

    /**
     * New effective row for one category (history kept; audited by the model observer).
     *
     * @param  array{ambang_kuning?: float|null, ambang_merah?: float|null, tempoh_kelewatan_kuning?: int|null, tempoh_kelewatan_merah?: int|null, pemberat?: float|null, tarikh_berkuat_kuasa?: string|null}  $data
     */
    public function tetapkan(string $kategori, array $data, User $by): KonfigurasiAmbangWarna
    {
        $prev = $this->ambang($kategori);
        $row = KonfigurasiAmbangWarna::query()->create([
            'kategori_risiko' => $kategori,
            'ambang_hijau' => 0,
            'ambang_kuning' => array_key_exists('ambang_kuning', $data) ? $data['ambang_kuning'] : $prev?->ambang_kuning,
            'ambang_merah' => array_key_exists('ambang_merah', $data) ? $data['ambang_merah'] : $prev?->ambang_merah,
            'tempoh_kelewatan_kuning' => array_key_exists('tempoh_kelewatan_kuning', $data) ? $data['tempoh_kelewatan_kuning'] : $prev?->tempoh_kelewatan_kuning,
            'tempoh_kelewatan_merah' => array_key_exists('tempoh_kelewatan_merah', $data) ? $data['tempoh_kelewatan_merah'] : $prev?->tempoh_kelewatan_merah,
            'pemberat' => array_key_exists('pemberat', $data) ? $data['pemberat'] : $prev?->pemberat,
            'tarikh_berkuat_kuasa' => $data['tarikh_berkuat_kuasa'] ?? now(),
            'id_pengubah' => $by->id,
        ]);
        $this->lupakan();

        return $row;
    }
}
