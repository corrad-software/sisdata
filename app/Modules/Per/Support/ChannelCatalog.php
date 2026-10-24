<?php

namespace App\Modules\Per\Support;

/**
 * The eleven acquisition channels of Lampiran H A-6.01–A-6.11 (SRS-PER-01):
 * visual identity, owning bahagian, target days, default stages
 * (SRS-PER-17), mandatory document checklist (SRS-PER-16) and the
 * channel-specific field schema (SRS-PER-02..15).
 *
 * Channels, stages and checklists are seeded into `saluran_pemerolehan`,
 * `peringkat_saluran` and `senarai_semak_dokumen_saluran` from here and are
 * admin-configurable afterwards. Field schemas stay in code: they drive both
 * validation of `pemerolehan.butiran_saluran` and the generated frontend form
 * (plan slice PER-c: forms generated from field configuration).
 *
 * Field definition keys:
 *   label     Malay label
 *   jenis     teks | teks_panjang | tarikh | nombor | wang | pilihan
 *   pilihan   value => label (jenis = pilihan)
 *   peringkat stage code the field must be filled by (required to LEAVE it); null = optional
 *   peribadi  personal data (names of deceased/heirs/waqif/seller…) — stored
 *             encrypted in butiran_peribadi, masked without per.sulit.lihat
 *
 * Stage `jenis`: biasa | kelulusan (WFE approval) | pendaftaran | simpanan (last; case closes here).
 * Checklist `peringkat`: the stage that cannot be ENTERED until the document
 * is in, or "tutup" (needed to close). `sistem` = produced by the closing step itself
 * (vault register record), never blocks.
 */
final class ChannelCatalog
{
    public const BAHAGIAN = [
        'hartanah' => 'Bahagian Pengurusan Hartanah',
        'wakaf' => 'Bahagian Wakaf',
        'baitulmal' => 'Bahagian Baitulmal',
    ];

    public const TUTUP = 'tutup';

