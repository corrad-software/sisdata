<?php

namespace App\Modules\Gis\Support;

use App\Modules\Gis\Layers\RisikoLayer;
use App\Modules\Gis\Layers\ZoningLayer;

/**
 * The ten mandatory layers of SRS-GIS-11 (RFP 8.1.4) with their planned
 * thematic legends. A slot is filled by MOD-GIS's own layer or by any layer
 * whose key starts with the source module's prefix (registered through
 * MapLayers). Unfilled slots still show their legend, marked "menunggu
 * modul sumber" — the style is built now, the data arrives with the module.
 */
final class KatalogWajib
{
    /**
     * @return list<array{bil: int, kod: string, nama: string, modul: string, awalan: list<string>, interim: list<string>, legenda: list<array{nilai: string, label: string, warna: string}>}>
     */
    public static function all(): array
    {
        $e = fn (array $rows) => array_map(fn ($r) => ['nilai' => $r[0], 'label' => $r[1], 'warna' => $r[2]], $rows);

        return [
            ['bil' => 1, 'kod' => 'hak_milik', 'nama' => 'Lapisan Hak Milik', 'modul' => 'MOD-HKM', 'awalan' => [], 'interim' => ['gis.hak_milik'], 'legenda' => Palet::entries(Palet::JENIS_HAK_MILIK)],
            ['bil' => 2, 'kod' => 'kategori_guna_tanah', 'nama' => 'Lapisan Kategori Guna Tanah', 'modul' => 'MOD-PRO', 'awalan' => [], 'interim' => ['gis.kategori_guna_tanah'], 'legenda' => Palet::entries(Palet::KATEGORI_GUNA_TANAH)],
            ['bil' => 3, 'kod' => 'zoning', 'nama' => 'Lapisan Zoning', 'modul' => 'JPBD / PBT', 'awalan' => [], 'interim' => ['gis.zoning'], 'legenda' => $e(array_map(fn ($k, $v) => [$k, $v[1], $v[0]], array_keys(ZoningLayer::PALET), ZoningLayer::PALET))],
            ['bil' => 4, 'kod' => 'status_pajakan', 'nama' => 'Lapisan Status Pajakan', 'modul' => 'MOD-PJK', 'awalan' => ['pjk.'], 'interim' => [], 'legenda' => $e([
                ['aktif', 'Aktif', '#16A34A'], ['hampir_tamat', 'Hampir tamat', '#EAB308'], ['tamat_tempoh', 'Tamat tempoh', '#DC2626'], ['belum_dipajak', 'Belum dipajak', '#9CA3AF'],
            ])],
            ['bil' => 5, 'kod' => 'status_cukai', 'nama' => 'Lapisan Status Cukai', 'modul' => 'MOD-CKI', 'awalan' => ['cki.'], 'interim' => [], 'legenda' => $e([
                ['selesai', 'Selesai', '#16A34A'], ['belum_dibayar', 'Belum dibayar', '#EAB308'], ['tertunggak', 'Tertunggak', '#DC2626'], ['tertunggak_ambang', 'Tertunggak melebihi ambang MAIS', '#7F1D1D'],
            ])],
            ['bil' => 6, 'kod' => 'pemantauan_tanah', 'nama' => 'Lapisan Pemantauan Tanah', 'modul' => 'MOD-PTN', 'awalan' => ['ptn.'], 'interim' => [], 'legenda' => $e([
                ['dalam_tempoh', 'Lawatan dalam tempoh', '#16A34A'], ['hampir_tempoh', 'Hampir tempoh lawatan', '#EAB308'], ['tertunggak', 'Lawatan tertunggak', '#DC2626'],
            ])],
            ['bil' => 7, 'kod' => 'aduan', 'nama' => 'Lapisan Aduan dan Isu Tanah', 'modul' => 'MOD-ADU', 'awalan' => ['adu.'], 'interim' => [], 'legenda' => $e([
                ['terbuka', 'Aduan terbuka', '#F97316'], ['melebihi_sla', 'Terbuka melebihi SLA', '#DC2626'], ['selesai', 'Selesai', '#6B7280'],
            ])],
            ['bil' => 8, 'kod' => 'kes_mahkamah', 'nama' => 'Lapisan Kes Mahkamah / Perundangan', 'modul' => 'MOD-KES', 'awalan' => ['kes.'], 'interim' => [], 'legenda' => $e([
                ['aktif', 'Kes aktif (hachure merah)', '#DC2626'], ['selesai', 'Kes selesai', '#9CA3AF'],
            ])],
            ['bil' => 9, 'kod' => 'risiko', 'nama' => 'Lapisan Risiko Hartanah', 'modul' => 'MOD-LAP', 'awalan' => ['lap.'], 'interim' => ['gis.risiko'], 'legenda' => $e(array_map(fn ($k, $v) => [$k, $v[1], $v[0]], array_keys(RisikoLayer::PALET), RisikoLayer::PALET))],
            ['bil' => 10, 'kod' => 'aset_fizikal', 'nama' => 'Lapisan Aset Fizikal', 'modul' => 'MOD-AST', 'awalan' => ['ast.'], 'interim' => ['gis.aset_fizikal'], 'legenda' => $e([
                ['baik', 'Baik', '#16A34A'], ['perlu_perhatian', 'Perlu perhatian', '#EAB308'], ['kritikal', 'Kritikal', '#DC2626'],
            ])],
        ];
    }

    /**
     * Fill each slot from the registered layer keys.
     *
     * @param  list<string>  $keys
     * @return list<array{bil: int, kod: string, nama: string, modul: string, lapisan: list<string>, status: string, legenda: list<array{nilai: string, label: string, warna: string}>}>
     */
    public static function status(array $keys): array
    {
        $out = [];
        foreach (self::all() as $slot) {
            $own = array_values(array_filter($keys, function ($k) use ($slot) {
                foreach ($slot['awalan'] as $prefix) {
                    if (str_starts_with($k, $prefix)) {
                        return true;
                    }
                }

                return false;
            }));
            $layers = $own !== [] ? $own : array_values(array_intersect($slot['interim'], $keys));
            $out[] = [
                'bil' => $slot['bil'], 'kod' => $slot['kod'], 'nama' => $slot['nama'], 'modul' => $slot['modul'],
                'lapisan' => $layers,
                'status' => $layers === [] ? 'menunggu_modul' : ($own !== [] || $slot['awalan'] === [] ? 'tersedia' : 'interim'),
                'legenda' => $slot['legenda'],
            ];
        }

        return $out;
    }
}
