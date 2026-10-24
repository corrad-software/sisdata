<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** SRS-AST-03: default asset categories (editable in Konfigurasi). Idempotent. */
return new class extends Migration
{
    public function up(): void
    {
        $i = 0;
        foreach ([
            'BANGUNAN_PEJABAT' => ['Bangunan pejabat', 'bangunan', 12, 60, 50, ['pemeriksaan_struktur', 'elektrik', 'pendingin_hawa', 'keselamatan_kebakaran']],
            'MASJID_SURAU' => ['Masjid dan surau', 'bangunan', 6, 60, 60, ['pemeriksaan_struktur', 'bumbung', 'elektrik', 'paip_sanitasi']],
            'SEKOLAH' => ['Sekolah / institusi pendidikan', 'bangunan', 6, 60, 50, ['pemeriksaan_struktur', 'elektrik', 'keselamatan_kebakaran']],
            'KEDAI' => ['Kedai / premis perniagaan', 'bangunan', 12, 36, 40, ['pemeriksaan_struktur', 'elektrik']],
            'KOMPLEKS' => ['Kompleks / bazar', 'bangunan', 6, 36, 50, ['pemeriksaan_struktur', 'elektrik', 'lif', 'keselamatan_kebakaran']],
            'KEDIAMAN' => ['Bangunan kediaman', 'bangunan', 12, 60, 50, ['pemeriksaan_struktur', 'paip_sanitasi']],
            'KEMUDAHAN_AWAM' => ['Kemudahan awam', 'kemudahan', 6, 60, 30, ['pembersihan', 'landskap']],
            'INFRASTRUKTUR' => ['Infrastruktur lain (jalan, saliran, parkir)', 'infrastruktur', 12, 60, 30, ['jalan_parkir', 'saliran']],
            'STRUKTUR' => ['Struktur — pagar, papan tanda, pondok', 'struktur', 12, 120, 20, ['pagar', 'papan_tanda']],
            'PERALATAN' => ['Peralatan dan kelengkapan', 'peralatan', 6, 36, 10, ['servis_peralatan']],
        ] as $kod => [$nama, $jenis, $kitaran, $ambang, $hayat, $pm]) {
            DB::table('aset_kategori')->updateOrInsert(['kod' => $kod], [
                'nama_kategori' => $nama, 'jenis_lalai' => $jenis, 'kitaran_penyelenggaraan_bulan' => $kitaran,
                'tempoh_ambang_penilaian_bulan' => $ambang, 'tempoh_pemeriksaan_bulan' => 12, 'hayat_guna_tahun' => $hayat,
                'kadar_nilai_sisa' => 10, 'jenis_penyelenggaraan_lalai' => json_encode($pm), 'status_aktif' => true, 'susunan' => $i++,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Categories are master data; left in place.
    }
};
