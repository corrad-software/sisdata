<?php

namespace App\Modules\Gis\Support;

/**
 * Server-side copy of the shared legends so layer legends served by the API
 * match the UI: frontend/lib/legend.ts (MOD-HKM, SRS-HKM-20) and
 * frontend/lib/legend-pemerolehan.ts (MOD-PER, SRS-PER-28).
 * tests/Modules/Gis/LegendTest parses those files and fails on drift.
 */
final class Palet
{
    /** WARNA_JENIS_HAK_MILIK */
    public const JENIS_HAK_MILIK = [
        'GRN' => ['#1D4ED8', 'Geran'],
        'PN' => ['#0F766E', 'Pajakan Negeri'],
        'GM' => ['#4338CA', 'Geran Mukim'],
        'PM' => ['#15803D', 'Pajakan Mukim'],
        'HSD' => ['#B45309', 'Hakmilik Sementara (Daftar)'],
        'HSM' => ['#C2410C', 'Hakmilik Sementara (Mukim)'],
        'TOL' => ['#BE185D', 'Lesen Pendudukan Sementara (TOL)'],
        'RIZAB' => ['#6D28D9', 'Tanah Rizab'],
    ];

    /** WARNA_KATEGORI_GUNA_TANAH */
    public const KATEGORI_GUNA_TANAH = [
        'PERTANIAN' => ['#16A34A', 'Pertanian'],
        'BANGUNAN' => ['#DC2626', 'Bangunan'],
        'PERUSAHAAN' => ['#7C3AED', 'Perusahaan'],
        'TIADA' => ['#71717A', 'Tiada kategori'],
    ];

    /** WARNA_BAHAGIAN_PEMILIK */
    public const BAHAGIAN_PEMILIK = [
        'hartanah' => ['#2563EB', 'Hartanah MAIS'],
        'wakaf' => ['#059669', 'Wakaf'],
        'baitulmal' => ['#D97706', 'Baitulmal'],
    ];

    /** LEGEND_SALURAN (legend-pemerolehan.ts) */
    public const SALURAN = [
        'pemberimilikan_5a' => ['#2563EB', 'Pemberimilikan Tanah (Seksyen 5A)'],
        'serah_milik' => ['#0891B2', 'Serah Milik'],
        'faraid' => ['#7C3AED', 'Faraid'],
        'daftar_kematian' => ['#475569', 'Daftar Kematian'],
        'perintah_arb' => ['#B45309', 'Perintah ARB'],
        'serahan_hakmilik' => ['#0D9488', 'Serahan Hakmilik'],
        'perintah_mahkamah' => ['#BE123C', 'Perintah Mahkamah (LA/Probet)'],
        'pusaka_jkptgs' => ['#C2410C', 'Pusaka JKPTGS'],
        'wakaf' => ['#15803D', 'Wakaf'],
        'pembelian_terus' => ['#A21CAF', 'Pembelian Terus'],
        'tanah_warta' => ['#4D7C0F', 'Tanah Warta'],
    ];

    public const LALAI = '#52525B';

    /**
     * @param  array<string, array{0: string, 1: string}>  $palette
     * @return list<array{nilai: string, label: string, warna: string}>
     */
    public static function entries(array $palette): array
    {
        $out = [];
        foreach ($palette as $value => [$colour, $label]) {
            $out[] = ['nilai' => (string) $value, 'label' => $label, 'warna' => $colour];
        }

        return $out;
    }
}
