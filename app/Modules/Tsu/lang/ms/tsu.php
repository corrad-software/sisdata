<?php

return [
    'disimpan' => 'Maklumat disimpan.',
    'aliran_tiada' => 'Aliran kelulusan :kod belum diaktifkan dalam Modul Workflow. Hubungi Pentadbir Sistem.',

    'urusan' => [
        'didaftar' => 'Urusan :no telah didaftarkan.',
        'penyatuan_dua_lot' => 'Penyatuan memerlukan sekurang-kurangnya dua lot.',
        'lot_tiada' => 'Lot tidak dijumpai atau di luar skop capaian anda.',
        'lot_arkib' => 'Lot :no_lot telah diarkibkan dan tidak boleh digunakan untuk urusan baharu.',
        'urusan_wujud' => 'Lot ini sudah mempunyai urusan jenis yang sama yang masih dalam proses.',
        'templat_tiada' => 'Tiada templat proses aktif bagi jenis urusan ini.',
        'dikunci' => 'Urusan telah ditutup dan dikunci — rekod tidak boleh diubah.',
        'tidak_terbuka' => 'Urusan ini tidak lagi dalam proses.',
        'belum_selesai' => 'Urusan hanya boleh ditutup selepas semua peringkat selesai, ditolak atau dibatalkan.',
        'aliran_berjalan' => 'Kelulusan sedang berjalan dalam Modul Workflow — tarik balik dahulu sebelum mengubah.',
        'ditutup' => 'Urusan ditutup dan dikunci sebagai rekod audit.',
        'dibatalkan' => 'Urusan dibatalkan.',
    ],

    'peringkat' => [
        'bukan_aktif' => 'Peringkat semasa tidak dalam status sedang berjalan.',
        'dokumen_wajib' => 'Dokumen wajib belum lengkap: :senarai.',
        'dihantar_kelulusan' => 'Peringkat dihantar untuk kelulusan melalui Modul Workflow.',
        'selesai' => 'Peringkat diselesaikan; peringkat seterusnya bermula.',
    ],

    'syarat' => [
        'lantikan' => 'Lantikan jurukur berlesen belum direkodkan.',
        'hasil_ukur' => 'Tiada hasil ukur yang telah diterima.',
        'rujukan_etanah' => 'Nombor rujukan e-Tanah belum direkodkan.',
        'rujukan_ptd' => 'Nombor rujukan Pejabat Tanah belum direkodkan.',
        'kos' => 'Masih ada kos peringkat ini yang belum dibayar.',
        'penilaian' => 'Laporan penilaian JPPH terkini (beserta nilai) belum dilampirkan atau telah luput.',
        'pemindahan' => 'Pemindahan rekod ke lot baharu belum selesai disahkan.',
        'lot_baharu' => 'Tiada hasil ukur diterima dengan nombor lot baharu untuk mencipta lot.',
    ],

    'pelupusan' => [
        'justifikasi_wajib' => 'Justifikasi bertulis pelupusan wajib diisi sebelum kelulusan.',
        'penilaian_wajib' => 'Laporan penilaian JPPH terkini wajib dilampirkan sebelum kelulusan pelupusan.',
        'tiada_aliran_tertinggi' => 'Kelulusan tidak direkodkan: keputusan peringkat kelulusan tertinggi tidak ditemui.',
        'mfa_wajib' => 'Kelulusan tidak direkodkan: peringkat kelulusan tertinggi mesti disahkan dengan MFA.',
        'justifikasi_pelulus_wajib' => 'Kelulusan tidak direkodkan: pelulus tertinggi mesti memberi justifikasi bertulis.',
    ],

    'keputusan' => [
        'lulus' => 'Diluluskan', 'tolak' => 'Ditolak', 'ditarik_balik' => 'Ditarik balik', 'dibatalkan' => 'Dibatalkan', 'tidak_sah' => 'Kelulusan tidak sah',
        'tutup' => 'Urusan ditutup', 'batal' => 'Urusan dibatalkan', 'lot_baharu_dicipta' => 'Lot baharu dicipta', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak',
        'kembali' => 'Dikembalikan', 'tangguh' => 'Ditangguhkan', 'selesai' => 'Selesai',
    ],

    'kesan' => [
        'sebab_syarat' => 'Tukar syarat diluluskan dan didaftarkan (urusan :no).',
        'sebab_lupus' => 'Lot dilupuskan (urusan :no).',
        'sebab_lot_baharu' => ':jenis diluluskan (urusan :no).',
        'sebab_keluasan' => 'Keluasan baharu daripada hasil ukur yang disahkan (urusan :no).',
        'cadangan_hkm' => 'Cadangan daripada MOD-TSU: :jenis :no.',
    ],

    'pemindahan' => [
        'tertunda' => 'Masih ada pemindahan rekod yang belum disahkan.',
        'sedang_disahkan' => 'Pemetaan sedang menunggu pengesahan dan tidak boleh diubah.',
        'tiada_lot_baharu' => 'Lot baharu belum dicipta bagi urusan ini.',
        'pemetaan_tidak_sah' => 'Pemetaan tidak sah: lot asal / lot baharu / jenis rekod tidak sepadan dengan urusan.',
        'rekod_tiada' => 'Rekod tidak ditemui pada lot asal.',
        'tiada_draf' => 'Tiada pemetaan draf untuk dihantar.',
        'dua_pengesah' => 'Pemindahan memerlukan dua pengesah berbeza yang bukan pembuat pemetaan.',
        'disimpan' => ':n pemetaan rekod disimpan sebagai draf.',
        'dihantar' => 'Pemetaan dihantar untuk pengesahan dua peringkat.',
    ],

    'ukur' => [
        'srid_tidak_disokong' => 'Sistem koordinat EPSG::srid tidak disokong. Pilih salah satu: :senarai.',
        'shp_zip' => 'Shapefile mesti dimuat naik sebagai fail ZIP (.shp, .shx, .dbf, .prj).',
        'format_tidak_disokong' => 'Format fail tidak disokong (DXF, Shapefile atau GeoJSON sahaja).',
        'fail_tidak_sah' => 'Fail :format tidak sah atau tidak dapat dibaca.',
        'crs_tidak_sepadan' => 'Sistem koordinat dalam fail (EPSG::fail) tidak sepadan dengan yang diisytihar (EPSG::diisytihar).',
        'tiada_poligon' => 'Fail tidak mengandungi poligon.',
        'dxf_tiada_poligon' => 'Fail DXF tidak mengandungi polyline tertutup (LWPOLYLINE / POLYLINE).',
        'lot_bukan_urusan' => 'Lot bukan sebahagian daripada urusan ini.',
        'lantikan_bukan_urusan' => 'Lantikan bukan bagi urusan ini.',
        'geometri_tidak_sah' => 'Geometri tidak sah.',
        'luar_malaysia' => 'Koordinat berada di luar Malaysia — semak sistem koordinat (EPSG::srid) yang diisytihar.',
        'sudah_disahkan' => 'Hasil ukur ini telah disahkan.',
        'pengesah_sama' => 'Pegawai yang mengimport hasil ukur tidak boleh mengesahkannya sendiri.',
        'luar_toleransi_justifikasi' => 'Sisihan keluasan melebihi toleransi — catatan justifikasi wajib untuk menerima.',
        'pelan_bukan_urusan' => 'Dokumen pelan akui tidak dilampirkan pada urusan ini.',
        'bukan_lantikan_anda' => 'Anda hanya boleh mengemas kini kemajuan bagi lantikan anda sendiri.',
        'lantikan_tidak_aktif' => 'Lantikan tidak aktif.',
        'peratus_menurun' => 'Peratus siap tidak boleh kurang daripada laporan terakhir (:semasa%).',
        'kemajuan_lewat' => 'Kemajuan direkodkan — kerja ukur terkebelakang daripada jadual; pegawai dan ketua bahagian dimaklumkan.',
        'kemajuan_direkod' => 'Kemajuan kerja ukur direkodkan.',
        'diimport' => ':n hasil ukur diimport dan menunggu pengesahan.',
        'disahkan_diterima' => 'Hasil ukur diterima; invois upah ukur direkodkan untuk diproses Bahagian Kewangan.',
        'disahkan_ditolak' => 'Hasil ukur ditolak.',
    ],

    'sebutharga' => [
        'jurukur_tidak_layak' => 'Hanya jurukur panel aktif dengan lesen sah boleh dijemput.',
        'minimum' => 'Sekurang-kurangnya :n jurukur panel mesti dijemput.',
        'tarikh_tutup' => 'Tarikh tutup mesti selepas masa sekarang.',
        'sudah_tutup' => 'Sebut harga telah ditutup — tawaran tidak lagi diterima.',
        'terkawal' => 'Nilai tawaran disimpan terkawal dan hanya boleh dilihat selepas tarikh tutup.',
        'belum_tutup' => 'Penilaian hanya boleh dibuat selepas tarikh tutup.',
        'pengasingan_penilai' => 'Pengasingan tugas: pendaftar urusan tidak boleh menilai sebut harga.',
        'tawaran_tidak_sah' => 'Tawaran yang disyorkan tidak sah.',
        'pengasingan_pelulus' => 'Pengasingan tugas: pendaftar urusan atau penilai tidak boleh meluluskan lantikan.',
        'belum_lulus' => 'Pemilihan jurukur belum diluluskan.',
        'sudah_dilantik' => 'Lantikan telah direkodkan bagi sebut harga ini.',
        'dijemput' => 'Jemputan sebut harga :no dijana.',
        'tawaran_direkod' => 'Tawaran direkodkan.',
        'dihantar_kelulusan' => 'Pengesyoran dihantar kepada pegawai berkuasa untuk kelulusan.',
        'dilantik' => 'Lantikan jurukur direkodkan.',
    ],

    'kos' => [
        'peringkat_bukan_urusan' => 'Peringkat bukan bagi urusan ini.',
        'sudah_dibayar' => 'Amaun kos yang telah dibayar tidak boleh diubah.',
        'resit_wajib' => 'No. resit dan tarikh bayaran wajib bagi kos yang dibayar.',
        'dokumen_bukan_urusan' => 'Dokumen resit tidak dilampirkan pada urusan ini.',
        'direkod' => 'Kos direkodkan.',
    ],

    'dokumen' => [
        'penilaian_rahsia' => 'Laporan penilaian adalah maklumat Rahsia — anda tiada kebenaran memuat naiknya.',
        'nilai_wajib' => 'Nilai penilaian dan tarikh laporan wajib bagi laporan penilaian JPPH.',
        'dimuat_naik' => 'Dokumen dimuat naik ke Modul Dokumen Digital.',
        'tiada_capaian' => 'Anda tiada capaian kepada dokumen ini.',
    ],

    'ptd' => [
        'tidak_tersedia' => 'Penyedia integrasi Pejabat Tanah tidak tersedia bagi daerah ini.',
        'sudah_dihantar' => 'Permohonan telah dihantar ke Pejabat Tanah.',
        'guna_manual' => 'API Pejabat Tanah tidak tersedia / di luar liputan — rekod rujukan dan status secara manual.',
        'dalam_liputan' => 'Daerah ini diliputi API berpusat — status dikemas kini secara automatik.',
        'luar_liputan' => 'Daerah ini di luar liputan API berpusat — gunakan kemas kini manual.',
        'dihantar' => 'Permohonan dihantar ke Pejabat Tanah (rujukan :no).',
        'dikemas_kini' => 'Status Pejabat Tanah dikemas kini.',
        'tiada_perubahan' => 'Tiada perubahan status di Pejabat Tanah.',
    ],

    'templat' => [
        'versi_baharu' => 'Templat versi :versi diaktifkan. Urusan sedia ada kekal dengan peringkat asal.',
    ],
];
