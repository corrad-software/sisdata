<?php

use App\Modules\Ptn\Support\Lalai;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeded defaults for MOD-PTN (editable afterwards by the Pentadbir Sistem
 * without code — SRS-PTN-02/04/11/15): checklist templates, risk-based visit
 * frequencies, colour thresholds and risk weights.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('templat_senarai_semak')->exists()) {
            DB::table('templat_senarai_semak')->insert([
                ['kod' => 'PTN-RUTIN', 'nama' => 'Pemeriksaan rutin tanah MAIS', 'kategori_lot' => null, 'jenis_pemeriksaan' => 'rutin', 'senarai_item' => json_encode(Lalai::itemRutin()), 'versi' => 1, 'status_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
                ['kod' => 'PTN-PERTANIAN', 'nama' => 'Pemeriksaan tanah pertanian', 'kategori_lot' => 'PERTANIAN', 'jenis_pemeriksaan' => 'rutin', 'senarai_item' => json_encode(Lalai::itemPertanian()), 'versi' => 1, 'status_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
                ['kod' => 'PTN-SUSULAN', 'nama' => 'Pemeriksaan susulan isu', 'kategori_lot' => null, 'jenis_pemeriksaan' => 'susulan', 'senarai_item' => json_encode(array_values(array_filter(Lalai::itemRutin(), fn ($i) => in_array($i['kategori'], ['tanda_pencerobohan', 'keadaan_fizikal', 'akses_sempadan'], true)))), 'versi' => 1, 'status_aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! DB::table('kekerapan_pemantauan')->exists()) {
            DB::table('kekerapan_pemantauan')->insert(array_map(fn ($r) => [
                'kategori_lot' => $r[0], 'tahap_risiko' => $r[1], 'kekerapan_setahun' => $r[2], 'aktif' => true, 'created_at' => $now, 'updated_at' => $now,
            ], Lalai::KEKERAPAN));
        }

        if (! DB::table('konfigurasi_ambang_warna')->exists()) {
            $rows = [['keseluruhan', 0, 30, 60, null, null, null]];
            foreach (Lalai::KATEGORI_RISIKO as $kod => [, $weight]) {
                $rows[] = [$kod, 0, 40, 70, null, null, $weight];
            }
            $rows[] = ['tindakan', 0, null, null, 7, 0, null];
            $rows[] = ['lawatan', 0, null, null, 14, 0, null];
            DB::table('konfigurasi_ambang_warna')->insert(array_map(fn ($r) => [
                'kategori_risiko' => $r[0], 'ambang_hijau' => $r[1], 'ambang_kuning' => $r[2], 'ambang_merah' => $r[3],
                'tempoh_kelewatan_kuning' => $r[4], 'tempoh_kelewatan_merah' => $r[5], 'pemberat' => $r[6],
                'tarikh_berkuat_kuasa' => '2000-01-01 00:00:00', 'created_at' => $now, 'updated_at' => $now,
            ], $rows));
        }
    }

    public function down(): void
    {
        // Data only; the tables are dropped by the previous migration.
    }
};
