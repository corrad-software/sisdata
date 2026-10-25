<?php

return [
    'pelapor' => 'Pelapor',
    'sistem' => 'Sistem',

    'ralat' => [
        'hanya_tambah' => 'Rekod kronologi dan eskalasi tiket tidak boleh diubah atau dipadam.',
        'pasukan_tidak_sah' => 'Pasukan sokongan tidak sah atau tidak aktif.',
        'bukan_ahli_pasukan' => 'Juruteknik dipilih bukan ahli aktif pasukan tersebut.',
        'peralihan' => 'Status tidak boleh ditukar daripada ":dari" kepada ":ke".',
        'perlu_kelulusan' => 'Permintaan perubahan perlu diluluskan melalui aliran kelulusan sebelum tindakan dimulakan.',
        'perlu_penyelesaian' => 'Punca akar dan penyelesaian wajib diisi sebelum tiket ditandakan selesai.',
        'tiket_ditutup' => 'Tiket ini telah ditutup.',
        'belum_selesai' => 'Tiket belum ditandakan selesai.',
        'perlu_pengesahan' => 'Tiket hanya boleh ditutup selepas pelapor mengesahkan penyelesaian (atau ditutup automatik).',
        'tidak_boleh_buka_semula' => 'Hanya tiket yang telah selesai boleh dibuka semula.',
        'tempoh_buka_semula' => 'Tempoh buka semula (:hari hari selepas selesai) telah tamat. Sila buka tiket baharu.',
        'induk_tidak_sah' => 'Tiket induk tidak sah.',
        'bukan_permintaan_perubahan' => 'Kelulusan hanya bagi tiket permintaan perubahan yang sedang disiasat atau dalam tindakan.',
        'tiada_aliran' => 'Aliran kelulusan permintaan perubahan belum dikonfigurasi dalam Enjin Aliran Kerja.',
        'kepuasan_sudah' => 'Penilaian kepuasan telah diberikan bagi tiket ini.',
        'perlu_persetujuan' => 'Tangkapan skrin hanya boleh dimuat naik selepas anda bersetuju secara jelas.',
        'aduan_tiada' => 'Nombor aduan tidak dikenali.',
        'lampiran_tiada_akses' => 'Anda tidak mempunyai capaian kepada lampiran ini.',
        'ip_tidak_dibenarkan' => 'Alamat IP tidak dibenarkan memanggil API ini.',
    ],

    'sla_status' => [
        'dalam_tempoh' => 'Dalam tempoh',
        'hampir_tamat' => 'Hampir tamat',
        'dilepasi' => 'Dilepasi',
        'dipenuhi' => 'Dipenuhi',
        'tidak_dipenuhi' => 'Tidak dipenuhi',
        'dijeda' => 'Dijeda (menunggu pelapor)',
    ],

    'sasaran' => [
        'respons' => 'respons',
        'selesai' => 'penyelesaian',
        'manual' => 'manual',
    ],

    'sebab_eskalasi' => [
        'hampir_sla' => 'Hampir ambang SLA',
        'lepas_sla' => 'SLA dilepasi',
        'manual' => 'Eskalasi manual',
    ],

    'eskalasi' => [
        'tiada_penerima' => 'Tiada penerima dikonfigurasi bagi peringkat ini.',
    ],

    'cadangan' => [
        'tiada_jawapan' => 'Pembantu pengetahuan tidak dapat menjawab buat masa ini.',
    ],

    'kronologi' => [
        'pelapor_membalas' => 'Pelapor membalas — tiket diteruskan.',
        'pendua' => 'Ditandakan pendua kepada :no.',
        'ada_pendua' => 'Tiket :no ditandakan pendua kepada tiket ini.',
        'dirujuk_aduan' => 'Dirujuk ke Modul Aduan dan Isu Tanah sebagai aduan tanah.',
        'tutup_automatik' => 'Ditutup automatik — tiada maklum balas pelapor dalam :hari hari selepas selesai.',
        'tutup_selepas_disahkan' => 'Ditutup automatik selepas pengesahan pelapor.',
        'draf_kb' => 'Draf artikel penyelesaian dihantar ke Pangkalan Pengetahuan untuk semakan.',
        'buka_semula_pusat' => 'Dibuka semula daripada Sistem Aduan berpusat.',
    ],

    'kelulusan' => [
        'lulus' => 'diluluskan',
        'tolak' => 'ditolak',
        'ditarik_balik' => 'ditarik balik',
        'dibatalkan' => 'dibatalkan',
    ],

    'kb' => [
        'tajuk' => 'Penyelesaian: :tajuk',
    ],

    'integrasi' => [
        'had_kadar' => 'Had kadar integrasi Sistem Aduan berpusat dicapai; akan dicuba semula.',
    ],

    'laporan' => [
        'bulanan_tajuk' => 'Laporan Sokongan Bulanan SISDATA — :bulan',
        'dijana' => 'Dijana secara automatik daripada data tiket Modul T1 pada :masa',
    ],

    'serahan' => [
        'tajuk' => 'Pakej Serahan Sokongan SISDATA (SRS-TKT-21)',
    ],

    'konfigurasi' => [
        'nota_sla' => 'Nilai SLA lalai ialah ANDAIAN kerana SRS §18.2 tidak menyatakan angka (mengikut kontrak). Gantikan dengan sasaran kontrak sebelum ujian penerimaan.',
    ],

    'kpi' => [
        'tiket_terbuka' => 'Tiket sokongan terbuka',
        'pematuhan_sla' => 'Pematuhan SLA penyelesaian tiket',
        'mttr' => 'Masa purata penyelesaian tiket',
        'penyelesaian_kendiri' => 'Kadar penyelesaian kendiri (Pangkalan Pengetahuan)',
    ],

    'mesej' => [
        'selesai_sendiri' => 'Terima kasih — direkodkan sebagai diselesaikan melalui Pangkalan Pengetahuan.',
        'dibuka' => 'Tiket :no telah dibuka. Anda akan dimaklumkan tentang perkembangannya.',
        'balasan' => 'Balasan dihantar.',
        'disahkan' => 'Penyelesaian disahkan. Terima kasih.',
        'kepuasan' => 'Terima kasih atas penilaian anda.',
        'dibuka_semula' => 'Tiket dibuka semula.',
        'lampiran' => 'Lampiran dimuat naik.',
        'status' => 'Status tiket dikemas kini.',
        'catatan' => 'Catatan direkodkan.',
        'ditugaskan' => 'Tiket ditugaskan semula.',
        'diserah' => 'Tiket diserahkan ke aras sokongan baharu.',
        'dikemas_kini' => 'Dikemas kini.',
        'dieskalasi' => 'Tiket dieskalasikan.',
        'pendua' => 'Tiket ditandakan sebagai pendua.',
        'dirujuk_aduan' => 'Tiket dirujuk ke Modul Aduan.',
        'kelulusan_dimohon' => 'Permintaan perubahan dihantar untuk kelulusan.',
        'laporan_dijana' => 'Laporan sokongan :bulan dijana.',
        'aktiviti' => 'Aktiviti sokongan direkodkan.',
        'sla_disimpan' => 'Versi baharu konfigurasi SLA disimpan.',
        'ulang_cuba' => 'Cuba semula: :berjaya berjaya, :gagal gagal.',
        'selaras' => ':n tiket dijadualkan untuk penyegerakan semula.',
        'dipindah' => ':tiket tiket dan :peraturan peraturan penugasan dipindahkan.',
    ],
];
