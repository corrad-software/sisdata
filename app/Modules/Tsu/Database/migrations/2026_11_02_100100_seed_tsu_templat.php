<?php

use App\Modules\Tsu\Support\Lalai;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SRS-TSU-01: version 1 of the seven process templates (stages, sequence,
 * target durations, mandatory documents, cost categories). Admins create
 * new versions through /api/v1/tsu/templat (SRS-TSU-02); running urusan keep
 * the stages they were generated with.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (Lalai::templat() as $jenis => $t) {
            if (DB::table('templat_proses')->where('jenis_urusan', $jenis)->exists()) {
                continue;
            }
            $id = DB::table('templat_proses')->insertGetId([
                'jenis_urusan' => $jenis, 'versi' => 1, 'nama' => $t['nama'], 'aliran_kelulusan' => $t['aliran'],
                'keterangan' => 'Templat lalai (versi 1) — boleh diubah oleh Pentadbir Sistem.', 'status_aktif' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($t['peringkat'] as $i => [$kod, $nama, $pihak, $hari, $dok, $kos, $lulus, $kesan, $tindakan]) {
                DB::table('templat_peringkat')->insert([
                    'templat_id' => $id, 'no_urutan' => $i + 1, 'kod' => $kod, 'nama_peringkat' => $nama, 'tindakan_diperlukan' => $tindakan,
                    'pihak_bertanggungjawab' => $pihak, 'tempoh_sasaran_hari' => $hari,
                    'senarai_dokumen_wajib' => json_encode($dok), 'senarai_kategori_kos' => json_encode($kos),
                    'perlu_kelulusan' => $lulus, 'kesan' => $kesan, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Templates are configuration data; the tables are dropped by the create migration.
    }
};
