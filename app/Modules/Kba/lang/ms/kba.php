<?php

return [
    'ai' => [
        'url_tidak_sah' => 'URL perkhidmatan AI tempatan tidak sah.',
        'bukan_tempatan' => 'Perkhidmatan AI mesti berada dalam rangkaian persendirian MAIS (SRS-KBA-15).',
    ],
    'jawapan' => [
        'tiada_sumber' => 'Tiada sumber yang mencukupi ditemui dalam Pangkalan Pengetahuan untuk menjawab soalan ini. Jawapan tidak dijana kerana ia tidak dapat disokong oleh mana-mana sumber yang diindeks. Cuba nyatakan soalan dengan lebih khusus, gunakan carian kata kunci, atau buka tiket sokongan.',
        'ekstraktif_pengenalan' => 'Berikut petikan sumber yang paling berkaitan dengan soalan anda (mod ekstraktif — dipetik terus daripada sumber, tiada penjanaan AI):',
        'had_kadar' => 'Had penggunaan AI prompt telah dicapai. Sila cuba semula dalam :saat saat.',
    ],
    'audit' => [
        'ditolak_had' => 'Prompt ditolak: had kadar penggunaan AI dicapai',
    ],
    'indeks' => [
        'tiada_teks' => 'Tiada teks boleh diindeks dalam sumber ini.',
        'tanpa_embedding' => 'Model embedding tempatan tidak tersedia — segmen diindeks untuk carian kata kunci sahaja. Indeks semula apabila model tersedia.',
        'dms_tiada' => 'Dokumen DMS telah dilupuskan atau tidak lagi wujud.',
        'dms_tiada_teks' => 'DMS tidak menemui teks dalam dokumen ini (cth. imbasan tanpa OCR atau fail spatial).',
    ],
    'sumber' => [
        'format_tidak_disokong' => 'Format fail tidak disokong. Gunakan Markdown, teks, PDF atau DOCX.',
        'gagal_baca' => 'Fail tidak dapat dibaca: :ralat',
        'didaftar' => 'Sumber didaftarkan dan dijadualkan untuk pengindeksan.',
        'dikemas_kini' => 'Metadata sumber dikemas kini; klasifikasi dan peranan dikenakan serta-merta kepada segmen.',
        'dinyahaktif' => 'Sumber dinyahaktifkan oleh pentadbir kandungan.',
        'indeks_dijadual' => 'Pengindeksan semula dijadualkan.',
        'indeks_semua_dijadual' => ':bil sumber dijadualkan untuk pengindeksan semula (inkremental).',
        'dms_tiada' => 'Dokumen DMS tidak ditemui.',
        'tiada_capaian' => 'Sumber tidak ditemui atau anda tiada kebenaran untuk membukanya.',
        'urus_melalui_artikel' => 'Sumber artikel diurus melalui artikel itu sendiri.',
    ],
    'artikel' => [
        'versi_awal' => 'Versi awal',
        'dicipta' => 'Draf artikel dicipta.',
        'dikemas_kini' => 'Artikel dikemas kini.',
        'dihantar' => 'Artikel dihantar untuk semakan.',
        'dilapukkan' => 'Artikel ditanda lapuk dan dikeluarkan daripada carian.',
        'lapuk' => 'Artikel ditanda lapuk.',
        'sedang_disemak' => 'Artikel sedang disemak dan tidak boleh disunting.',
        'bukan_draf' => 'Hanya draf boleh dihantar untuk semakan.',
        'aliran_tiada' => 'Aliran kerja "kba.terbit_artikel" belum dikonfigurasi dalam Workflow. Hubungi pentadbir sistem.',
        'tajuk_aliran' => 'Terbitkan artikel: :tajuk',
        'dari_tiket' => 'Draf penyelesaian daripada tiket :tiket',
        'sebab_keluaran' => 'Merujuk versi sistem :lama; keluaran :baharu telah dilancarkan.',
    ],
    'keluaran' => [
        'direkod' => 'Keluaran direkod. :bil artikel ditanda perlu semakan.',
    ],
    'perbualan' => [
        'disembunyikan' => 'Perbualan dibuang daripada sejarah anda. Log audit tetap disimpan.',
    ],
    'maklum_balas' => [
        'terima_kasih' => 'Terima kasih atas maklum balas anda.',
        'dikemas_kini' => 'Status tindakan maklum balas dikemas kini.',
    ],
    'penilaian' => [
        'membantu' => 'Membantu',
        'tidak_membantu' => 'Tidak membantu',
        'sumber_salah' => 'Sumber salah',
    ],
];
