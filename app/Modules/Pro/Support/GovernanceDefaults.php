<?php

namespace App\Modules\Pro\Support;

use Illuminate\Support\Facades\DB;

/**
 * Seeded governance defaults (SRS §4.2–4.4). Idempotent: rows are matched on
 * their natural key and only inserted when missing, so admins' later edits
 * are never overwritten (`php artisan pro:seed-tadbir` re-runs it).
 */
final class GovernanceDefaults
{
    /** SRS §4.3 — komponen, entiti, owner (jawatan, bahagian), steward (jawatan, bahagian), bahagian lot, tanggungjawab */
    public const STEWARDSHIP = [
        ['Lot tanah dan geometri', 'lot_tanah,data_spatial,sesi_import', 'Ketua Bahagian', 'Bahagian Pengurusan Hartanah', 'Pegawai Hartanah', 'Bahagian Pengurusan Hartanah', 'hartanah', 'Ketepatan no_lot, mukim, keluasan, sempadan'],
        ['Hak milik dan pemerolehan', 'hak_milik,pemerolehan', 'Ketua Bahagian', 'Bahagian Pengurusan Hartanah', 'Pegawai Hartanah', 'Bahagian Pengurusan Hartanah', null, 'Kelengkapan dokumen, status pendaftaran'],
        ['Wakaf', 'lot_tanah', 'Ketua Bahagian', 'Bahagian Wakaf', 'Pegawai Wakaf', 'Bahagian Wakaf', 'wakaf', 'Ketepatan rekod pewakaf dan syarat wakaf'],
        ['Baitulmal', 'lot_tanah', 'Ketua Bahagian', 'Bahagian Baitulmal', 'Pegawai Baitulmal', 'Bahagian Baitulmal', 'baitulmal', 'Rekod serahan dan hibah'],
        ['Pajakan dan sewaan', 'kontrak_pajakan', 'Ketua Bahagian', 'Bahagian Pengurusan Hartanah', 'Pegawai Unit Sewaan', 'Unit Sewaan', null, 'Ketepatan terma, kadar dan lejar'],
        ['Cukai dan bayaran', 'cukai', 'Ketua Bahagian', 'Bahagian Kewangan', 'Pegawai Kewangan', 'Bahagian Kewangan', null, 'Rekonsiliasi dengan GRP'],
        ['Kes perundangan', 'kes_mahkamah', 'Ketua Bahagian', 'Bahagian Perundangan', 'Pegawai Perundangan', 'Bahagian Perundangan', null, 'Status kes dan tarikh prosiding'],
        ['Data spatial', 'data_spatial', 'Ketua Bahagian', 'Bahagian Pengurusan Hartanah', 'GIS Specialist', 'Bahagian Pengurusan Hartanah', null, 'Pengesahan sempadan dan metadata spatial'],
        ['Pengguna dan peranan', 'users,peranan', 'Ketua Bahagian', 'Bahagian ICT', 'Pentadbir Sistem', 'Bahagian ICT', null, 'Semakan capaian berkala'],
    ];

