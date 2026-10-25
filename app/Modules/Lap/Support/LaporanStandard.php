<?php

namespace App\Modules\Lap\Support;

/**
 * The standard report set (SRS-LAP-02) — portfolio, kewangan, operasi,
 * risiko, pematuhan, aset and board / statutory composites — plus links to
 * the source modules' own reports (reused, not rebuilt). Synced into
 * lap_katalog_laporan by Katalog::sync(); admins then own the metadata
 * (owner, metric definition, allowed roles, CRP-2 validation status).
 */
final class LaporanStandard
{
    public const KATEGORI = ['portfolio', 'kewangan', 'operasi', 'risiko', 'pematuhan', 'aset', 'lembaga'];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $b = ['fungsi' => 'bilangan'];
        $sum = fn (string $m) => ['fungsi' => 'jumlah', 'medan' => $m];

        $lap = [
            // ------------------------------------------------ portfolio
            'POR-01' => ['nama' => 'Portfolio hartanah mengikut daerah, mukim dan kategori guna tanah', 'kategori' => 'portfolio',
                'metrik' => 'Bilangan lot aktif (tidak diarkib / digabung) dan jumlah keluasan, berperingkat daerah → mukim → kategori guna tanah → senarai lot.',
                'def' => ['set' => 'lot', 'hierarki' => ['kod_daerah', 'kod_mukim', 'kategori_guna_tanah'], 'agregat' => [$b, $sum('keluasan')], 'carta' => 'bar']],
            'POR-02' => ['nama' => 'Lot mengikut status hak milik dan zoning', 'kategori' => 'portfolio',
                'metrik' => 'Bilangan lot mengikut status hak milik semasa (hak milik aktif terkini bagi setiap lot) dan zoning.',
                'def' => ['set' => 'lot', 'kumpulan' => ['status_hak_milik', 'zoning'], 'agregat' => [$b], 'carta' => 'bar']],
            'POR-03' => ['nama' => 'Kontrak sewaan dan pajakan mengikut status dan kategori hartanah', 'kategori' => 'portfolio',
                'metrik' => 'Bilangan kontrak dan jumlah nilai kontrak (RM) mengikut status dan kategori hartanah.',
                'def' => ['set' => 'pajakan', 'hierarki' => ['status', 'kategori_hartanah', 'kod_daerah'], 'agregat' => [$b, $sum('nilai_kontrak')], 'carta' => 'pai']],
            'POR-04' => ['nama' => 'Pemerolehan tanah mengikut status dan bahagian', 'kategori' => 'portfolio',
                'metrik' => 'Bilangan kes pemerolehan, jumlah nilai pemerolehan dan bilangan melebihi tempoh sasaran.',
                'def' => ['set' => 'pemerolehan', 'hierarki' => ['status_pemerolehan', 'bahagian'], 'agregat' => [$b, $sum('nilai_pemerolehan'), ['fungsi' => 'bilangan_benar', 'medan' => 'melebihi_sasaran']], 'tempoh' => ['pratetap' => 'tahun_ini']]],

            // ------------------------------------------------ kewangan
            'KEW-01' => ['nama' => 'Hasil sewaan bulanan — perbandingan tahun ke tahun', 'kategori' => 'kewangan',
                'metrik' => 'Caj sewaan dikeluarkan dan amaun dibayar (RM) mengikut bulan caj, berbanding bulan yang sama tahun sebelumnya. Perbezaan dan peratus dikira dengan aritmetik perpuluhan tepat.',
                'def' => ['set' => 'caj_sewaan', 'kumpulan' => ['tarikh_caj:bulan'], 'agregat' => [$sum('amaun'), $sum('amaun_dibayar')], 'penapis' => [['medan' => 'kod_caj', 'op' => 'tidak_sama', 'nilai' => 'deposit']], 'tempoh' => ['pratetap' => 'tahun_ini'], 'bandingan' => 'tahun_lepas', 'carta' => 'garis']],
            'KEW-02' => ['nama' => 'Tunggakan sewaan mengikut umur hutang', 'kategori' => 'kewangan',
                'metrik' => 'Baki caj melepasi tarikh akhir bayar (caj − dibayar − dikecualikan), dikumpul 0-30 / 31-90 / 91-180 / 181-365 / >365 hari.',
                'def' => ['set' => 'caj_sewaan', 'hierarki' => ['kumpulan_umur', 'kod_daerah'], 'agregat' => [$b, $sum('baki')], 'penapis' => [['medan' => 'tertunggak', 'op' => 'benar']], 'susunan' => [['medan' => 'kumpulan_umur', 'arah' => 'asc']]]],
            'KEW-03' => ['nama' => 'Tunggakan cukai tanah dan taksiran mengikut daerah', 'kategori' => 'kewangan',
                'metrik' => 'Baki cukai (jumlah + denda − dibayar) yang melepasi tarikh akhir dan belum dibayar.',
                'def' => ['set' => 'cukai', 'hierarki' => ['kod_daerah', 'kod_mukim'], 'agregat' => [$b, $sum('baki'), $sum('amaun_denda')], 'penapis' => [['medan' => 'tertunggak', 'op' => 'benar']]]],
            'KEW-04' => ['nama' => 'Kos penyelenggaraan aset — perbandingan tahun ke tahun', 'kategori' => 'kewangan',
                'metrik' => 'Kos sebenar dan kos anggaran kerja penyelenggaraan mengikut daerah dan jenis penyelenggaraan, berbanding tahun sebelumnya.',
                'def' => ['set' => 'penyelenggaraan', 'hierarki' => ['kod_daerah', 'jenis_penyelenggaraan'], 'agregat' => [$sum('kos'), $sum('kos_anggaran'), $b], 'tempoh' => ['pratetap' => 'tahun_ini'], 'bandingan' => 'tahun_lepas']],
            'KEW-05' => ['nama' => 'Kos guaman dan pendedahan tuntutan kes mahkamah', 'kategori' => 'kewangan',
                'metrik' => 'Jumlah kos guaman terkumpul dan nilai tuntutan mengikut jenis kes.',
                'def' => ['set' => 'kes', 'hierarki' => ['jenis', 'status'], 'agregat' => [$b, $sum('kos_guaman'), $sum('nilai_tuntutan')]]],
            'KEW-06' => ['nama' => 'Kutipan cukai bulanan — perbandingan tahun ke tahun', 'kategori' => 'kewangan',
                'metrik' => 'Jumlah cukai dibilkan dan amaun dibayar mengikut bulan bil, berbanding tahun sebelumnya.',
                'def' => ['set' => 'cukai', 'kumpulan' => ['tarikh_bil:bulan'], 'agregat' => [$sum('jumlah'), $sum('amaun_dibayar')], 'tempoh' => ['pratetap' => 'tahun_ini'], 'bandingan' => 'tahun_lepas', 'carta' => 'garis']],

            // ------------------------------------------------ operasi
            'OPS-01' => ['nama' => 'Pencapaian SLA tugasan aliran kerja mengikut modul', 'kategori' => 'operasi',
                'metrik' => 'Peratus tugasan diselesaikan (atau masih terbuka) dalam tarikh akhir SLA, dan bilangan tugasan terbuka melepasi SLA, 12 bulan terakhir.',
                'def' => ['set' => 'tugasan', 'hierarki' => ['modul', 'kod_aliran'], 'agregat' => [$b, ['fungsi' => 'peratus_benar', 'medan' => 'dalam_sla'], ['fungsi' => 'bilangan_benar', 'medan' => 'lewat_terbuka']], 'tempoh' => ['pratetap' => '12_bulan']]],
            'OPS-02' => ['nama' => 'Aduan mengikut kategori dan status', 'kategori' => 'operasi',
                'metrik' => 'Bilangan aduan, aduan masih terbuka dan purata tempoh selesai (hari) mengikut kategori dan status.',
                'def' => ['set' => 'aduan', 'hierarki' => ['kategori', 'status', 'kod_daerah'], 'agregat' => [$b, ['fungsi' => 'bilangan_benar', 'medan' => 'terbuka'], ['fungsi' => 'purata', 'medan' => 'tempoh_selesai_hari']], 'tempoh' => ['pratetap' => 'tahun_ini']]],
            'OPS-03' => ['nama' => 'Trend aduan bulanan — perbandingan tahun ke tahun', 'kategori' => 'operasi',
                'metrik' => 'Bilangan aduan diterima mengikut bulan, berbanding bulan yang sama tahun sebelumnya.',
                'def' => ['set' => 'aduan', 'kumpulan' => ['tarikh_aduan:bulan'], 'agregat' => [$b], 'tempoh' => ['pratetap' => 'tahun_ini'], 'bandingan' => 'tahun_lepas', 'carta' => 'garis']],
            'OPS-04' => ['nama' => 'Isu pemantauan tanah terbuka mengikut tahap keterukan', 'kategori' => 'operasi',
                'metrik' => 'Isu pemantauan berstatus terbuka / dalam tindakan dan purata tempoh terbuka (hari).',
                'def' => ['set' => 'pemantauan', 'hierarki' => ['tahap_keterukan', 'kategori_isu', 'kod_daerah'], 'agregat' => [$b, ['fungsi' => 'purata', 'medan' => 'tempoh_hari']], 'penapis' => [['medan' => 'terbuka', 'op' => 'benar']]]],

            // ------------------------------------------------ risiko
            'RIS-01' => ['nama' => 'Lot berisiko tinggi (risk rating merah)', 'kategori' => 'risiko',
                'metrik' => 'Lot dengan kod warna risk rating semasa merah (MOD-PTN), mengikut daerah → mukim → senarai lot.',
                'def' => ['set' => 'risiko', 'hierarki' => ['kod_daerah', 'kod_mukim'], 'agregat' => [$b, ['fungsi' => 'purata', 'medan' => 'skor_keseluruhan']], 'penapis' => [['medan' => 'kod_warna', 'op' => 'sama', 'nilai' => 'merah']]]],
            'RIS-02' => ['nama' => 'Kontrak sewaan / pajakan tamat tempoh dalam 180 hari', 'kategori' => 'risiko',
                'metrik' => 'Kontrak berkuat kuasa yang tarikh tamatnya dalam 0–180 hari dari hari ini.',
                'def' => ['set' => 'pajakan', 'hierarki' => ['kod_daerah'], 'agregat' => [$b, $sum('nilai_kontrak')], 'penapis' => [['medan' => 'status', 'op' => 'dalam', 'nilai' => ['aktif', 'hampir_tamat']], ['medan' => 'baki_hari', 'op' => 'antara', 'nilai' => ['0', '180']]]]],
            'RIS-03' => ['nama' => 'Hak milik hampir tamat tempoh (365 hari)', 'kategori' => 'risiko',
                'metrik' => 'Hak milik pajakan yang tamat tempoh dalam 0–365 hari.',
                'def' => ['set' => 'hak_milik', 'hierarki' => ['kod_daerah', 'jenis_hak_milik'], 'agregat' => [$b], 'penapis' => [['medan' => 'baki_hari', 'op' => 'antara', 'nilai' => ['0', '365']]]]],
            'RIS-04' => ['nama' => 'Analitik risiko cukai tertunggak (cadangan sistem)', 'kategori' => 'risiko',
                'metrik' => 'Skor kebarangkalian tunggakan 0–100 (model peraturan MOD-LAP: umur tunggakan, sejarah lewat bayar, nilai baki, denda) mengikut aras.',
                'def' => ['set' => 'analitik', 'hierarki' => ['aras_risiko', 'kod_daerah'], 'agregat' => [$b, ['fungsi' => 'purata', 'medan' => 'skor']], 'penapis' => [['medan' => 'jenis_analitik', 'op' => 'sama', 'nilai' => 'risiko_tunggakan']]]],
            'RIS-05' => ['nama' => 'Analitik tanah tidak produktif (cadangan sistem)', 'kategori' => 'risiko',
                'metrik' => 'Lot tanpa pajakan aktif dengan penemuan pemantauan terbiar / tiada lawatan, mengikut aras.',
                'def' => ['set' => 'analitik', 'hierarki' => ['aras_risiko', 'kod_daerah'], 'agregat' => [$b], 'penapis' => [['medan' => 'jenis_analitik', 'op' => 'sama', 'nilai' => 'tanah_tidak_produktif']]]],

            // ------------------------------------------------ pematuhan
            'PEM-01' => ['nama' => 'Jejak penjanaan dan eksport laporan', 'kategori' => 'pematuhan', 'kebenaran' => 'lap.log.lihat',
                'metrik' => 'Setiap penjanaan / eksport laporan MOD-LAP dengan format, mod dan bilangan rekod dikeluarkan (SRS-LAP-10).',
                'def' => ['set' => 'log_laporan', 'hierarki' => ['tarikh:bulan', 'format_eksport', 'nama_pengguna'], 'agregat' => [$b, $sum('bilangan_rekod')], 'tempoh' => ['pratetap' => '12_bulan']]],
            'PEM-02' => ['nama' => 'Kes mahkamah aktif mengikut jenis dan peringkat', 'kategori' => 'pematuhan',
                'metrik' => 'Kes yang belum selesai / ditutup / diputuskan / ditarik balik.',
                'def' => ['set' => 'kes', 'hierarki' => ['jenis', 'peringkat_mahkamah'], 'agregat' => [$b, $sum('nilai_tuntutan')], 'penapis' => [['medan' => 'aktif', 'op' => 'benar']]]],
            'PEM-03' => ['nama' => 'Capaian dan eksport data (audit trail)', 'kategori' => 'pematuhan',
                'metrik' => 'Tindakan lihat / muat turun / cetak / eksport yang direkod MOD-AUD, 30 hari terakhir.',
                'def' => ['set' => 'aktiviti_audit', 'hierarki' => ['modul', 'jenis_tindakan', 'pengecam_pengguna'], 'agregat' => [$b], 'penapis' => [['medan' => 'jenis_tindakan', 'op' => 'dalam', 'nilai' => ['lihat', 'muat_turun', 'cetak', 'eksport']]], 'tempoh' => ['pratetap' => '30_hari']]],
            'PEM-04' => ['nama' => 'Analitik penggunaan sistem mengikut modul (input pelan peningkatan)', 'kategori' => 'pematuhan',
                'metrik' => 'Bilangan aktiviti pengguna mengikut modul dan bulan, sebagai asas cadangan pelan peningkatan sistem 3–5 tahun (SRS-LAP-22).',
                'def' => ['set' => 'aktiviti_audit', 'kumpulan' => ['modul'], 'agregat' => [$b], 'tempoh' => ['pratetap' => '12_bulan'], 'bandingan' => 'tahun_lepas']],

            // ------------------------------------------------ aset
            'AST-01' => ['nama' => 'Aset fizikal mengikut kondisi dan daerah', 'kategori' => 'aset',
                'metrik' => 'Bilangan aset dan jumlah nilai semasa mengikut kondisi → daerah → senarai aset.',
                'def' => ['set' => 'aset', 'hierarki' => ['status_kondisi', 'kod_daerah'], 'agregat' => [$b, $sum('nilai_semasa')]]],
            'AST-02' => ['nama' => 'Aset tanpa pemeriksaan melebihi 365 hari', 'kategori' => 'aset',
                'metrik' => 'Aset yang pemeriksaan terakhirnya lebih 365 hari lalu atau tiada rekod pemeriksaan.',
                'def' => ['set' => 'aset', 'hierarki' => ['kod_daerah'], 'agregat' => [$b], 'penapis' => [['medan' => 'hari_sejak_pemeriksaan', 'op' => 'lebih', 'nilai' => '365']]]],
        ];

