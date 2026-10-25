<?php

use App\Modules\Tkt\Support\Lalai;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeded configuration (editable afterwards by administrators, SRS-TKT-08):
 * SLA per severity (ASSUMED figures — SRS §18.2 has none, PLAN §14 #9), the
 * L1 MAIS helpdesk and L2 vendor teams, a catch-all assignment rule, the
 * central complaints system mapping (SRS-TKT-15).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (Lalai::sla() as $severity => $row) {
            DB::table('tkt_sla_konfigurasi')->insert([
                'keterukan' => $severity,
                'ambang_respons' => $row['ambang_respons'],
                'ambang_penyelesaian' => $row['ambang_penyelesaian'],
                'waktu_perkhidmatan' => $row['waktu_perkhidmatan'],
                'ambang_amaran_peratus' => $row['ambang_amaran_peratus'],
                'hierarki_eskalasi' => json_encode($row['hierarki_eskalasi']),
                'tarikh_berkuat_kuasa' => '2026-01-01',
                'aktif' => true,
                'catatan' => 'Nilai andaian — gantikan dengan sasaran kontrak (SRS §18.2 tiada angka).',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $l1 = DB::table('tkt_pasukan')->insertGetId([
            'kod' => 'L1_MAIS', 'nama' => 'Meja Bantuan MAIS (L1)', 'aras' => 'L1', 'jenis' => 'dalaman', 'modul' => null, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('tkt_pasukan')->insert([
            'kod' => 'L2_VENDOR', 'nama' => 'Sokongan Vendor (L2)', 'aras' => 'L2', 'jenis' => 'vendor', 'nama_vendor' => 'Syarikat Pelaksana SISDATA', 'modul' => null, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('tkt_peraturan_penugasan')->insert([
            'id_pasukan' => $l1, 'keutamaan' => 0, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach (Lalai::PEMETAAN as $jenis => $pairs) {
            foreach ($pairs as $local => $central) {
                DB::table('tkt_pemetaan_aduan')->insert([
                    'jenis' => $jenis, 'nilai_tempatan' => (string) $local, 'nilai_berpusat' => $central, 'arah' => 'dua_hala', 'aktif' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['tkt_pemetaan_aduan', 'tkt_peraturan_penugasan', 'tkt_pasukan', 'tkt_sla_konfigurasi'] as $table) {
            DB::table($table)->delete();
        }
    }
};
