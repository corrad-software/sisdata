<?php

return [
    'modul' => 'Notifikasi',

    'keutamaan' => [
        'kritikal' => 'Kritikal',
        'tinggi' => 'Tinggi',
        'biasa' => 'Biasa',
        'maklumat' => 'Maklumat',
    ],

    'saluran' => [
        'dalam_sistem' => 'Dalam sistem',
        'emel' => 'E-mel',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
    ],

    'status' => [
        'beratur' => 'Dalam baris gilir',
        'ditahan' => 'Ditahan untuk ringkasan',
        'dihantar' => 'Dihantar',
        'diterima' => 'Diterima',
        'dibaca' => 'Dibaca',
        'gagal' => 'Gagal (akan dicuba semula)',
        'gagal_muktamad' => 'Gagal muktamad',
    ],

    'kekerapan' => [
        'serta_merta' => 'Serta-merta',
        'ringkasan' => 'Ringkasan harian',
    ],

    'sasaran' => [
        'peranan' => 'Peranan',
        'kebenaran' => 'Kebenaran',
        'bahagian' => 'Bahagian',
        'daerah' => 'Daerah',
        'pengguna' => 'Pengguna',
    ],

    'dikemas_kini' => 'Notifikasi dikemas kini.',
    'jenis_dikemas_kini' => 'Jenis notifikasi dikemas kini.',
    'templat_disimpan' => 'Templat versi :versi disimpan dan diaktifkan.',
    'templat_diaktifkan' => 'Templat versi :versi diaktifkan.',
    'medan_tidak_dikenali' => 'Medan {{:medan}} tidak diisytiharkan bagi jenis notifikasi ini.',
    'medan_sensitif_templat' => 'Medan {{:medan}} ditanda Sulit/Rahsia dan tidak boleh digunakan dalam templat.',
    'wajib_dikunci' => 'Jenis notifikasi ":nama" adalah wajib dan tidak boleh dimatikan.',
    'ringkasan_tidak_dibenarkan' => 'Ringkasan berkala hanya untuk notifikasi berkeutamaan Biasa dan Maklumat.',
    'saluran_kosong' => 'Pilih sekurang-kurangnya satu saluran, atau matikan jenis notifikasi ini.',
    'keutamaan_disimpan' => 'Tetapan notifikasi disimpan.',
    'peraturan_ditambah' => 'Peraturan penerima ditambah.',
    'peraturan_dipadam' => 'Peraturan penerima dipadam.',
    'tiada_penerima' => 'Tiada penerima aktif ditemui bagi kumpulan yang dipilih.',
    'pengumuman_dihantar' => 'Pengumuman dihantar kepada :count penerima.',
    'cuba_semula' => 'Penghantaran dimasukkan semula ke baris gilir.',
    'tidak_boleh_cuba_semula' => 'Hanya penghantaran yang gagal boleh dicuba semula.',
];
