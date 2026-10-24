<?php

return [
    'penapis_tidak_sah' => 'Nilai penapis tidak sah.',
    'tiada_maklumat' => 'Tiada maklumat',
    'tiada_data' => 'tiada data',
    'ya' => 'Ya',
    'tidak' => 'Tidak',
    'telah_tamat' => 'Telah tamat tempoh',
    'dalam_sla' => 'Dalam SLA',
    'lewat_sla' => 'Melepasi SLA',
    'hasil_sewaan' => 'Hasil sewaan dikutip',
    'kos_cukai' => 'Cukai dibayar',
    'draf_disimpan' => 'disimpan :masa',
    'ralat_kpi' => 'Ralat pengiraan',
    'aras_tidak_sah' => 'Aras dashboard tidak sah.',
    'aras_tiada_kebenaran' => 'Anda tiada kebenaran untuk aras dashboard ini.',
    'kpi_tiada' => 'Indikator tidak ditemui atau tidak menyokong senarai rekod.',

    'kpi' => [
        'bilangan_lot' => 'Jumlah tanah / aset',
        'keluasan' => 'Keluasan tanah',
        'lot_bahagian' => 'Tanah mengikut bahagian',
        'nilai_portfolio' => 'Nilai portfolio semasa',
        'hak_milik' => 'Hak milik berkuat kuasa',
        'status_tanah' => 'Status tanah (diguna)',
        'hampir_tamat' => 'Hak milik hampir tamat tempoh',
        'belum_disahkan' => 'Hak milik belum disahkan',
        'pemerolehan_tahunan' => 'Pemerolehan selesai (tahun)',
        'per_dalam_proses' => 'Kes pemerolehan dalam proses',
        'per_melebihi_tempoh' => 'Pemerolehan melebihi tempoh sasaran',
        'tugasan_tertunggak' => 'Tindakan tertunggak',
        'tugasan_lewat_sla' => 'Tugasan melepasi SLA',
        'dalam_sla' => 'Tugasan selesai dalam SLA',
        'tugasan_saya' => 'Tugasan saya',
        'notifikasi' => 'Notifikasi belum dibaca',
        'storan' => 'Storan dokumen',
        'kes_aktif' => 'Kes mahkamah aktif',
        'draf_saya' => 'Draf transaksi saya',
        'hasil_kos' => 'Hasil berbanding kos (bersih)',
    ],

    'matriks' => ['daerah_kategori' => 'Taburan lot mengikut daerah dan kategori guna tanah'],

    'lajur' => [
        'no_lot' => 'No. lot', 'daerah' => 'Daerah', 'mukim' => 'Mukim', 'kategori' => 'Kategori guna tanah', 'bahagian' => 'Bahagian',
        'hektar' => 'Keluasan (ha)', 'no_hak_milik' => 'No. hak milik', 'jenis' => 'Jenis', 'status' => 'Status', 'tarikh_tamat' => 'Tarikh tamat',
        'disahkan' => 'Disahkan', 'no_kes' => 'No. kes', 'tajuk' => 'Tajuk', 'saluran' => 'Saluran', 'tarikh_mula' => 'Tarikh mula',
        'modul' => 'Modul', 'tarikh_akhir' => 'Tarikh akhir',
    ],

    'insight' => [
        'merah' => ':kpi berada pada tahap merah (:nilai) — perlu tindakan segera.',
        'kuning' => ':kpi menghampiri ambang (:nilai) — perlu dipantau.',
        'naik' => ':kpi meningkat :peratus% pada tempoh :tempoh berbanding tempoh sebelumnya.',
        'turun' => ':kpi menurun :peratus% pada tempoh :tempoh berbanding tempoh sebelumnya.',
        'dominan' => ':pecahan menyumbang :peratus% daripada :kpi.',
        'tiada_isu' => 'Tiada indikator yang melepasi ambang pada paparan ini.',
    ],

    'ai' => [
        'terhad' => 'Anda tidak dibenarkan melihat data daerah :daerah; jawapan dihadkan kepada skop capaian anda.',
        'tidak_difahami' => 'Maaf, soalan tidak dapat dipadankan dengan mana-mana indikator yang anda dibenarkan lihat. Cuba sebut indikator, cth. "jumlah tanah", "kadar kutipan" atau "aduan terbuka".',
        'susun_atur_tiada' => 'Tiada widget yang sepadan untuk diubah.',
        'susun_atur_tambah' => ':bil widget dipaparkan di bahagian atas dashboard anda.',
        'susun_atur_buang' => ':bil widget disembunyikan daripada dashboard anda.',
        'audit_terhad' => 'Prompt AI meminta data di luar skop RBAC pengguna — jawapan dihadkan kepada skop pengguna.',
    ],

    'aras' => ['eksekutif' => 'Eksekutif', 'pengurusan' => 'Pengurusan', 'operasi' => 'Operasi'],

    'eksport' => [
        'tajuk' => 'Dashboard Eksekutif SISDATA — Aras :aras',
        'tanda_air' => 'SISDATA · :nama (:emel) · :masa',
        'dijana_oleh' => 'Dijana oleh :nama pada :masa',
        'penapis' => 'Penapis: :penapis',
        'tiada_penapis' => 'tiada (semua dalam skop)',
        'data_pada' => 'Data dikira pada: :masa',
        'ringkasan' => 'Ringkasan indikator',
        'indikator' => 'Indikator', 'domain' => 'Domain', 'nilai' => 'Nilai', 'unit' => 'Unit', 'sasaran' => 'Sasaran',
        'status' => 'Status', 'dikira_pada' => 'Dikira pada', 'pecahan' => 'Pecahan',
    ],

    'penapis' => [
        'tahun' => 'Tahun', 'kod_daerah' => 'Daerah', 'kod_mukim' => 'Mukim', 'bahagian' => 'Bahagian',
        'kategori' => 'Kategori tanah', 'status' => 'Status', 'pegawai' => 'Pegawai', 'modul' => 'Modul',
    ],

    'domain' => ['portfolio' => 'Portfolio', 'hasil' => 'Hasil', 'risiko' => 'Risiko', 'operasi' => 'Operasi', 'pematuhan' => 'Pematuhan'],

    'lampu' => ['hijau' => 'Hijau', 'kuning' => 'Kuning', 'merah' => 'Merah'],

    'modul' => [
        'pro' => 'Profil Hartanah', 'hkm' => 'Hak Milik', 'per' => 'Pemerolehan', 'wfe' => 'Workflow', 'ntf' => 'Notifikasi',
        'dms' => 'Dokumen', 'kes' => 'Kes Mahkamah', 'dsh' => 'Dashboard', 'pjk' => 'Pajakan', 'cki' => 'Cukai', 'adu' => 'Aduan', 'ptn' => 'Pemantauan Tanah',
    ],

    'susun_atur_disimpan' => 'Susun atur dashboard disimpan.',
    'susun_atur_set_semula' => 'Susun atur dashboard dikembalikan kepada lalai.',
    'paparan_disimpan' => 'Paparan kegemaran disimpan.',
    'paparan_dipadam' => 'Paparan kegemaran dipadam.',
    'draf_terlalu_besar' => 'Kandungan draf terlalu besar (maksimum 256 KB).',
    'draf_disimpan_ok' => 'Draf disimpan.',
    'draf_dibuang' => 'Draf dibuang.',
    'langganan_disimpan' => 'Langganan ringkasan dashboard disimpan.',
    'langganan_dipadam' => 'Langganan ringkasan dashboard dibatalkan.',
    'snapshot_dijana' => 'Snapshot KPI dijana.',
    'tetapan_disimpan' => 'Tetapan dashboard disimpan.',
    'tetapan_dipadam' => 'Tetapan dashboard dipadam.',
    'widget_tidak_sah' => 'Kod widget / indikator tidak sah.',
    'ambang_tidak_konsisten' => 'Ambang hijau mesti lebih baik daripada ambang kuning mengikut arah indikator.',
];
