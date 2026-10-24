<?php

namespace App\Modules\Ast\Support;

/**
 * Book value by straight-line depreciation (kaedah garis lurus, MPSAS 17 /
 * Pekeliling Perbendaharaan) — the SRS only prescribes valuation-report
 * values (SRS-AST-18); the book value is shown beside them for finance.
 * All money is DECIMAL strings computed with bcmath (no floats):
 *
 *   nilai sisa      = kos × kadar_sisa / 100
 *   susut tahunan   = (kos − nilai sisa) / hayat guna
 *   nilai buku(t)   = max(nilai sisa, kos − susut tahunan × umur penuh (tahun))
 */
final class SusutNilai
{
    private const SKALA = 2;

    /** @return array{kos: string, nilai_sisa: string, susut_tahunan: string, umur_tahun: int, susut_terkumpul: string, nilai_buku: string} */
    public static function kira(string $kos, int $tahunPerolehan, int $hayatGuna, string $kadarSisa, int $tahunSemasa): array
    {
        $kos = self::norm($kos);
        $hayat = max(1, $hayatGuna);
        $sisa = bcdiv(bcmul($kos, self::norm($kadarSisa), 6), '100', 6);
        $tahunan = bcdiv(bcsub($kos, $sisa, 6), (string) $hayat, 6);
        $umur = max(0, min($hayat, $tahunSemasa - $tahunPerolehan));
        $terkumpul = bcmul($tahunan, (string) $umur, 6);
        $buku = bcsub($kos, $terkumpul, 6);
        if (bccomp($buku, $sisa, 6) < 0) {
            $buku = $sisa;
        }

        return [
            'kos' => self::bundar($kos), 'nilai_sisa' => self::bundar($sisa), 'susut_tahunan' => self::bundar($tahunan),
            'umur_tahun' => $umur, 'susut_terkumpul' => self::bundar(bcsub($kos, $buku, 6)), 'nilai_buku' => self::bundar($buku),
        ];
    }

    /**
     * Year-by-year schedule from the acquisition year to $hingga (inclusive).
     *
     * @return list<array{tahun: int, susut: string, susut_terkumpul: string, nilai_buku: string}>
     */
    public static function jadual(string $kos, int $tahunPerolehan, int $hayatGuna, string $kadarSisa, int $hingga): array
    {
        $out = [];
        $sebelum = self::norm($kos);
        for ($y = $tahunPerolehan; $y <= $hingga; $y++) {
            $k = self::kira($kos, $tahunPerolehan, $hayatGuna, $kadarSisa, $y);
            $out[] = ['tahun' => $y, 'susut' => self::bundar(bcsub($sebelum, $k['nilai_buku'], 6)), 'susut_terkumpul' => $k['susut_terkumpul'], 'nilai_buku' => $k['nilai_buku']];
            $sebelum = $k['nilai_buku'];
        }

        return $out;
    }

    /** Sum of decimal strings. */
    public static function jumlah(iterable $values): string
    {
        $t = '0';
        foreach ($values as $v) {
            if ($v !== null && $v !== '') {
                $t = bcadd($t, self::norm((string) $v), self::SKALA);
            }
        }

        return $t;
    }

    /** Round half-up to 2 decimals. */
    public static function bundar(string $v): string
    {
        $v = self::norm($v);
        $offset = str_starts_with($v, '-') ? '-0.005' : '0.005';

        return bcadd($v, $offset, self::SKALA);
    }

    public static function norm(string $v): string
    {
        $v = trim($v);

        return is_numeric($v) ? (str_contains(strtolower($v), 'e') ? number_format((float) $v, 6, '.', '') : $v) : '0';
    }
}
