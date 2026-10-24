<?php

return [
    'saved' => 'Perubahan disimpan.',

    'attributes' => [
        'nilai' => 'nilai',
        'justifikasi' => 'justifikasi',
        'catatan' => 'catatan',
        'jenis_kod' => 'jenis kod',
        'rekod' => 'rekod',
        'fail' => 'fail',
        'kod' => 'kod',
        'nama' => 'nama',
        'kategori' => 'kategori',
        'arahan' => 'arahan',
        'jenis_jadual' => 'jenis jadual',
        'ungkapan_cron' => 'ungkapan cron',
        'selang_minit' => 'selang (minit)',
        'kunci' => 'kunci',
        'format_output' => 'format output',
        'kandungan' => 'kandungan',
        'medan' => 'medan',
        'endpoint' => 'endpoint',
        'tempoh_tamat_masa' => 'tempoh tamat masa',
        'kadar_capaian' => 'kadar capaian',
        'jadual' => 'jadual',
        'status' => 'status',
        'jenis' => 'jenis',
        'istilah' => 'istilah',
        'sinonim' => 'sinonim',
        'tamat_hari' => 'tempoh sah (hari)',
    ],

    'parameter' => [
        'unknown' => 'Parameter tidak wujud.',
        'value' => 'nilai',
        'security_scope' => 'Parameter kritikal (keselamatan, sesi, retensi) hanya boleh diubah oleh Pentadbir Sistem.',
    ],

    'kelulusan' => [
        'already_pending' => 'Sudah ada permohonan perubahan yang menunggu kelulusan bagi konfigurasi ini.',
        'not_pending' => 'Permohonan ini sudah diputuskan.',
        'only_requester_cancels' => 'Hanya pemohon boleh membatalkan permohonan ini.',
        'second_admin_required' => 'Perubahan konfigurasi kritikal mesti diluluskan oleh pentadbir lain, bukan pemohon.',
        'decide_in_workflow' => 'Permohonan ini diproses melalui Workflow; sila putuskan dalam senarai tugasan Workflow.',
        'workflow_title' => 'Perubahan konfigurasi kritikal: :kunci',
        'submitted' => 'Perubahan konfigurasi kritikal dihantar untuk kelulusan pentadbir kedua. Ia belum berkuat kuasa.',
        'approved' => 'Perubahan diluluskan dan kini berkuat kuasa.',
        'rejected' => 'Perubahan ditolak.',
        'cancelled' => 'Permohonan dibatalkan.',
    ],

    'kod' => [
        'attributes' => [
            'jenis_kod' => 'jenis kod',
            'kod' => 'kod',
            'keterangan' => 'keterangan',
            'kod_induk' => 'kod induk',
            'susunan' => 'susunan',
            'tarikh_mula' => 'tarikh mula',
            'tarikh_tamat' => 'tarikh tamat',
            'justifikasi' => 'justifikasi',
        ],
        'parent_missing' => 'Kod induk tidak wujud dalam jenis :jenis.',
        'inactive' => 'tidak aktif',
        'deactivated' => 'Kod dinyahaktifkan. Rekod sejarah yang menggunakannya kekal sah.',
        'imported' => 'Import selesai: :diterima diterima, :ditolak ditolak.',
        'csv_unreadable' => 'Fail CSV tidak dapat dibaca.',
        'types' => [
            'daerah' => 'Daerah',
            'mukim' => 'Mukim',
            'pbt' => 'Pihak Berkuasa Tempatan',
            'kategori_guna_tanah' => 'Kategori guna tanah',
            'jenis_hak_milik' => 'Jenis hak milik',
            'jenis_dokumen' => 'Jenis dokumen',
            'kategori_aduan' => 'Kategori aduan',
            'jenis_urusan' => 'Jenis urusan',
            'status_transaksi' => 'Status transaksi',
            'klasifikasi_data' => 'Klasifikasi data',
        ],
    ],

    'templat' => [
        'unknown_fields' => 'Kandungan menggunakan medan yang tidak diisytiharkan: :medan',
        'restored' => 'Versi :versi dipulihkan sebagai versi baharu.',
    ],

    'tugasan' => [
        'already_running' => 'Dilangkau: tugasan yang sama sedang berjalan.',
        'command_not_allowed' => 'Arahan ":arahan" tidak dibenarkan untuk dijadualkan.',
        'exit_code' => 'Arahan tamat dengan kod :kod.',
        'deactivated_has_history' => 'Tugasan mempunyai sejarah larian, jadi ia dinyahaktifkan (bukan dipadam).',
        'queued' => 'Tugasan dimasukkan ke baris gilir untuk dilaksanakan.',
        'system_not_found' => 'Tugasan sistem tidak dijumpai dalam penjadual.',
        'invalid_cron' => 'Ungkapan cron tidak sah.',
        'stuck' => 'Larian melebihi tempoh tamat masa dan ditandakan gagal.',
    ],

    'kesihatan' => [
        'queue_over' => 'Baris gilir :saiz melebihi ambang :ambang.',
        'horizon_down' => 'Tiada penyelia Horizon berjalan.',
        'storage_over' => 'Penggunaan storan :peratus% melebihi ambang :ambang%.',
    ],

    'integrasi' => [
        'no_change' => 'Tiada perubahan.',
    ],

    'penyelenggaraan' => [
        'default_notice' => 'Sistem sedang dalam penyelenggaraan. Sila cuba sebentar lagi.',
    ],

    'carian' => [
        'reindex_queued' => 'Indeks semula dimasukkan ke baris gilir.',
    ],

    'token' => [
        'scope' => 'Token ini tidak mempunyai skop API pentadbiran.',
        'session_only' => 'Token API hanya boleh dicipta daripada sesi log masuk, bukan dengan token lain.',
        'created' => 'Token dicipta. Salin sekarang — ia tidak akan dipaparkan lagi.',
    ],
];