        $out = [];
        $i = 0;
        foreach ($lap as $kod => $r) {
            $set = SetData::get($r['def']['set']);
            $out[$kod] = [
                'nama' => $r['nama'], 'kategori' => $r['kategori'], 'jenis' => 'lap', 'set_data' => $r['def']['set'], 'definisi' => $r['def'],
                'definisi_metrik' => $r['metrik'], 'kebenaran' => $r['kebenaran'] ?? null, 'pautan' => null, 'bahagian' => null,
                'sumber_data' => 'laporan.'.$set['paparan'].' ← '.$set['jadual_sumber'].' (MOD-'.strtoupper($set['modul']).')',
                'susunan' => ++$i,
            ];
        }

        $out['LEM-01'] = ['nama' => 'Laporan suku tahunan Lembaga / Mesyuarat Pengurusan MAIS', 'kategori' => 'lembaga', 'jenis' => 'komposit', 'set_data' => null, 'definisi' => null,
            'definisi_metrik' => 'Gabungan laporan standard portfolio, hasil, tunggakan, SLA, risiko dan perundangan untuk kertas Lembaga. Setiap bahagian tertakluk kepada kebenaran dan skop pembaca.',
            'kebenaran' => null, 'pautan' => null, 'bahagian' => ['POR-01', 'KEW-01', 'KEW-02', 'KEW-03', 'OPS-01', 'OPS-02', 'RIS-01', 'RIS-02', 'PEM-02'],
            'sumber_data' => 'Laporan standard MOD-LAP (lihat setiap bahagian)', 'susunan' => ++$i];
        $out['LEM-02'] = ['nama' => 'Laporan tahunan hasil dan kos (penyata berkanun)', 'kategori' => 'lembaga', 'jenis' => 'komposit', 'set_data' => null, 'definisi' => null,
            'definisi_metrik' => 'Hasil sewaan, kutipan cukai, kos penyelenggaraan dan kos guaman bagi tahun semasa berbanding tahun lepas.',
            'kebenaran' => null, 'pautan' => null, 'bahagian' => ['KEW-01', 'KEW-06', 'KEW-04', 'KEW-05'],
            'sumber_data' => 'Laporan standard MOD-LAP (lihat setiap bahagian)', 'susunan' => ++$i];