    /** @return array<string, array<string, mixed>> */
    public static function channels(): array
    {
        $kelulusan = ['kelulusan', 'Kelulusan berlapis', 'kelulusan', 14];
        $daftar = ['pendaftaran_hak_milik', 'Pendaftaran hak milik', 'pendaftaran', 30];
        $simpan = ['simpanan_dokumen', 'Simpanan dokumen (peti besi)', 'simpanan', 7];

        return [
            'pemberimilikan_5a' => [
                'nama' => 'Pemberimilikan Tanah (Seksyen 5A)',
                'singkatan' => '5A',
                'lampiran_h' => 'A-6.01',
                'ikon' => 'dokumen_tanah',
                'warna' => '#2563EB',
                'bahagian' => 'hartanah',
                'tempoh_sasaran_hari' => 365,
                'keterangan' => 'Permohonan e-Tanah, keputusan MMKN, notis 5A dan bayaran hingga penerimaan hak milik.',
                'peringkat' => [
                    ['permohonan_etanah', 'Permohonan e-Tanah', 'biasa', 30],
                    ['keputusan_mmkn', 'Keputusan MMKN', 'biasa', 120],
                    ['notis_5a', 'Notis 5A', 'biasa', 14],
                    ['kelulusan', 'Kelulusan bayaran notis 5A', 'kelulusan', 14],
                    ['bayaran_notis_5a', 'Bayaran notis 5A', 'biasa', 60],
                    ['penerimaan_hak_milik', 'Penerimaan hak milik', 'biasa', 90],
                    ['pendaftaran_hak_milik', 'Pendaftaran dalam SISDATA', 'pendaftaran', 14],
                    $simpan,
                ],
                'dokumen' => [
                    ['salinan_permohonan_etanah', 'Salinan permohonan e-Tanah', 'surat_rasmi', 'kelulusan'],
                    ['kertas_mmkn', 'Keputusan / kertas MMKN', 'surat_rasmi', 'kelulusan'],
                    ['notis_5a_asal', 'Notis 5A asal', 'surat_rasmi', 'kelulusan'],
                    ['resit_bayaran_5a', 'Resit bayaran premium dan notis 5A', 'resit', 'penerimaan_hak_milik'],
                    ['pelan_akui', 'Pelan pra-perhitungan atau pelan akui', 'pelan', 'pendaftaran_hak_milik'],
                    ['hak_milik_diterima', 'Hak milik yang diterima', 'geran', 'pendaftaran_hak_milik'],
                    ['memo_penerimaan', 'Memo penerimaan', 'surat_rasmi', 'pendaftaran_hak_milik'],
                    ['rekod_peti_besi', 'Rekod daftar peti besi', 'lain', self::TUTUP, true],
                ],
                'medan' => [
                    'no_permohonan_etanah' => ['label' => 'No. permohonan e-Tanah', 'jenis' => 'teks', 'peringkat' => 'permohonan_etanah'],
                    'tarikh_permohonan' => ['label' => 'Tarikh permohonan', 'jenis' => 'tarikh', 'peringkat' => 'permohonan_etanah'],
                    'pejabat_tanah_daerah' => ['label' => 'Pejabat Tanah Daerah', 'jenis' => 'teks', 'peringkat' => 'permohonan_etanah'],
                    'no_mesyuarat_mmkn' => ['label' => 'No. mesyuarat MMKN', 'jenis' => 'teks', 'peringkat' => 'keputusan_mmkn'],
                    'tarikh_mmkn' => ['label' => 'Tarikh mesyuarat MMKN', 'jenis' => 'tarikh', 'peringkat' => 'keputusan_mmkn'],
                    'no_kertas_mmkn' => ['label' => 'No. kertas MMKN', 'jenis' => 'teks', 'peringkat' => 'keputusan_mmkn'],
                    'jenis_keputusan_mmkn' => ['label' => 'Jenis keputusan MMKN', 'jenis' => 'pilihan', 'peringkat' => 'keputusan_mmkn', 'pilihan' => [
                        'lulus' => 'Lulus', 'lulus_bersyarat' => 'Lulus bersyarat', 'tangguh' => 'Tangguh', 'tolak' => 'Tolak',
                    ]],
                    'syarat_mmkn' => ['label' => 'Syarat dikenakan MMKN', 'jenis' => 'teks_panjang'],
                    'keluasan_diluluskan' => ['label' => 'Keluasan diluluskan (m²)', 'jenis' => 'nombor', 'peringkat' => 'keputusan_mmkn'],
                    'no_notis_5a' => ['label' => 'No. notis 5A', 'jenis' => 'teks', 'peringkat' => 'notis_5a'],
                    'tarikh_notis_5a' => ['label' => 'Tarikh notis 5A', 'jenis' => 'tarikh', 'peringkat' => 'notis_5a'],
                    'tarikh_terima_notis' => ['label' => 'Tarikh notis diterima', 'jenis' => 'tarikh', 'peringkat' => 'notis_5a'],
                    'jumlah_premium' => ['label' => 'Jumlah premium (RM)', 'jenis' => 'wang', 'peringkat' => 'notis_5a'],
                    'cukai_tahun_pertama' => ['label' => 'Cukai tanah tahun pertama (RM)', 'jenis' => 'wang', 'peringkat' => 'notis_5a'],
                    'bayaran_ukur' => ['label' => 'Bayaran ukur (RM)', 'jenis' => 'wang', 'peringkat' => 'notis_5a'],
                    'bayaran_lain' => ['label' => 'Bayaran lain (RM)', 'jenis' => 'wang'],
                    'tarikh_akhir_bayaran' => ['label' => 'Tarikh akhir bayaran', 'jenis' => 'tarikh', 'peringkat' => 'notis_5a'],
                    'tarikh_terima_hak_milik' => ['label' => 'Tarikh hak milik diterima', 'jenis' => 'tarikh', 'peringkat' => 'penerimaan_hak_milik'],
                ],
            ],

            'serah_milik' => [
                'nama' => 'Serah Milik',
                'singkatan' => 'SM',
                'lampiran_h' => 'A-6.02',
                'ikon' => 'serah',
                'warna' => '#0891B2',
                'bahagian' => 'hartanah',
                'tempoh_sasaran_hari' => 120,
                'keterangan' => 'Penerimaan memo serah milik, pendaftaran hak milik dan simpanan dokumen.',
                'peringkat' => [
                    ['penerimaan_memo', 'Penerimaan memo serah milik', 'biasa', 7],
                    ['semakan', 'Semakan dokumen', 'biasa', 14],
                    $kelulusan,
                    ['pendaftaran_hak_milik', 'Pendaftaran hak milik', 'pendaftaran', 60],
                    $simpan,
                ],
                'dokumen' => [
                    ['memo_serah_milik', 'Memo serah milik', 'surat_rasmi', 'kelulusan'],
                    ['borang_serah_balik', 'Borang serah balik tanah', 'lain', 'kelulusan'],
                    ['hak_milik_asal', 'Hak milik asal', 'geran', 'kelulusan'],
                    ['surat_kelulusan_pejabat_tanah', 'Surat kelulusan Pejabat Tanah', 'surat_rasmi', 'kelulusan'],
                    ['hak_milik_baharu', 'Hak milik baharu', 'geran', 'simpanan_dokumen'],
                    ['rekod_peti_besi', 'Rekod daftar peti besi', 'lain', self::TUTUP, true],
                ],
                'medan' => [
                    'no_memo_serah_milik' => ['label' => 'No. memo serah milik', 'jenis' => 'teks', 'peringkat' => 'penerimaan_memo'],
                    'tarikh_memo' => ['label' => 'Tarikh memo', 'jenis' => 'tarikh', 'peringkat' => 'penerimaan_memo'],
                    'pihak_penyerah' => ['label' => 'Pihak penyerah', 'jenis' => 'teks', 'peringkat' => 'penerimaan_memo', 'peribadi' => true],
                    'sebab_serah_milik' => ['label' => 'Sebab serah milik', 'jenis' => 'teks_panjang', 'peringkat' => 'semakan'],
                    'no_hak_milik_asal' => ['label' => 'No. hak milik asal', 'jenis' => 'teks', 'peringkat' => 'semakan'],
                    'no_hak_milik_baharu' => ['label' => 'No. hak milik baharu', 'jenis' => 'teks', 'peringkat' => 'pendaftaran_hak_milik'],
                    'tarikh_pendaftaran' => ['label' => 'Tarikh pendaftaran', 'jenis' => 'tarikh', 'peringkat' => 'pendaftaran_hak_milik'],
                ],
            ],

            'faraid' => [
                'nama' => 'Faraid',
                'singkatan' => 'FRD',
                'lampiran_h' => 'A-6.09',
                'ikon' => 'timbangan',
                'warna' => '#7C3AED',
                'bahagian' => 'baitulmal',
                'tempoh_sasaran_hari' => 180,
                'keterangan' => 'Rekod pembahagian pusaka, pengesahan waris dan integrasi e-Faraid MAIS.',
                'peringkat' => [
                    ['dokumen_diterima', 'Dokumen diterima', 'biasa', 14],
                    ['pengesahan_waris', 'Pengesahan waris (e-Faraid)', 'biasa', 30],
                    ['semakan', 'Semakan perundangan', 'biasa', 21],
                    $kelulusan, $daftar, $simpan,
                ],
                'dokumen' => [
                    ['sijil_faraid', 'Sijil faraid atau keputusan pembahagian pusaka', 'surat_rasmi', 'kelulusan'],
                    ['sijil_kematian', 'Sijil kematian', 'surat_rasmi', 'kelulusan'],
                    ['senarai_waris_disahkan', 'Senarai waris disahkan', 'surat_rasmi', 'kelulusan'],
                    ['surat_pelepasan_hak', 'Surat pelepasan hak waris', 'surat_rasmi', 'kelulusan'],
                    ['carian_rasmi', 'Carian rasmi', 'surat_rasmi', 'kelulusan'],
                    ['borang_pindah_milik', 'Borang pindah milik', 'lain', 'pendaftaran_hak_milik'],
                ],
                'medan' => [
                    'no_kes_faraid' => ['label' => 'No. kes faraid', 'jenis' => 'teks', 'peringkat' => 'dokumen_diterima'],
                    'rujukan_mahkamah_syariah' => ['label' => 'Rujukan Mahkamah Syariah', 'jenis' => 'teks'],
                    'tarikh_keputusan' => ['label' => 'Tarikh keputusan', 'jenis' => 'tarikh', 'peringkat' => 'dokumen_diterima'],
                    'nama_si_mati' => ['label' => 'Nama si mati', 'jenis' => 'teks', 'peringkat' => 'dokumen_diterima', 'peribadi' => true],
                    'tarikh_kematian' => ['label' => 'Tarikh kematian', 'jenis' => 'tarikh', 'peringkat' => 'dokumen_diterima'],
                    'pecahan_bahagian' => ['label' => 'Pecahan bahagian waris', 'jenis' => 'teks_panjang', 'peringkat' => 'pengesahan_waris'],
                    'bahagian_mais' => ['label' => 'Bahagian MAIS', 'jenis' => 'teks', 'peringkat' => 'pengesahan_waris'],
                    'status_pengesahan_waris' => ['label' => 'Status pengesahan waris', 'jenis' => 'pilihan', 'peringkat' => 'pengesahan_waris', 'mesti' => 'disahkan', 'pilihan' => [
                        'belum_disahkan' => 'Belum disahkan', 'disahkan' => 'Disahkan', 'ditolak' => 'Ditolak',
                    ]],
                    'rujukan_e_faraid' => ['label' => 'Rujukan e-Faraid', 'jenis' => 'teks'],
                ],
            ],

            'daftar_kematian' => [
                'nama' => 'Daftar Kematian',
                'singkatan' => 'DK',
                'lampiran_h' => 'A-6.03',
                'ikon' => 'daftar',
                'warna' => '#475569',
                'bahagian' => 'baitulmal',
                'tempoh_sasaran_hari' => 180,
                'keterangan' => 'Penerimaan maklumat pewaris, pengesahan status harta dan pendaftaran rekod.',
                'peringkat' => [
                    ['penerimaan_maklumat', 'Penerimaan maklumat pewaris', 'biasa', 14],
                    ['pengesahan_harta', 'Pengesahan status harta', 'biasa', 30],
                    $kelulusan, $daftar, $simpan,
                ],
                'dokumen' => [
                    ['sijil_kematian', 'Sijil kematian', 'surat_rasmi', 'kelulusan'],
                    ['rekod_daftar_kematian', 'Rekod Daftar Kematian', 'surat_rasmi', 'kelulusan'],
                    ['surat_pengesahan_waris', 'Surat pengesahan tiada waris atau pelepasan hak', 'surat_rasmi', 'kelulusan'],
                    ['carian_rasmi', 'Carian rasmi', 'surat_rasmi', 'kelulusan'],
                    ['laporan_pengesahan_harta', 'Laporan pengesahan status harta', 'laporan', 'kelulusan'],
                    ['borang_pindah_milik', 'Borang pindah milik', 'lain', 'pendaftaran_hak_milik'],
                ],
                'medan' => [
                    'no_rujukan_daftar_kematian' => ['label' => 'No. rujukan Daftar Kematian', 'jenis' => 'teks', 'peringkat' => 'penerimaan_maklumat'],
                    'nama_si_mati' => ['label' => 'Nama si mati', 'jenis' => 'teks', 'peringkat' => 'penerimaan_maklumat', 'peribadi' => true],
                    'tarikh_kematian' => ['label' => 'Tarikh kematian', 'jenis' => 'tarikh', 'peringkat' => 'penerimaan_maklumat'],
                    'daerah_kematian' => ['label' => 'Daerah kematian', 'jenis' => 'teks'],
                    'status_waris' => ['label' => 'Status waris', 'jenis' => 'pilihan', 'peringkat' => 'penerimaan_maklumat', 'pilihan' => [
                        'tiada_waris' => 'Tiada waris', 'waris_melepaskan' => 'Waris melepaskan hak', 'ada_waris' => 'Ada waris (sebahagian)',
                    ]],
                    'nama_pewaris_melepaskan' => ['label' => 'Nama pewaris yang melepaskan hak', 'jenis' => 'teks', 'peribadi' => true],
                    'tarikh_pelepasan_hak' => ['label' => 'Tarikh pelepasan hak', 'jenis' => 'tarikh'],
                    'status_pengesahan_harta' => ['label' => 'Status pengesahan harta', 'jenis' => 'pilihan', 'peringkat' => 'pengesahan_harta', 'mesti' => 'disahkan', 'pilihan' => [
                        'belum_disahkan' => 'Belum disahkan', 'disahkan' => 'Disahkan', 'bukan_harta_mais' => 'Bukan harta MAIS',
                    ]],
                    'pegawai_pengesah' => ['label' => 'Pegawai pengesah', 'jenis' => 'teks', 'peringkat' => 'pengesahan_harta'],
                ],
            ],

            'perintah_arb' => [
                'nama' => 'Perintah ARB',
                'singkatan' => 'ARB',
                'lampiran_h' => 'A-6.04',
                'ikon' => 'cop',
                'warna' => '#B45309',
                'bahagian' => 'baitulmal',
                'tempoh_sasaran_hari' => 120,
                'keterangan' => 'Penerimaan perintah Amanah Raya Berhad dan pengemaskinian status hak milik.',
                'peringkat' => [
                    ['penerimaan_perintah', 'Penerimaan perintah ARB', 'biasa', 7],
                    ['semakan_perundangan', 'Semakan perundangan', 'biasa', 21],
                    $kelulusan,
                    ['pendaftaran_hak_milik', 'Pengemaskinian status hak milik', 'pendaftaran', 45],
                    $simpan,
                ],
                'dokumen' => [
                    ['perintah_arb', 'Perintah ARB asal atau salinan diakui sah', 'surat_rasmi', 'kelulusan'],
                    ['surat_iringan_arb', 'Surat iringan ARB', 'surat_rasmi', 'kelulusan'],
                    ['sijil_kematian', 'Sijil kematian', 'surat_rasmi', 'kelulusan'],
                    ['carian_rasmi', 'Carian rasmi', 'surat_rasmi', 'kelulusan'],
                    ['borang_pindah_milik', 'Borang pindah milik', 'lain', 'pendaftaran_hak_milik'],
                    ['hak_milik_dikemas_kini', 'Hak milik dikemas kini', 'geran', 'simpanan_dokumen'],
                ],
                'medan' => [
                    'no_perintah_arb' => ['label' => 'No. perintah ARB', 'jenis' => 'teks', 'peringkat' => 'penerimaan_perintah'],
                    'tarikh_perintah' => ['label' => 'Tarikh perintah', 'jenis' => 'tarikh', 'peringkat' => 'penerimaan_perintah'],
                    'pejabat_arb' => ['label' => 'Pejabat ARB', 'jenis' => 'teks', 'peringkat' => 'penerimaan_perintah'],
                    'nama_si_mati' => ['label' => 'Nama si mati', 'jenis' => 'teks', 'peringkat' => 'penerimaan_perintah', 'peribadi' => true],
                    'no_fail_arb' => ['label' => 'No. fail ARB', 'jenis' => 'teks'],
                    'jenis_perintah' => ['label' => 'Jenis perintah', 'jenis' => 'pilihan', 'peringkat' => 'penerimaan_perintah', 'pilihan' => [
                        'perintah_pembahagian' => 'Perintah pembahagian', 'surat_kuasa_tadbir' => 'Surat kuasa tadbir', 'perintah_lain' => 'Perintah lain',
                    ]],
                    'tarikh_terima' => ['label' => 'Tarikh perintah diterima', 'jenis' => 'tarikh', 'peringkat' => 'penerimaan_perintah'],
                    'status_hak_milik_selepas_perintah' => ['label' => 'Status hak milik selepas perintah', 'jenis' => 'teks', 'peringkat' => 'pendaftaran_hak_milik'],
                ],
            ],

            'serahan_hakmilik' => [
                'nama' => 'Serahan Hakmilik',
                'singkatan' => 'SH',
                'lampiran_h' => 'A-6.05',
                'ikon' => 'kunci',
                'warna' => '#0D9488',
                'bahagian' => 'hartanah',
                'tempoh_sasaran_hari' => 90,
                'keterangan' => 'Rekod penyerah hak, dokumen pindah milik, tarikh serahan dan pengesahan pendaftaran PTG.',
                'peringkat' => [
                    ['dokumen_diterima', 'Dokumen serahan diterima', 'biasa', 7],
                    ['semakan', 'Semakan dokumen', 'biasa', 14],
                    $kelulusan,
                    ['perserahan_ptg', 'Perserahan di PTG', 'biasa', 30],
                    $daftar, $simpan,
                ],
                'dokumen' => [
                    ['surat_serahan', 'Surat serahan hak milik', 'surat_rasmi', 'kelulusan'],
                    ['borang_14a', 'Borang pindah milik (14A)', 'lain', 'kelulusan'],
                    ['hak_milik_asal', 'Hak milik asal', 'geran', 'kelulusan'],
                    ['carian_rasmi', 'Carian rasmi', 'surat_rasmi', 'kelulusan'],
                    ['resit_perserahan_ptg', 'Resit perserahan PTG', 'resit', 'pendaftaran_hak_milik'],
                    ['pengesahan_pendaftaran_ptg', 'Pengesahan pendaftaran PTG', 'surat_rasmi', 'simpanan_dokumen'],
                ],
                'medan' => [
                    'nama_penyerah_hak' => ['label' => 'Nama penyerah hak', 'jenis' => 'teks', 'peringkat' => 'dokumen_diterima', 'peribadi' => true],
                    'tarikh_serahan' => ['label' => 'Tarikh serahan', 'jenis' => 'tarikh', 'peringkat' => 'dokumen_diterima'],
                    'sebab_serahan' => ['label' => 'Sebab serahan', 'jenis' => 'teks_panjang'],
                    'no_borang_pindah_milik' => ['label' => 'No. borang pindah milik', 'jenis' => 'teks', 'peringkat' => 'semakan'],
                    'tarikh_perserahan_ptg' => ['label' => 'Tarikh perserahan PTG', 'jenis' => 'tarikh', 'peringkat' => 'perserahan_ptg'],
                    'no_perserahan_ptg' => ['label' => 'No. perserahan PTG', 'jenis' => 'teks', 'peringkat' => 'perserahan_ptg'],
                    'status_pendaftaran_ptg' => ['label' => 'Status pendaftaran PTG', 'jenis' => 'pilihan', 'peringkat' => 'pendaftaran_hak_milik', 'mesti' => 'didaftar', 'pilihan' => self::STATUS_PTG],
                ],
            ],

            'perintah_mahkamah' => [
                'nama' => 'Perintah Mahkamah (LA/Probet)',
                'singkatan' => 'PM',
                'lampiran_h' => 'A-6.06',
                'ikon' => 'mahkamah',
                'warna' => '#BE123C',
                'bahagian' => 'baitulmal',
                'tempoh_sasaran_hari' => 180,
                'keterangan' => 'Penerimaan Letter of Administration / Probet, pendaftaran waris dan pengemaskinian hak milik.',
                'peringkat' => [
                    ['penerimaan_perintah', 'Penerimaan perintah mahkamah', 'biasa', 7],
                    ['pendaftaran_waris', 'Pendaftaran waris', 'biasa', 21],
                    ['semakan_perundangan', 'Semakan perundangan', 'biasa', 21],
                    $kelulusan, $daftar, $simpan,
                ],
                'dokumen' => [
                    ['perintah_mahkamah', 'Perintah mahkamah diakui sah', 'surat_rasmi', 'kelulusan'],
                    ['sijil_kematian', 'Sijil kematian', 'surat_rasmi', 'kelulusan'],
                    ['senarai_harta_pusaka', 'Senarai harta pusaka', 'surat_rasmi', 'kelulusan'],
                    ['dokumen_pengesahan_waris', 'Dokumen pengesahan waris', 'surat_rasmi', 'kelulusan'],
                    ['carian_rasmi', 'Carian rasmi', 'surat_rasmi', 'kelulusan'],
                    ['borang_pindah_milik', 'Borang pindah milik', 'lain', 'pendaftaran_hak_milik'],
                ],
                'medan' => [
                    'no_kes_mahkamah' => ['label' => 'No. kes mahkamah', 'jenis' => 'teks', 'peringkat' => 'penerimaan_perintah'],
                    'jenis_perintah' => ['label' => 'Jenis perintah', 'jenis' => 'pilihan', 'peringkat' => 'penerimaan_perintah', 'pilihan' => [
                        'letter_of_administration' => 'Letter of Administration (LA)', 'probet' => 'Probet',
                    ]],
                    'nama_mahkamah' => ['label' => 'Nama mahkamah', 'jenis' => 'teks', 'peringkat' => 'penerimaan_perintah'],
                    'tarikh_perintah' => ['label' => 'Tarikh perintah', 'jenis' => 'tarikh', 'peringkat' => 'penerimaan_perintah'],
                    'tarikh_terima' => ['label' => 'Tarikh perintah diterima', 'jenis' => 'tarikh', 'peringkat' => 'penerimaan_perintah'],
                    'nama_pentadbir_harta' => ['label' => 'Nama pentadbir harta', 'jenis' => 'teks', 'peringkat' => 'pendaftaran_waris', 'peribadi' => true],
                    'bahagian_mais' => ['label' => 'Bahagian MAIS', 'jenis' => 'teks', 'peringkat' => 'pendaftaran_waris'],
                ],
            ],

            'pusaka_jkptgs' => [
                'nama' => 'Pusaka JKPTGS',
                'singkatan' => 'JKPTG',
                'lampiran_h' => 'A-6.07',
                'ikon' => 'pusaka',
                'warna' => '#C2410C',
                'bahagian' => 'baitulmal',
                'tempoh_sasaran_hari' => 150,
                'keterangan' => 'Penerimaan sijil pusaka kecil JKPTGS, pendaftaran harta dan simpanan dokumen.',
                'peringkat' => [
                    ['penerimaan_sijil', 'Penerimaan sijil pusaka kecil', 'biasa', 7],
                    ['pendaftaran_harta', 'Pendaftaran harta', 'biasa', 21],
                    $kelulusan, $daftar, $simpan,
                ],
                'dokumen' => [
                    ['sijil_pusaka_kecil', 'Sijil pusaka kecil JKPTGS', 'surat_rasmi', 'kelulusan'],
                    ['perintah_pembahagian', 'Perintah pembahagian pusaka kecil', 'surat_rasmi', 'kelulusan'],
                    ['sijil_kematian', 'Sijil kematian', 'surat_rasmi', 'kelulusan'],
                    ['senarai_harta', 'Senarai harta', 'surat_rasmi', 'kelulusan'],
                    ['carian_rasmi', 'Carian rasmi', 'surat_rasmi', 'kelulusan'],
                ],
                'medan' => [
                    'no_sijil_pusaka_kecil' => ['label' => 'No. sijil pusaka kecil', 'jenis' => 'teks', 'peringkat' => 'penerimaan_sijil'],
                    'tarikh_sijil' => ['label' => 'Tarikh sijil', 'jenis' => 'tarikh', 'peringkat' => 'penerimaan_sijil'],
                    'pejabat_jkptgs' => ['label' => 'Pejabat JKPTGS', 'jenis' => 'teks', 'peringkat' => 'penerimaan_sijil'],
                    'no_fail_pusaka' => ['label' => 'No. fail pusaka', 'jenis' => 'teks'],
                    'nama_si_mati' => ['label' => 'Nama si mati', 'jenis' => 'teks', 'peringkat' => 'penerimaan_sijil', 'peribadi' => true],
                    'nilai_harta_pusaka' => ['label' => 'Nilai harta pusaka (RM)', 'jenis' => 'wang'],
                    'bahagian_mais' => ['label' => 'Bahagian MAIS', 'jenis' => 'teks', 'peringkat' => 'pendaftaran_harta'],
                ],
            ],

            'wakaf' => [
                'nama' => 'Wakaf',
                'singkatan' => 'WKF',
                'lampiran_h' => 'A-6.08',
                'ikon' => 'masjid',
                'warna' => '#15803D',
                'bahagian' => 'wakaf',
                'tempoh_sasaran_hari' => 120,
                'keterangan' => 'Ikrar wakaf, pengesahan syariah, pendaftaran PTG dan integrasi Portal Hartanah Wakaf Selangor.',
                'peringkat' => [
                    ['penerimaan_ikrar', 'Penerimaan ikrar wakaf', 'biasa', 14],
                    ['pengesahan_syariah', 'Pengesahan syariah', 'biasa', 30],
                    $kelulusan,
                    ['pendaftaran_hak_milik', 'Pendaftaran di PTG', 'pendaftaran', 45],
                    $simpan,
                ],
                'dokumen' => [
                    ['borang_ikrar_wakaf', 'Borang ikrar wakaf (akad)', 'surat_rasmi', 'kelulusan'],
                    ['dokumen_pengesahan_syariah', 'Dokumen pengesahan syariah', 'surat_rasmi', 'kelulusan'],
                    ['hak_milik_asal_pewakaf', 'Hak milik asal pewakaf', 'geran', 'kelulusan'],
                    ['carian_rasmi', 'Carian rasmi', 'surat_rasmi', 'kelulusan'],
                    ['laporan_penilaian', 'Laporan penilaian', 'laporan', 'kelulusan'],
                    ['borang_pindah_milik_mais', 'Borang pindah milik kepada MAIS', 'lain', 'pendaftaran_hak_milik'],
                    ['pengesahan_pendaftaran_ptg', 'Pengesahan pendaftaran PTG', 'surat_rasmi', 'simpanan_dokumen'],
                ],
                'medan' => [
                    'nama_pewakaf' => ['label' => 'Nama pewakaf', 'jenis' => 'teks', 'peringkat' => 'penerimaan_ikrar', 'peribadi' => true],
                    'jenis_wakaf' => ['label' => 'Jenis wakaf', 'jenis' => 'pilihan', 'peringkat' => 'penerimaan_ikrar', 'pilihan' => [
                        'am' => 'Wakaf am', 'khas' => 'Wakaf khas', 'irsod' => 'Irsod',
                    ]],
                    'tujuan_wakaf_mawquf_alaih' => ['label' => 'Tujuan wakaf (mawquf alaih)', 'jenis' => 'teks_panjang', 'peringkat' => 'penerimaan_ikrar'],
                    'tarikh_ikrar_akad' => ['label' => 'Tarikh ikrar / akad', 'jenis' => 'tarikh', 'peringkat' => 'penerimaan_ikrar'],
                    'tempat_ikrar' => ['label' => 'Tempat ikrar', 'jenis' => 'teks'],
                    'saksi_ikrar' => ['label' => 'Saksi ikrar', 'jenis' => 'teks_panjang', 'peribadi' => true],
                    'status_pengesahan_syariah' => ['label' => 'Status pengesahan syariah', 'jenis' => 'pilihan', 'peringkat' => 'pengesahan_syariah', 'mesti' => 'disahkan', 'pilihan' => [
                        'belum_disahkan' => 'Belum disahkan', 'disahkan' => 'Disahkan', 'ditolak' => 'Ditolak',
                    ]],
                    'pegawai_syariah_pengesah' => ['label' => 'Pegawai syariah pengesah', 'jenis' => 'teks', 'peringkat' => 'pengesahan_syariah'],
                    'rujukan_portal' => ['label' => 'Rujukan Portal Hartanah Wakaf', 'jenis' => 'teks'],
                    'no_perserahan_ptg' => ['label' => 'No. perserahan PTG', 'jenis' => 'teks', 'peringkat' => 'pendaftaran_hak_milik'],
                    'nilai_wakaf' => ['label' => 'Nilai wakaf (RM)', 'jenis' => 'wang'],
                ],
            ],

            'pembelian_terus' => [
                'nama' => 'Pembelian Terus',
                'singkatan' => 'PT',
                'lampiran_h' => 'A-6.10',
                'ikon' => 'wang',
                'warna' => '#A21CAF',
                'bahagian' => 'hartanah',
                'tempoh_sasaran_hari' => 180,
                'keterangan' => 'Perjanjian jual beli, dokumen pindah milik, bukti pembayaran dan pengesahan PTG.',
                'peringkat' => [
                    ['perjanjian_jual_beli', 'Perjanjian jual beli', 'biasa', 30],
                    $kelulusan,
                    ['bayaran_penyelesaian', 'Bayaran dan penyelesaian', 'biasa', 90],
                    ['pendaftaran_hak_milik', 'Pindah milik dan pengesahan PTG', 'pendaftaran', 45],
                    $simpan,
                ],
                'dokumen' => [
                    ['perjanjian_jual_beli', 'Perjanjian jual beli', 'perjanjian', 'kelulusan'],
                    ['laporan_penilaian_jpph', 'Laporan penilaian JPPH', 'laporan', 'kelulusan'],
                    ['kelulusan_perolehan_mais', 'Kelulusan perolehan MAIS', 'surat_rasmi', 'kelulusan'],
                    ['hak_milik_asal_penjual', 'Hak milik asal penjual', 'geran', 'kelulusan'],
                    ['bukti_bayaran', 'Bukti pembayaran deposit dan baki', 'resit', 'pendaftaran_hak_milik'],
                    ['borang_14a', 'Borang pindah milik (14A)', 'lain', 'pendaftaran_hak_milik'],
                    ['pengesahan_pendaftaran_ptg', 'Pengesahan pendaftaran PTG', 'surat_rasmi', 'simpanan_dokumen'],
                ],
                'medan' => [
                    'no_perjanjian_jual_beli' => ['label' => 'No. perjanjian jual beli', 'jenis' => 'teks', 'peringkat' => 'perjanjian_jual_beli'],
                    'tarikh_perjanjian' => ['label' => 'Tarikh perjanjian', 'jenis' => 'tarikh', 'peringkat' => 'perjanjian_jual_beli'],
                    'nama_penjual' => ['label' => 'Nama penjual', 'jenis' => 'teks', 'peringkat' => 'perjanjian_jual_beli', 'peribadi' => true],
                    'harga_belian' => ['label' => 'Harga belian (RM)', 'jenis' => 'wang', 'peringkat' => 'perjanjian_jual_beli'],
                    'deposit_dibayar' => ['label' => 'Deposit dibayar (RM)', 'jenis' => 'wang'],
                    'baki_harga_belian' => ['label' => 'Baki harga belian (RM)', 'jenis' => 'wang'],
                    'tarikh_penyelesaian' => ['label' => 'Tarikh penyelesaian', 'jenis' => 'tarikh', 'peringkat' => 'bayaran_penyelesaian'],
                    'sumber_dana' => ['label' => 'Sumber dana', 'jenis' => 'teks', 'peringkat' => 'perjanjian_jual_beli'],
                    'rujukan_kelulusan_perolehan' => ['label' => 'Rujukan kelulusan perolehan', 'jenis' => 'teks'],
                    'rujukan_penilaian_jpph' => ['label' => 'Rujukan penilaian JPPH', 'jenis' => 'teks', 'peringkat' => 'perjanjian_jual_beli'],
                    'no_perserahan_ptg' => ['label' => 'No. perserahan PTG', 'jenis' => 'teks', 'peringkat' => 'pendaftaran_hak_milik'],
                    'status_pendaftaran_ptg' => ['label' => 'Status pendaftaran PTG', 'jenis' => 'pilihan', 'peringkat' => 'pendaftaran_hak_milik', 'mesti' => 'didaftar', 'pilihan' => self::STATUS_PTG],
                ],
            ],

            'tanah_warta' => [
                'nama' => 'Tanah Warta',
                'singkatan' => 'TW',
                'lampiran_h' => 'A-6.11',
                'ikon' => 'warta',
                'warna' => '#4D7C0F',
                'bahagian' => 'hartanah',
                'tempoh_sasaran_hari' => 90,
                'keterangan' => 'Perekodan jenis dan tujuan pewartaan beserta nombor dan tarikh warta kerajaan negeri.',
                'peringkat' => [
                    ['rekod_warta', 'Rekod pewartaan', 'biasa', 14],
                    ['semakan', 'Semakan dokumen', 'biasa', 14],
                    $kelulusan, $daftar, $simpan,
                ],
                'dokumen' => [
                    ['petikan_warta', 'Petikan Warta Kerajaan Negeri Selangor', 'surat_rasmi', 'kelulusan'],
                    ['surat_kelulusan_kerajaan_negeri', 'Surat kelulusan kerajaan negeri', 'surat_rasmi', 'kelulusan'],
                    ['pelan_kawasan_diwarta', 'Pelan kawasan diwartakan', 'pelan', 'kelulusan'],
                    ['carian_rasmi', 'Carian rasmi', 'surat_rasmi', 'kelulusan'],
                    ['hak_milik_dikeluarkan', 'Hak milik yang dikeluarkan', 'geran', 'simpanan_dokumen'],
                ],
                'medan' => [
                    'no_warta_kerajaan' => ['label' => 'No. warta kerajaan', 'jenis' => 'teks', 'peringkat' => 'rekod_warta'],
                    'tarikh_warta' => ['label' => 'Tarikh warta', 'jenis' => 'tarikh', 'peringkat' => 'rekod_warta'],
                    'jenis_pewartaan' => ['label' => 'Jenis pewartaan', 'jenis' => 'pilihan', 'peringkat' => 'rekod_warta', 'pilihan' => [
                        'rizab_agama_islam' => 'Rizab agama Islam', 'tapak_masjid_surau' => 'Tapak masjid / surau',
                        'tanah_perkuburan' => 'Tanah perkuburan Islam', 'rizab_lain' => 'Rizab lain',
                    ]],
                    'tujuan_pewartaan' => ['label' => 'Tujuan pewartaan', 'jenis' => 'teks_panjang', 'peringkat' => 'rekod_warta'],
                    'pihak_berkuasa_mewartakan' => ['label' => 'Pihak berkuasa mewartakan', 'jenis' => 'teks', 'peringkat' => 'rekod_warta'],
                    'keluasan_diwartakan' => ['label' => 'Keluasan diwartakan (m²)', 'jenis' => 'nombor', 'peringkat' => 'semakan'],
                    'syarat_pewartaan' => ['label' => 'Syarat pewartaan', 'jenis' => 'teks_panjang'],
                ],
            ],
        ];
    }