    /** SRS-PRO-13 / SRS-DAT-01 — entiti, medan, jenis, ungkapan, mesej, keterukan */
    public const RULES = [
        ['lot_tanah', 'no_lot', 'wajib', [], 'No. lot wajib diisi.', 'ralat'],
        ['lot_tanah', 'no_lot', 'format', ['regex' => '^(Lot|PT) [0-9]{1,7}[A-Z]?$'], 'No. lot mesti dalam format "Lot 1234" atau "PT 1234".', 'ralat'],
        ['lot_tanah', 'kod_mukim', 'wajib', [], 'Mukim wajib dipilih.', 'ralat'],
        ['lot_tanah', 'kod_mukim', 'rujukan', ['senarai' => 'ref_mukim'], 'Kod mukim tidak wujud dalam senarai rujukan MOD-REF.', 'ralat'],
        ['lot_tanah', 'kod_daerah', 'rujukan', ['senarai' => 'ref_daerah'], 'Kod daerah tidak wujud atau tidak sepadan dengan mukim.', 'ralat'],
        ['lot_tanah', 'keluasan', 'wajib', [], 'Keluasan wajib diisi.', 'ralat'],
        ['lot_tanah', 'keluasan', 'julat', ['min' => 0.0001, 'max' => 100000000], 'Keluasan mesti melebihi 0 dan tidak melebihi 100,000,000.', 'ralat'],
        ['lot_tanah', 'unit_keluasan', 'senarai', ['nilai' => ['m2', 'hektar', 'ekar']], 'Unit keluasan mesti m2, hektar atau ekar.', 'ralat'],
        ['lot_tanah', 'bahagian_pemilik', 'wajib', [], 'Bahagian pemilik MAIS wajib dipilih.', 'ralat'],
        ['lot_tanah', 'bahagian_pemilik', 'senarai', ['nilai' => ['hartanah', 'wakaf', 'baitulmal']], 'Bahagian pemilik mesti hartanah, wakaf atau baitulmal.', 'ralat'],
        ['lot_tanah', 'kategori_guna_tanah', 'rujukan', ['senarai' => 'ref_kategori_guna_tanah'], 'Kategori guna tanah tidak wujud dalam senarai rujukan.', 'ralat'],
        ['lot_tanah', 'klasifikasi', 'senarai', ['nilai' => ['awam', 'dalaman', 'sulit', 'rahsia']], 'Klasifikasi mesti awam, dalaman, sulit atau rahsia.', 'ralat'],
        ['lot_tanah', 'status_lot', 'senarai', ['nilai' => ['draf', 'aktif', 'tidak_aktif']], 'Status lot mesti draf, aktif atau tidak aktif.', 'ralat'],
        ['lot_tanah', 'zoning', 'panjang', ['max' => 50], 'Zoning tidak boleh melebihi 50 aksara.', 'ralat'],
        ['lot_tanah', 'no_lot', 'unik', ['skop' => ['kod_mukim']], 'No. lot ini telah didaftarkan dalam mukim yang sama.', 'ralat'],
        ['lot_tanah', 'kategori_guna_tanah', 'lengkap', [], 'Kategori guna tanah belum diisi.', 'amaran'],
        ['lot_tanah', 'id_data_steward', 'lengkap', [], 'Data steward belum ditetapkan.', 'amaran'],
        ['data_spatial', 'sumber_data', 'senarai', ['nilai' => ['jupem', 'ptg', 'pbt', 'juruukur', 'lakaran', 'satelit', 'sintetik']], 'Sumber data spatial tidak sah.', 'ralat'],
        ['data_spatial', 'srid_asal', 'senarai', ['nilai' => [4326, 4742, 3375, 3380, 3168, 4245]], 'Sistem unjuran (SRID) tidak disokong.', 'ralat'],
        ['data_spatial', 'keluasan_dikira', 'julat', ['peratus_beza_maks' => 10], 'Keluasan dikira berbeza lebih 10% daripada keluasan diisytihar.', 'amaran'],
    ];

