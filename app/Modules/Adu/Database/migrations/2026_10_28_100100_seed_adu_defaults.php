<?php

use App\Modules\Adu\Support\Lalai;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default configuration MAIS can then edit in /admin/adu/konfigurasi
 * (SRS-ADU-08 subcategories, SRS-ADU-12/14 SLA + escalation path per
 * category, SRS-ADU-09 triage keyword and repeat-check rules).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [];
        foreach (Lalai::SUBKATEGORI as $kategori => $subs) {
            $i = 0;
            foreach ($subs as $kod => $nama) {
                $rows[] = ['kod_kategori' => $kategori, 'kod' => $kod, 'nama' => $nama, 'aktif' => true, 'susunan' => $i++, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('subkategori_aduan')->insert($rows);

        DB::table('sla_kategori_aduan')->insert(array_map(fn (string $kategori, array $sla) => [
            'kategori' => $kategori,
            'subkategori' => null,
            'tempoh_respons' => $sla[0],
            'tempoh_penyelesaian' => $sla[1],
            'unit_masa' => 'hari_bekerja',
            'laluan_eskalasi' => json_encode(Lalai::LALUAN_ESKALASI),
            'tarikh_berkuat_kuasa' => '2026-01-01',
            'aktif' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys(Lalai::SLA), Lalai::SLA));

        $rules = [];
        foreach (Lalai::KATA_KUNCI as $kategori => [$keutamaan, $words]) {
            $rules[] = ['jenis_peraturan' => 'kata_kunci', 'kata_kunci' => json_encode($words), 'kategori_cadangan' => $kategori, 'keutamaan_cadangan' => $keutamaan, 'tempoh_semakan_berulang' => null, 'jejari_meter' => null, 'status_aktif' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        $rules[] = ['jenis_peraturan' => 'semakan_berulang', 'kata_kunci' => null, 'kategori_cadangan' => null, 'keutamaan_cadangan' => null, 'tempoh_semakan_berulang' => 30, 'jejari_meter' => 100, 'status_aktif' => true, 'created_at' => $now, 'updated_at' => $now];
        DB::table('peraturan_triaj')->insert($rules);
    }

    public function down(): void
    {
        DB::table('peraturan_triaj')->delete();
        DB::table('sla_kategori_aduan')->delete();
        DB::table('subkategori_aduan')->delete();
    }
};