    private const STATUS_PTG = [
        'belum_diserah' => 'Belum diserah', 'diserah' => 'Telah diserah', 'didaftar' => 'Didaftarkan', 'ditolak' => 'Ditolak PTG',
    ];

    /**
     * Party roles allowed per channel (SRS-PER-23).
     *
     * @return array<string, list<string>>
     */
    public static function partyRoles(): array
    {
        return [
            'pemberimilikan_5a' => ['agensi', 'pegawai_tanah'],
            'serah_milik' => ['penyerah_hak', 'agensi'],
            'faraid' => ['si_mati', 'waris', 'pegawai_mahkamah'],
            'daftar_kematian' => ['si_mati', 'waris'],
            'perintah_arb' => ['si_mati', 'waris', 'pemegang_amanah', 'agensi'],
            'serahan_hakmilik' => ['penyerah_hak'],
            'perintah_mahkamah' => ['si_mati', 'waris', 'pemegang_amanah', 'pegawai_mahkamah'],
            'pusaka_jkptgs' => ['si_mati', 'waris', 'agensi'],
            'wakaf' => ['pewakaf', 'saksi', 'pemegang_amanah'],
            'pembelian_terus' => ['penjual', 'peguam', 'agensi'],
            'tanah_warta' => ['agensi'],
        ];
    }

    public const PERANAN_PIHAK = [
        'pewakaf' => 'Pewakaf',
        'si_mati' => 'Si mati',
        'waris' => 'Waris',
        'penyerah_hak' => 'Penyerah hak',
        'penjual' => 'Penjual',
        'pemegang_amanah' => 'Pemegang amanah / pentadbir',
        'pegawai_mahkamah' => 'Pegawai mahkamah',
        'agensi' => 'Agensi',
        'pegawai_tanah' => 'Pegawai Pejabat Tanah',
        'saksi' => 'Saksi',
        'peguam' => 'Peguam',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function fields(string $channel): array
    {
        return self::channels()[$channel]['medan'] ?? [];
    }

    /** @return list<string> */
    public static function personalFields(string $channel): array
    {
        return array_keys(array_filter(self::fields($channel), fn ($f) => ! empty($f['peribadi'])));
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::channels());
    }
}