    /** SRS-PRO-14 — komponen, entiti, aras, encryption, peranan, kebenaran */
    public const CLASSIFICATION = [
        ['Lot tanah', 'lot_tanah', 'dalaman', 'TLS 1.3 semasa penghantaran; storan pangkalan data tersulit (AES-256, peringkat cakera)', 'Pegawai MAIS mengikut skop peranan', 'pro.lot.lihat'],
        ['Hak milik', 'hak_milik', 'sulit', 'TLS 1.3; AES-256 peringkat cakera; medan No. KP disamarkan', 'Pegawai yang diberi kuasa khusus', 'hkm.hak_milik.lihat'],
        ['Pemerolehan', 'pemerolehan', 'sulit', 'TLS 1.3; AES-256 peringkat cakera', 'Pegawai yang diberi kuasa khusus', 'per.kes.lihat'],
        ['Penilaian', 'penilaian', 'rahsia', 'TLS 1.3; AES-256; setiap paparan diaudit', 'Capaian terhad dan diaudit', 'pro.rahsia.lihat'],
        ['Kontrak dan pajakan', 'kontrak_pajakan', 'sulit', 'TLS 1.3; AES-256 peringkat cakera', 'Pegawai yang diberi kuasa khusus', 'pro.sulit.lihat'],
        ['Cukai', 'cukai', 'dalaman', 'TLS 1.3; AES-256 peringkat cakera', 'Pegawai MAIS mengikut skop peranan', 'pro.lot.lihat'],
        ['Dokumen digital', 'dokumen', 'dalaman', 'Penyulitan sampul AES-256 setiap fail (MOD-DMS)', 'Mengikut rekod induk', 'dms.dokumen.lihat'],
        ['Aduan dan isu', 'aduan', 'dalaman', 'TLS 1.3; AES-256 peringkat cakera', 'Pegawai MAIS mengikut skop peranan', 'pro.lot.lihat'],
        ['Kes mahkamah', 'kes_mahkamah', 'rahsia', 'TLS 1.3; AES-256; setiap paparan diaudit', 'Capaian terhad dan diaudit', 'pro.rahsia.lihat'],
        ['Data spatial', 'data_spatial', 'dalaman', 'TLS 1.3; AES-256 peringkat cakera', 'Pegawai MAIS mengikut skop peranan', 'pro.lot.lihat'],
    ];