        foreach (self::moduleReports() as $kod => $r) {
            $out[$kod] = $r + ['jenis' => 'modul', 'set_data' => null, 'definisi' => null, 'bahagian' => null, 'susunan' => ++$i];
        }

        return $out;
    }

    /**
     * Reports the source modules already provide — listed in the central
     * catalogue and opened in the module itself (its own API enforces access).
     *
     * @return array<string, array<string, mixed>>
     */
    private static function moduleReports(): array
    {
        $m = fn (string $nama, string $kategori, string $pautan, string $kebenaran, string $sumber) => [
            'nama' => $nama, 'kategori' => $kategori, 'pautan' => $pautan, 'kebenaran' => $kebenaran, 'sumber_data' => $sumber,
            'definisi_metrik' => 'Ditakrif oleh modul sumber; lihat laman laporan modul.',
        ];

        return [
            'ADU-prestasi_sla' => $m('Prestasi pencapaian SLA aduan', 'operasi', '/admin/adu/laporan?tab=prestasi_sla', 'adu.laporan.lihat', 'MOD-ADU: aduan, jejak_sla_aduan'),
            'ADU-tertangguh' => $m('Aduan tertangguh dan melepasi SLA', 'operasi', '/admin/adu/laporan?tab=tertangguh', 'adu.laporan.lihat', 'MOD-ADU: aduan'),
            'ADU-kawasan_panas' => $m('Kawasan panas aduan mengikut mukim', 'risiko', '/admin/adu/laporan?tab=kawasan_panas', 'adu.laporan.lihat', 'MOD-ADU: aduan'),
            'ADU-audit_capaian' => $m('Audit capaian identiti pengadu', 'pematuhan', '/admin/adu/laporan?tab=audit_capaian', 'adu.audit_capaian.lihat', 'MOD-ADU: audit_capaian_pengadu'),
            'AST-inventori' => $m('Inventori aset (kategori, daerah, kondisi, nilai)', 'aset', '/admin/ast/laporan?kod=inventori', 'ast.laporan.lihat', 'MOD-AST: aset_fizikal'),
            'AST-nilai_portfolio' => $m('Nilai portfolio aset dan perbandingan tahunan', 'aset', '/admin/ast/laporan?kod=nilai_portfolio', 'ast.laporan.lihat', 'MOD-AST: aset_nilai_sejarah'),
            'AST-aset_kritikal' => $m('Senarai aset kritikal', 'risiko', '/admin/ast/laporan?kod=aset_kritikal', 'ast.laporan.lihat', 'MOD-AST: aset_fizikal'),
            'AUD-laporan' => $m('Laporan audit keselamatan (Sulit/Rahsia, luar waktu, eksport besar, log masuk gagal)', 'pematuhan', '/admin/aud/laporan', 'aud.laporan.lihat', 'MOD-AUD: audit_rekod, audit_eksport'),
            'CKI-tertunggak' => $m('Cukai tertunggak — umur tunggakan dan denda', 'kewangan', '/admin/cki/laporan?jenis=tertunggak', 'cki.laporan.lihat', 'MOD-CKI: cukai, lejar_cukai'),
            'CKI-trend_tahunan' => $m('Trend bayaran cukai tahunan', 'kewangan', '/admin/cki/laporan?jenis=trend_tahunan', 'cki.laporan.lihat', 'MOD-CKI: cukai'),
            'CKI-rekonsiliasi' => $m('Rekonsiliasi cukai sistem, resit dan GRP', 'pematuhan', '/admin/cki/laporan?jenis=rekonsiliasi', 'cki.laporan.lihat', 'MOD-CKI: rekonsiliasi_cukai_larian, sisihan_rekonsiliasi'),
            'CKI-anggaran_obligasi' => $m('Anggaran obligasi cukai tahunan (bajet)', 'kewangan', '/admin/cki/laporan?jenis=anggaran_obligasi', 'cki.laporan.lihat', 'MOD-CKI: obligasi_cukai'),
            'HKM-ringkasan' => $m('Hak milik mengikut daerah, jenis dan status', 'portfolio', '/admin/hkm/laporan?jenis=ringkasan', 'hkm.hak_milik.lihat', 'MOD-HKM: hak_milik'),
            'HKM-lot_tanpa_hak_milik' => $m('Lot tanpa hak milik disahkan', 'risiko', '/admin/hkm/laporan?jenis=lot_tanpa_hak_milik', 'hkm.hak_milik.lihat', 'MOD-HKM: hak_milik, lot_tanah'),
            'HKM-dokumen_belum_pulang' => $m('Dokumen asal belum dipulangkan', 'pematuhan', '/admin/hkm/laporan?jenis=dokumen_belum_pulang', 'hkm.peti_besi.lihat', 'MOD-HKM: pengeluaran_dokumen'),
            'KES-pendedahan' => $m('Pendedahan perundangan portfolio', 'risiko', '/admin/kes/laporan?jenis=pendedahan', 'kes.laporan.lihat', 'MOD-KES: kes_mahkamah'),
            'KES-tanpa_tarikh' => $m('Kes tanpa tarikh prosiding berikutnya', 'pematuhan', '/admin/kes/laporan?jenis=tanpa_tarikh', 'kes.laporan.lihat', 'MOD-KES: kes_mahkamah'),
            'PER-ringkasan' => $m('Ringkasan pemerolehan dan laporan khas (Notis 5A, MMKN, melebihi tempoh)', 'portfolio', '/admin/per/laporan', 'per.laporan.lihat', 'MOD-PER: pemerolehan'),
            'PJK-tunggakan' => $m('Tunggakan sewaan dengan analisis umur hutang', 'kewangan', '/admin/pjk/laporan?jenis=tunggakan', 'pjk.laporan.lihat', 'MOD-PJK: jadual_caj'),
            'PJK-kutipan' => $m('Kutipan hasil mengikut kaedah bayaran', 'kewangan', '/admin/pjk/laporan?jenis=kutipan', 'pjk.laporan.lihat', 'MOD-PJK: bayaran'),
            'PJK-penghunian' => $m('Penghunian dan kekosongan premis', 'portfolio', '/admin/pjk/laporan?jenis=penghunian', 'pjk.laporan.lihat', 'MOD-PJK: premis_aset'),
            'PJK-rekonsiliasi' => $m('Rekonsiliasi payment gateway, GRP dan e-Sewa', 'pematuhan', '/admin/pjk/laporan?jenis=rekonsiliasi', 'pjk.laporan.lihat', 'MOD-PJK: rekonsiliasi_larian'),
            'PTN-prestasi' => $m('Prestasi pemantauan tanah', 'operasi', '/admin/ptn/laporan?laporan=prestasi', 'ptn.laporan.lihat', 'MOD-PTN: rekod_lawatan, jadual_pemantauan'),
            'PTN-pencerobohan' => $m('Penemuan pencerobohan mengikut daerah dan tempoh', 'risiko', '/admin/ptn/laporan?laporan=pencerobohan', 'ptn.laporan.lihat', 'MOD-PTN: isu_pemantauan'),
            'RBC-laporan' => $m('Pengguna, peranan dan capaian (tadbir urus akaun)', 'pematuhan', '/admin/rbc/laporan', 'rbc.laporan.lihat', 'MOD-RBC: users, peranan, log_log_masuk'),
            'TSU-kerja' => $m('Kerja pelupusan, tukar syarat, ukur dan demarkasi', 'operasi', '/admin/tsu/laporan', 'tsu.laporan.lihat', 'MOD-TSU: urusan_tanah'),
            'WFE-prestasi' => $m('Prestasi aliran kerja, kesesakan dan eskalasi', 'operasi', '/admin/wfe/laporan', 'wfe.laporan.lihat', 'MOD-WFE: wf_tugasan, wf_instans'),
            'PRO-kualiti' => $m('Kualiti data rekod induk', 'pematuhan', '/admin/pro/kualiti?tab=laporan', 'pro.kualiti.lihat', 'MOD-PRO: tugasan_kualiti_data'),
        ];
    }
}