    /** SRS-PRO-05 curated metadata — jadual, medan, nama perniagaan, definisi, format, nilai dibenarkan, wajib, klasifikasi */
    public const DICTIONARY = [
        ['lot_tanah', null, 'Lot Tanah', 'Rekod induk setiap lot tanah milik atau di bawah pengurusan MAIS. Semua rekod satelit memegang lot_id.', null, null, null, 'dalaman'],
        ['lot_tanah', 'id', 'ID Lot', 'Pengecam unik rekod lot (UUID).', 'UUID', null, true, 'dalaman'],
        ['lot_tanah', 'no_lot', 'No. Lot', 'Nombor lot atau PT seperti dalam hak milik / pelan akui.', '"Lot 1234" atau "PT 1234"', null, true, 'dalaman'],
        ['lot_tanah', 'kod_mukim', 'Mukim', 'Kod mukim MOD-REF (6 digit).', '6 digit', 'ref_mukim', true, 'dalaman'],
        ['lot_tanah', 'kod_daerah', 'Daerah', 'Kod daerah MOD-REF (4 digit), mesti sepadan dengan mukim.', '4 digit', 'ref_daerah', true, 'dalaman'],
        ['lot_tanah', 'kod_negeri', 'Negeri', 'Kod negeri MOD-REF.', '2 digit', 'ref_negeri', true, 'dalaman'],
        ['lot_tanah', 'keluasan', 'Keluasan', 'Keluasan lot seperti diisytihar dalam hak milik.', 'Perpuluhan (16,4)', '> 0', true, 'dalaman'],
        ['lot_tanah', 'unit_keluasan', 'Unit Keluasan', 'Unit bagi keluasan.', null, 'm2, hektar, ekar', true, 'dalaman'],
        ['lot_tanah', 'kategori_guna_tanah', 'Kategori Guna Tanah', 'Kategori kegunaan tanah (KTN s.52).', null, 'ref_kategori_guna_tanah', false, 'dalaman'],
        ['lot_tanah', 'zoning', 'Zoning', 'Zoning rancangan tempatan.', 'Teks ≤ 50', null, false, 'dalaman'],
        ['lot_tanah', 'syarat_nyata', 'Syarat Nyata', 'Syarat nyata seperti dalam dokumen hak milik.', 'Teks', null, false, 'sulit'],
        ['lot_tanah', 'bahagian_pemilik', 'Bahagian Pemilik MAIS', 'Bahagian MAIS yang memiliki/mengurus lot.', null, 'hartanah, wakaf, baitulmal', true, 'dalaman'],
        ['lot_tanah', 'status_lot', 'Status Lot', 'Kitaran hayat rekod lot.', null, 'draf, aktif, tidak_aktif, digabung, arkib', true, 'dalaman'],
        ['lot_tanah', 'klasifikasi', 'Klasifikasi', 'Aras kerahsiaan rekod (SRS §4.2).', null, 'awam, dalaman, sulit, rahsia', true, 'dalaman'],
        ['lot_tanah', 'id_data_owner', 'Data Owner', 'Pegawai pemilik data (SRS §4.3).', 'ID pengguna', null, false, 'dalaman'],
        ['lot_tanah', 'id_data_steward', 'Data Steward', 'Pegawai pengawas data yang bertanggungjawab atas ketepatan rekod.', 'ID pengguna', null, false, 'dalaman'],
        ['lot_tanah', 'versi_rekod', 'Versi Rekod', 'Nombor versi semasa rekod induk (SRS-PRO-22).', 'Integer', null, true, 'dalaman'],
        ['lot_tanah', 'koordinat_pusat', 'Koordinat Pusat', 'Titik di dalam poligon lot (WGS84).', 'POINT 4326', null, false, 'dalaman'],
        ['lot_tanah', 'sumber_sistem', 'Sistem Sumber', 'Salasilah data: sistem asal rekod (SRS-DAT-08).', null, null, false, 'dalaman'],
        ['lot_tanah', 'kunci_asal', 'Kunci Asal', 'Salasilah data: kunci rekod dalam sistem sumber.', null, null, false, 'dalaman'],
        ['data_spatial', null, 'Data Spatial', 'Geometri sempadan lot beserta metadata sumber dan pengesahan (SRS-PRO-07).', null, null, null, 'dalaman'],
        ['data_spatial', 'geometri', 'Geometri Poligon', 'Sempadan lot sebagai MULTIPOLYGON WGS84.', 'MULTIPOLYGON 4326', null, true, 'dalaman'],
        ['data_spatial', 'srid_asal', 'Sistem Unjuran Asal', 'SRID fail sumber sebelum ditukar ke 4326.', 'Integer', '4326, 4742, 3375, 3380, 3168, 4245', true, 'dalaman'],
        ['data_spatial', 'sumber_data', 'Sumber Data Spatial', 'Asal geometri.', null, 'jupem, ptg, pbt, juruukur, lakaran, satelit, sintetik', true, 'dalaman'],
        ['data_spatial', 'status_pengesahan', 'Status Pengesahan', 'Status pengesahan geometri (SRS-PRO-10).', null, 'belum_disahkan, disahkan_juruukur, disahkan_ptg, bertindih', true, 'dalaman'],
        ['data_spatial', 'keluasan_dikira', 'Keluasan Dikira', 'Keluasan poligon dikira atas elipsoid (m²).', 'Perpuluhan', null, false, 'dalaman'],
        ['data_spatial', 'id_pegawai_pengesah', 'Pegawai Pengesah', 'Pegawai yang mengesahkan geometri.', 'ID pengguna', null, false, 'dalaman'],
        ['penilaian', 'nilai_pasaran', 'Nilai Pasaran', 'Nilai pasaran lot daripada penilaian.', 'RM', null, false, 'rahsia'],
        ['kes_mahkamah', null, 'Kes Mahkamah', 'Kes perundangan berkaitan lot (N:N).', null, null, null, 'rahsia'],
        ['kontrak_pajakan', null, 'Kontrak Pajakan', 'Kontrak pajakan / sewaan bagi lot.', null, null, null, 'sulit'],
    ];

    /** SRS-PRO-23 — kategori, keterangan, syarat, tahun, rujukan */
    public const RETENTION = [
        ['lot_tidak_aktif', 'Lot berstatus tidak aktif (dilupuskan / diserah balik)', ['status_lot' => ['tidak_aktif']], 7, 'Dasar Rekod MAIS (perlu disahkan)'],
        ['lot_digabung', 'Rekod lot pendua yang telah digabungkan', ['status_lot' => ['digabung']], 2, 'Dasar Rekod MAIS (perlu disahkan)'],
    ];

    public static function seed(): void
    {
        $now = now();

        foreach (self::STEWARDSHIP as [$komponen, $entiti, $jOwner, $bOwner, $jSteward, $bSteward, $bahagian, $tugas]) {
            if (! DB::table('daftar_stewardship_data')->where('komponen_data', $komponen)->exists()) {
                DB::table('daftar_stewardship_data')->insert([
                    'komponen_data' => $komponen, 'entiti_berkaitan' => $entiti,
                    'jawatan_data_owner' => $jOwner, 'bahagian_data_owner' => $bOwner,
                    'jawatan_data_steward' => $jSteward, 'bahagian_data_steward' => $bSteward,
                    'bahagian_pemilik' => $bahagian, 'peranan_tanggungjawab' => $tugas,
                    'tarikh_berkuat_kuasa' => $now->toDateString(), 'status' => 'aktif',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach (self::RULES as [$entiti, $medan, $jenis, $ungkapan, $mesej, $tahap]) {
            if (! DB::table('peraturan_kualiti_data')->where(['entiti' => $entiti, 'medan' => $medan, 'jenis_peraturan' => $jenis])->exists()) {
                DB::table('peraturan_kualiti_data')->insert([
                    'entiti' => $entiti, 'medan' => $medan, 'jenis_peraturan' => $jenis,
                    'ungkapan_peraturan' => json_encode((object) $ungkapan), 'mesej_ralat' => $mesej,
                    'tahap_keterukan' => $tahap, 'status_aktif' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach (self::CLASSIFICATION as [$komponen, $entiti, $aras, $enc, $peranan, $kebenaran]) {
            DB::table('klasifikasi_data')->insertOrIgnore([
                'komponen_data' => $komponen, 'entiti' => $entiti, 'aras_kerahsiaan' => $aras,
                'keperluan_encryption' => $enc, 'peranan_dibenarkan' => $peranan, 'kebenaran_diperlukan' => $kebenaran,
                'tarikh_semakan' => $now->toDateString(), 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (self::DICTIONARY as [$jadual, $medan, $nama, $definisi, $format, $nilai, $wajib, $klas]) {
            if (! DB::table('kamus_data')->where('nama_jadual', $jadual)->where('nama_medan', $medan)->exists()) {
                DB::table('kamus_data')->insert([
                    'nama_jadual' => $jadual, 'nama_medan' => $medan, 'nama_perniagaan' => $nama, 'definisi' => $definisi,
                    'format' => $format, 'nilai_dibenarkan' => $nilai, 'wajib' => $wajib, 'klasifikasi' => $klas,
                    'data_owner' => 'Bahagian Pengurusan Hartanah', 'data_steward' => str_starts_with($jadual, 'data_spatial') ? 'GIS Specialist' : 'Pegawai Hartanah',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach (self::RETENTION as [$kategori, $keterangan, $syarat, $tahun, $rujukan]) {
            DB::table('jadual_retensi')->insertOrIgnore([
                'kategori_rekod' => $kategori, 'keterangan' => $keterangan, 'entiti' => 'lot_tanah',
                'syarat' => json_encode($syarat), 'tempoh_retensi_tahun' => $tahun, 'tindakan_selepas_retensi' => 'arkib',
                'rujukan_dasar_mais' => $rujukan, 'memerlukan_kelulusan' => true, 'aktif' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
