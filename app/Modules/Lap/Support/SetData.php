<?php

namespace App\Modules\Lap\Support;

/**
 * Curated datasets (SRS-LAP-03): the only things a report — standard, ad hoc,
 * AI-prompted, scheduled — can query. Each maps to one row-level view in the
 * `laporan` schema (RLS by session settings) and whitelists its fields, so the
 * query builder never interpolates anything a user typed.
 *
 * Field types: teks | kod | nombor | wang | tarikh | bool. `nama` names the
 * column holding a code's display name; `sumber` is "<jadual>.<medan>" in the
 * owning module's table for the data dictionary (MOD-PRO kamus_data).
 */
final class SetData
{
    public const JENIS_NOMBOR = ['nombor', 'wang'];

    /** @var array<string, array<string, mixed>> */
    private const COMMON = [
        'kod_daerah' => ['label' => 'Daerah', 'jenis' => 'kod', 'nama' => 'nama_daerah'],
        'kod_mukim' => ['label' => 'Mukim', 'jenis' => 'kod', 'nama' => 'nama_mukim'],
        'klasifikasi' => ['label' => 'Klasifikasi', 'jenis' => 'kod'],
    ];

    /**
     * @return array<string, array{label: string, paparan: string, modul: string, kategori: string, kebenaran: string, tahap: array<string, string>, pautan: string|null, tarikh: string|null, jadual_sumber: string, medan: array<string, array<string, mixed>>}>
     */
    public static function all(): array
    {
        $c = self::COMMON;

        return [
            'lot' => [
                'label' => 'Lot tanah (portfolio)', 'paparan' => 'v_lot', 'modul' => 'pro', 'kategori' => 'portfolio', 'kebenaran' => 'pro.lot.lihat',
                'tahap' => ['sulit' => 'pro.sulit.lihat', 'rahsia' => 'pro.rahsia.lihat'], 'pautan' => '/admin/pro/lot/{id}', 'tarikh' => 'tarikh_daftar', 'jadual_sumber' => 'lot_tanah',
                'medan' => [
                    'no_lot' => ['label' => 'No. lot', 'jenis' => 'teks', 'sumber' => 'lot_tanah.no_lot'],
                    ...$c,
                    'kategori_guna_tanah' => ['label' => 'Kategori guna tanah', 'jenis' => 'kod', 'sumber' => 'lot_tanah.kategori_guna_tanah'],
                    'zoning' => ['label' => 'Zoning', 'jenis' => 'kod', 'sumber' => 'lot_tanah.zoning'],
                    'status_lot' => ['label' => 'Status lot', 'jenis' => 'kod', 'sumber' => 'lot_tanah.status_lot'],
                    'bahagian' => ['label' => 'Bahagian pemilik', 'jenis' => 'kod', 'sumber' => 'lot_tanah.bahagian_pemilik'],
                    'status_hak_milik' => ['label' => 'Status hak milik', 'jenis' => 'kod', 'sumber' => 'hak_milik.status_semasa'],
                    'jenis_hak_milik' => ['label' => 'Jenis hak milik', 'jenis' => 'kod', 'sumber' => 'hak_milik.jenis_hak_milik'],
                    'keluasan' => ['label' => 'Keluasan', 'jenis' => 'nombor', 'sumber' => 'lot_tanah.keluasan'],
                    'nilai_pasaran' => ['label' => 'Nilai pasaran terkini (RM)', 'jenis' => 'wang', 'sumber' => 'penilaian.nilai_pasaran', 'kebenaran' => 'pro.rahsia.lihat'],
                    'ada_pajakan_aktif' => ['label' => 'Ada pajakan aktif', 'jenis' => 'bool'],
                    'ada_kes_aktif' => ['label' => 'Ada kes mahkamah aktif', 'jenis' => 'bool'],
                    'ada_isu_pencerobohan' => ['label' => 'Ada isu pencerobohan terbuka', 'jenis' => 'bool'],
                    'ada_isu_terbiar' => ['label' => 'Ada isu tanah terbiar terbuka', 'jenis' => 'bool'],
                    'lawatan_terakhir' => ['label' => 'Lawatan pemantauan terakhir', 'jenis' => 'tarikh', 'sumber' => 'rekod_lawatan.tarikh_lawatan'],
                    'keadaan_tapak_terakhir' => ['label' => 'Keadaan tapak terakhir', 'jenis' => 'kod', 'sumber' => 'rekod_lawatan.keadaan_tapak'],
                    'keluasan_m2' => ['label' => 'Keluasan (m²)', 'jenis' => 'nombor'],
                    'tarikh_daftar' => ['label' => 'Tarikh daftar', 'jenis' => 'tarikh', 'sumber' => 'lot_tanah.created_at'],
                ],
            ],
            'hak_milik' => [
                'label' => 'Hak milik', 'paparan' => 'v_hak_milik', 'modul' => 'hkm', 'kategori' => 'portfolio', 'kebenaran' => 'hkm.hak_milik.lihat',
                'tahap' => ['sulit' => 'hkm.sulit.lihat', 'rahsia' => 'hkm.sulit.lihat'], 'pautan' => '/admin/hkm/hak-milik/{id}', 'tarikh' => 'tarikh_pendaftaran', 'jadual_sumber' => 'hak_milik',
                'medan' => [
                    'no_hak_milik' => ['label' => 'No. hak milik', 'jenis' => 'teks', 'sumber' => 'hak_milik.no_hak_milik'],
                    'no_lot' => ['label' => 'No. lot', 'jenis' => 'teks', 'sumber' => 'lot_tanah.no_lot'],
                    ...$c,
                    'jenis_hak_milik' => ['label' => 'Jenis hak milik', 'jenis' => 'kod', 'sumber' => 'hak_milik.jenis_hak_milik'],
                    'kategori_tanah' => ['label' => 'Kategori tanah', 'jenis' => 'kod', 'sumber' => 'hak_milik.kategori_tanah'],
                    'status_semasa' => ['label' => 'Status', 'jenis' => 'kod', 'sumber' => 'hak_milik.status_semasa'],
                    'disahkan' => ['label' => 'Disahkan', 'jenis' => 'bool', 'sumber' => 'hak_milik.disahkan'],
                    'tarikh_pendaftaran' => ['label' => 'Tarikh pendaftaran', 'jenis' => 'tarikh', 'sumber' => 'hak_milik.tarikh_pendaftaran'],
                    'tarikh_tamat_tempoh' => ['label' => 'Tarikh tamat tempoh', 'jenis' => 'tarikh', 'sumber' => 'hak_milik.tarikh_tamat_tempoh'],
                    'baki_hari' => ['label' => 'Baki hari ke tamat', 'jenis' => 'nombor'],
                    'keluasan' => ['label' => 'Keluasan', 'jenis' => 'nombor', 'sumber' => 'hak_milik.keluasan'],
                ],
            ],
            'pajakan' => [
                'label' => 'Kontrak sewaan & pajakan', 'paparan' => 'v_kontrak_pajakan', 'modul' => 'pjk', 'kategori' => 'portfolio', 'kebenaran' => 'pjk.laporan.lihat',
                'tahap' => ['sulit' => 'pjk.sulit.lihat', 'rahsia' => 'pjk.rahsia.lihat'], 'pautan' => '/admin/pjk/kontrak/{id}', 'tarikh' => 'tarikh_mula', 'jadual_sumber' => 'kontrak_pajakan',
                'medan' => [
                    'no_kontrak' => ['label' => 'No. kontrak', 'jenis' => 'teks', 'sumber' => 'kontrak_pajakan.no_kontrak'],
                    'no_lot' => ['label' => 'No. lot', 'jenis' => 'teks'],
                    ...$c,
                    'bahagian' => ['label' => 'Bahagian', 'jenis' => 'kod', 'sumber' => 'kontrak_pajakan.bahagian'],
                    'status' => ['label' => 'Status kontrak', 'jenis' => 'kod', 'sumber' => 'kontrak_pajakan.status'],
                    'jenis_perjanjian' => ['label' => 'Jenis perjanjian', 'jenis' => 'kod', 'sumber' => 'kontrak_pajakan.jenis_perjanjian'],
                    'kategori_hartanah' => ['label' => 'Kategori hartanah', 'jenis' => 'kod', 'sumber' => 'kontrak_pajakan.kategori_hartanah'],
                    'tarikh_mula' => ['label' => 'Tarikh mula', 'jenis' => 'tarikh', 'sumber' => 'kontrak_pajakan.tarikh_mula'],
                    'tarikh_tamat' => ['label' => 'Tarikh tamat', 'jenis' => 'tarikh', 'sumber' => 'kontrak_pajakan.tarikh_tamat'],
                    'baki_hari' => ['label' => 'Baki hari ke tamat', 'jenis' => 'nombor'],
                    'kadar' => ['label' => 'Kadar sewa (RM)', 'jenis' => 'wang', 'sumber' => 'kontrak_pajakan.kadar'],
                    'nilai_kontrak' => ['label' => 'Nilai kontrak (RM)', 'jenis' => 'wang', 'sumber' => 'kontrak_pajakan.nilai_kontrak'],
                ],
            ],
            'caj_sewaan' => [
                'label' => 'Caj & tunggakan sewaan', 'paparan' => 'v_caj_sewaan', 'modul' => 'pjk', 'kategori' => 'kewangan', 'kebenaran' => 'pjk.laporan.lihat',
                'tahap' => ['sulit' => 'pjk.sulit.lihat', 'rahsia' => 'pjk.rahsia.lihat'], 'pautan' => '/admin/pjk/kontrak/{kontrak_id}', 'tarikh' => 'tarikh_caj', 'jadual_sumber' => 'jadual_caj',
                'medan' => [
                    'no_kontrak' => ['label' => 'No. kontrak', 'jenis' => 'teks'],
                    'no_caj' => ['label' => 'No. caj', 'jenis' => 'teks', 'sumber' => 'jadual_caj.no_caj'],
                    ...$c,
                    'bahagian' => ['label' => 'Bahagian', 'jenis' => 'kod'],
                    'kategori_hartanah' => ['label' => 'Kategori hartanah', 'jenis' => 'kod'],
                    'kod_caj' => ['label' => 'Jenis caj', 'jenis' => 'kod', 'sumber' => 'jadual_caj.kod_caj'],
                    'status_caj' => ['label' => 'Status caj', 'jenis' => 'kod', 'sumber' => 'jadual_caj.status_caj'],
                    'tarikh_caj' => ['label' => 'Tarikh caj', 'jenis' => 'tarikh', 'sumber' => 'jadual_caj.tarikh_caj'],
                    'tarikh_akhir' => ['label' => 'Tarikh akhir bayar', 'jenis' => 'tarikh', 'sumber' => 'jadual_caj.tarikh_akhir'],
                    'amaun' => ['label' => 'Amaun caj (RM)', 'jenis' => 'wang', 'sumber' => 'jadual_caj.amaun'],
                    'amaun_dibayar' => ['label' => 'Amaun dibayar (RM)', 'jenis' => 'wang', 'sumber' => 'jadual_caj.amaun_dibayar'],
                    'baki' => ['label' => 'Baki (RM)', 'jenis' => 'wang'],
                    'tertunggak' => ['label' => 'Tertunggak', 'jenis' => 'bool'],
                    'umur_tunggakan_hari' => ['label' => 'Umur tunggakan (hari)', 'jenis' => 'nombor'],
                    'kumpulan_umur' => ['label' => 'Kumpulan umur tunggakan', 'jenis' => 'kod'],
                ],
            ],
            'cukai' => [
                'label' => 'Cukai tanah & taksiran', 'paparan' => 'v_cukai', 'modul' => 'cki', 'kategori' => 'kewangan', 'kebenaran' => 'cki.laporan.lihat',
                'tahap' => ['sulit' => 'cki.laporan.lihat', 'rahsia' => 'cki.laporan.lihat'], 'pautan' => '/admin/cki/bil/{id}', 'tarikh' => 'tarikh_bil', 'jadual_sumber' => 'cukai',
                'medan' => [
                    'no_bil' => ['label' => 'No. bil', 'jenis' => 'teks', 'sumber' => 'cukai.no_bil'],
                    'no_lot' => ['label' => 'No. lot', 'jenis' => 'teks'],
                    ...$c,
                    'jenis' => ['label' => 'Jenis cukai', 'jenis' => 'kod', 'sumber' => 'cukai.jenis'],
                    'tahun_taksiran' => ['label' => 'Tahun taksiran', 'jenis' => 'kod', 'sumber' => 'cukai.tahun_taksiran'],
                    'status_bayaran' => ['label' => 'Status bayaran', 'jenis' => 'kod', 'sumber' => 'cukai.status_bayaran'],
                    'pihak_pengeluar' => ['label' => 'Pihak pengeluar', 'jenis' => 'kod', 'sumber' => 'cukai.pihak_pengeluar'],
                    'tarikh_bil' => ['label' => 'Tarikh bil', 'jenis' => 'tarikh', 'sumber' => 'cukai.tarikh_bil'],
                    'tarikh_akhir' => ['label' => 'Tarikh akhir', 'jenis' => 'tarikh', 'sumber' => 'cukai.tarikh_akhir'],
                    'jumlah' => ['label' => 'Jumlah cukai (RM)', 'jenis' => 'wang', 'sumber' => 'cukai.jumlah'],
                    'amaun_denda' => ['label' => 'Denda (RM)', 'jenis' => 'wang', 'sumber' => 'cukai.amaun_denda'],
                    'amaun_dibayar' => ['label' => 'Dibayar (RM)', 'jenis' => 'wang', 'sumber' => 'cukai.amaun_dibayar'],
                    'baki' => ['label' => 'Baki (RM)', 'jenis' => 'wang'],
                    'tertunggak' => ['label' => 'Tertunggak', 'jenis' => 'bool'],
                    'umur_tunggakan_hari' => ['label' => 'Umur tunggakan (hari)', 'jenis' => 'nombor'],
                    'kumpulan_umur' => ['label' => 'Kumpulan umur tunggakan', 'jenis' => 'kod'],
                ],
            ],
            'aduan' => [
                'label' => 'Aduan', 'paparan' => 'v_aduan', 'modul' => 'adu', 'kategori' => 'operasi', 'kebenaran' => 'adu.laporan.lihat',
                'tahap' => ['sulit' => 'adu.sulit.lihat', 'rahsia' => 'adu.sulit.lihat'], 'pautan' => '/admin/adu/aduan/{id}', 'tarikh' => 'tarikh_aduan', 'jadual_sumber' => 'aduan',
                'medan' => [
                    'no_rujukan_aduan' => ['label' => 'No. rujukan', 'jenis' => 'teks', 'sumber' => 'aduan.no_rujukan_aduan'],
                    ...$c,
                    'kategori' => ['label' => 'Kategori', 'jenis' => 'kod', 'sumber' => 'aduan.kategori'],
                    'subkategori' => ['label' => 'Subkategori', 'jenis' => 'kod', 'sumber' => 'aduan.subkategori'],
                    'status' => ['label' => 'Status', 'jenis' => 'kod', 'sumber' => 'aduan.status'],
                    'saluran_penerimaan' => ['label' => 'Saluran', 'jenis' => 'kod', 'sumber' => 'aduan.saluran_penerimaan'],
                    'tahap_keutamaan' => ['label' => 'Keutamaan', 'jenis' => 'kod', 'sumber' => 'aduan.tahap_keutamaan'],
                    'tarikh_aduan' => ['label' => 'Tarikh aduan', 'jenis' => 'tarikh', 'sumber' => 'aduan.tarikh_terima'],
                    'tarikh_tutup' => ['label' => 'Tarikh tutup', 'jenis' => 'tarikh', 'sumber' => 'aduan.tarikh_tutup'],
                    'tempoh_selesai_hari' => ['label' => 'Tempoh selesai (hari)', 'jenis' => 'nombor'],
                    'terbuka' => ['label' => 'Masih terbuka', 'jenis' => 'bool'],
                    'bukan_tanah_mais' => ['label' => 'Bukan tanah MAIS', 'jenis' => 'bool', 'sumber' => 'aduan.status_bukan_tanah_mais'],
                ],
            ],
            'pemantauan' => [
                'label' => 'Isu pemantauan tanah', 'paparan' => 'v_isu_pemantauan', 'modul' => 'ptn', 'kategori' => 'operasi', 'kebenaran' => 'ptn.laporan.lihat',
                'tahap' => ['sulit' => 'ptn.laporan.lihat', 'rahsia' => 'ptn.laporan.lihat'], 'pautan' => '/admin/ptn/isu/{id}', 'tarikh' => 'tarikh_dibuka', 'jadual_sumber' => 'isu_pemantauan',
                'medan' => [
                    'no_rujukan' => ['label' => 'No. rujukan', 'jenis' => 'teks', 'sumber' => 'isu_pemantauan.no_rujukan'],
                    'no_lot' => ['label' => 'No. lot', 'jenis' => 'teks'],
                    ...$c,
                    'kategori_isu' => ['label' => 'Kategori isu', 'jenis' => 'kod', 'sumber' => 'isu_pemantauan.kategori_isu'],
                    'tahap_keterukan' => ['label' => 'Tahap keterukan', 'jenis' => 'kod', 'sumber' => 'isu_pemantauan.tahap_keterukan'],
                    'status_isu' => ['label' => 'Status isu', 'jenis' => 'kod', 'sumber' => 'isu_pemantauan.status_isu'],
                    'tarikh_dibuka' => ['label' => 'Tarikh dibuka', 'jenis' => 'tarikh', 'sumber' => 'isu_pemantauan.tarikh_dibuka'],
                    'tarikh_ditutup' => ['label' => 'Tarikh ditutup', 'jenis' => 'tarikh', 'sumber' => 'isu_pemantauan.tarikh_ditutup'],
                    'tempoh_hari' => ['label' => 'Tempoh (hari)', 'jenis' => 'nombor'],
                    'terbuka' => ['label' => 'Masih terbuka', 'jenis' => 'bool'],
                ],
            ],
            'tugasan' => [
                'label' => 'Tugasan aliran kerja & SLA', 'paparan' => 'v_tugasan', 'modul' => 'wfe', 'kategori' => 'operasi', 'kebenaran' => 'wfe.laporan.lihat',
                'tahap' => [], 'pautan' => '{pautan}', 'tarikh' => 'tarikh_cipta', 'jadual_sumber' => 'wf_tugasan',
                'medan' => [
                    'tajuk' => ['label' => 'Tugasan', 'jenis' => 'teks', 'sumber' => 'wf_tugasan.tajuk'],
                    'kod_daerah' => $c['kod_daerah'],
                    'modul' => ['label' => 'Modul', 'jenis' => 'kod', 'sumber' => 'wf_instans.modul'],
                    'kod_aliran' => ['label' => 'Aliran kerja', 'jenis' => 'kod', 'sumber' => 'wf_instans.kod_aliran'],
                    'status' => ['label' => 'Status', 'jenis' => 'kod', 'sumber' => 'wf_tugasan.status'],
                    'tarikh_cipta' => ['label' => 'Tarikh cipta', 'jenis' => 'tarikh', 'sumber' => 'wf_tugasan.created_at'],
                    'tarikh_akhir_sla' => ['label' => 'Tarikh akhir SLA', 'jenis' => 'tarikh', 'sumber' => 'wf_tugasan.tarikh_akhir_sla'],
                    'diselesaikan_pada' => ['label' => 'Diselesaikan pada', 'jenis' => 'tarikh', 'sumber' => 'wf_tugasan.diselesaikan_pada'],
                    'selesai' => ['label' => 'Selesai', 'jenis' => 'bool'],
                    'dalam_sla' => ['label' => 'Dalam SLA', 'jenis' => 'bool'],
                    'lewat_terbuka' => ['label' => 'Terbuka melepasi SLA', 'jenis' => 'bool'],
                    'aras_eskalasi' => ['label' => 'Aras eskalasi', 'jenis' => 'nombor', 'sumber' => 'wf_tugasan.aras_eskalasi'],
                ],
            ],
            'kes' => [
                'label' => 'Kes mahkamah (perundangan)', 'paparan' => 'v_kes_mahkamah', 'modul' => 'kes', 'kategori' => 'pematuhan', 'kebenaran' => 'kes.laporan.lihat',
                'tahap' => ['sulit' => 'kes.laporan.lihat', 'rahsia' => 'kes.rahsia.lihat'], 'pautan' => '/admin/kes/kes/{id}', 'tarikh' => 'tarikh_pemfailan', 'jadual_sumber' => 'kes_mahkamah',
                'medan' => [
                    'no_kes' => ['label' => 'No. kes', 'jenis' => 'teks', 'sumber' => 'kes_mahkamah.no_kes'],
                    ...$c,
                    'jenis' => ['label' => 'Jenis kes', 'jenis' => 'kod', 'sumber' => 'kes_mahkamah.jenis'],
                    'status' => ['label' => 'Status', 'jenis' => 'kod', 'sumber' => 'kes_mahkamah.status'],
                    'peringkat_mahkamah' => ['label' => 'Peringkat mahkamah', 'jenis' => 'kod', 'sumber' => 'kes_mahkamah.peringkat_mahkamah'],
                    'peranan_mais' => ['label' => 'Peranan MAIS', 'jenis' => 'kod', 'sumber' => 'kes_mahkamah.peranan_mais'],
                    'tarikh_pemfailan' => ['label' => 'Tarikh pemfailan', 'jenis' => 'tarikh', 'sumber' => 'kes_mahkamah.tarikh_pemfailan'],
                    'tarikh_prosiding_berikutnya' => ['label' => 'Prosiding berikutnya', 'jenis' => 'tarikh', 'sumber' => 'kes_mahkamah.tarikh_prosiding_berikutnya'],
                    'nilai_tuntutan' => ['label' => 'Nilai tuntutan (RM)', 'jenis' => 'wang', 'sumber' => 'kes_mahkamah.nilai_tuntutan'],
                    'kos_guaman' => ['label' => 'Kos guaman (RM)', 'jenis' => 'wang', 'sumber' => 'kos_guaman.amaun'],
                    'aktif' => ['label' => 'Kes aktif', 'jenis' => 'bool'],
                ],
            ],
            'aset' => [
                'label' => 'Aset fizikal', 'paparan' => 'v_aset', 'modul' => 'ast', 'kategori' => 'aset', 'kebenaran' => 'ast.laporan.lihat',
                'tahap' => ['sulit' => 'ast.laporan.lihat', 'rahsia' => 'ast.laporan.lihat'], 'pautan' => '/admin/ast/aset/{id}', 'tarikh' => null, 'jadual_sumber' => 'aset_fizikal',
                'medan' => [
                    'no_rujukan_aset' => ['label' => 'No. rujukan aset', 'jenis' => 'teks', 'sumber' => 'aset_fizikal.no_rujukan_aset'],
                    'nama_aset' => ['label' => 'Nama aset', 'jenis' => 'teks', 'sumber' => 'aset_fizikal.nama_aset'],
                    ...$c,
                    'jenis_aset' => ['label' => 'Jenis aset', 'jenis' => 'kod', 'sumber' => 'aset_fizikal.jenis_aset'],
                    'status_aset' => ['label' => 'Status aset', 'jenis' => 'kod', 'sumber' => 'aset_fizikal.status_aset'],
                    'status_kondisi' => ['label' => 'Kondisi', 'jenis' => 'kod', 'sumber' => 'aset_fizikal.status_kondisi'],
                    'tahun_perolehan' => ['label' => 'Tahun perolehan', 'jenis' => 'kod', 'sumber' => 'aset_fizikal.tahun_perolehan'],
                    'kos_perolehan' => ['label' => 'Kos perolehan (RM)', 'jenis' => 'wang', 'sumber' => 'aset_fizikal.kos_perolehan'],
                    'nilai_semasa' => ['label' => 'Nilai semasa (RM)', 'jenis' => 'wang', 'sumber' => 'aset_fizikal.nilai_semasa'],
                    'tarikh_pemeriksaan_terakhir' => ['label' => 'Pemeriksaan terakhir', 'jenis' => 'tarikh', 'sumber' => 'aset_fizikal.tarikh_pemeriksaan_terakhir'],
                    'hari_sejak_pemeriksaan' => ['label' => 'Hari sejak pemeriksaan', 'jenis' => 'nombor'],
                ],
            ],
            'penyelenggaraan' => [
                'label' => 'Kerja & kos penyelenggaraan', 'paparan' => 'v_penyelenggaraan', 'modul' => 'ast', 'kategori' => 'kewangan', 'kebenaran' => 'ast.laporan.lihat',
                'tahap' => ['sulit' => 'ast.laporan.lihat', 'rahsia' => 'ast.laporan.lihat'], 'pautan' => '/admin/ast/aset/{id_aset}', 'tarikh' => 'tarikh_dijadual', 'jadual_sumber' => 'rekod_penyelenggaraan',
                'medan' => [
                    'no_rujukan' => ['label' => 'No. rujukan kerja', 'jenis' => 'teks', 'sumber' => 'rekod_penyelenggaraan.no_rujukan'],
                    'no_rujukan_aset' => ['label' => 'No. aset', 'jenis' => 'teks'],
                    ...$c,
                    'jenis_kerja' => ['label' => 'Jenis kerja', 'jenis' => 'kod', 'sumber' => 'rekod_penyelenggaraan.jenis_kerja'],
                    'jenis_penyelenggaraan' => ['label' => 'Jenis penyelenggaraan', 'jenis' => 'kod', 'sumber' => 'rekod_penyelenggaraan.jenis_penyelenggaraan'],
                    'keutamaan' => ['label' => 'Keutamaan', 'jenis' => 'kod', 'sumber' => 'rekod_penyelenggaraan.keutamaan'],
                    'status' => ['label' => 'Status', 'jenis' => 'kod', 'sumber' => 'rekod_penyelenggaraan.status'],
                    'tarikh_dijadual' => ['label' => 'Tarikh dijadual', 'jenis' => 'tarikh', 'sumber' => 'rekod_penyelenggaraan.tarikh_dijadual'],
                    'tarikh_siap' => ['label' => 'Tarikh siap', 'jenis' => 'tarikh', 'sumber' => 'rekod_penyelenggaraan.tarikh_siap'],
                    'kos_anggaran' => ['label' => 'Kos anggaran (RM)', 'jenis' => 'wang', 'sumber' => 'rekod_penyelenggaraan.kos_anggaran'],
                    'kos' => ['label' => 'Kos sebenar (RM)', 'jenis' => 'wang', 'sumber' => 'rekod_penyelenggaraan.kos'],
                ],
            ],
            'pemerolehan' => [
                'label' => 'Pemerolehan tanah', 'paparan' => 'v_pemerolehan', 'modul' => 'per', 'kategori' => 'portfolio', 'kebenaran' => 'per.laporan.lihat',
                'tahap' => ['sulit' => 'per.sulit.lihat', 'rahsia' => 'per.sulit.lihat'], 'pautan' => '/admin/per/kes/{id}', 'tarikh' => 'tarikh_mula_kes', 'jadual_sumber' => 'pemerolehan',
                'medan' => [
                    'no_kes' => ['label' => 'No. kes', 'jenis' => 'teks', 'sumber' => 'pemerolehan.no_kes'],
                    ...$c,
                    'bahagian' => ['label' => 'Bahagian', 'jenis' => 'kod', 'sumber' => 'pemerolehan.kod_bahagian'],
                    'status_pemerolehan' => ['label' => 'Status', 'jenis' => 'kod', 'sumber' => 'pemerolehan.status_pemerolehan'],
                    'peringkat_semasa' => ['label' => 'Peringkat', 'jenis' => 'kod', 'sumber' => 'pemerolehan.peringkat_semasa'],
                    'tarikh_mula_kes' => ['label' => 'Tarikh mula', 'jenis' => 'tarikh', 'sumber' => 'pemerolehan.tarikh_mula_kes'],
                    'tarikh_selesai' => ['label' => 'Tarikh selesai', 'jenis' => 'tarikh', 'sumber' => 'pemerolehan.tarikh_selesai'],
                    'tempoh_hari' => ['label' => 'Tempoh (hari)', 'jenis' => 'nombor'],
                    'melebihi_sasaran' => ['label' => 'Melebihi tempoh sasaran', 'jenis' => 'bool'],
                    'nilai_pemerolehan' => ['label' => 'Nilai pemerolehan (RM)', 'jenis' => 'wang', 'sumber' => 'pemerolehan.nilai_pemerolehan'],
                ],
            ],
            'risiko' => [
                'label' => 'Risk rating lot', 'paparan' => 'v_risiko_lot', 'modul' => 'ptn', 'kategori' => 'risiko', 'kebenaran' => 'ptn.risiko.lihat',
                'tahap' => ['sulit' => 'pro.sulit.lihat', 'rahsia' => 'pro.rahsia.lihat'], 'pautan' => '/admin/pro/lot/{lot_id}', 'tarikh' => 'tarikh_kiraan', 'jadual_sumber' => 'risk_rating_lot',
                'medan' => [
                    'no_lot' => ['label' => 'No. lot', 'jenis' => 'teks'],
                    ...$c,
                    'kod_warna' => ['label' => 'Kod warna risiko', 'jenis' => 'kod', 'sumber' => 'risk_rating_lot.kod_warna'],
                    'skor_keseluruhan' => ['label' => 'Skor keseluruhan', 'jenis' => 'nombor', 'sumber' => 'risk_rating_lot.skor_keseluruhan'],
                    'skor_pencerobohan' => ['label' => 'Skor pencerobohan', 'jenis' => 'nombor'],
                    'skor_tunggakan_cukai' => ['label' => 'Skor tunggakan cukai', 'jenis' => 'nombor'],
                    'skor_tamat_kontrak' => ['label' => 'Skor tamat kontrak', 'jenis' => 'nombor'],
                    'tarikh_kiraan' => ['label' => 'Tarikh kiraan', 'jenis' => 'tarikh', 'sumber' => 'risk_rating_lot.tarikh_kiraan'],
                ],
            ],
            'analitik' => [
                'label' => 'Hasil analitik MOD-LAP (cadangan sistem)', 'paparan' => 'v_hasil_analitik', 'modul' => 'lap', 'kategori' => 'risiko', 'kebenaran' => 'lap.analitik.lihat',
                'tahap' => ['sulit' => 'pro.sulit.lihat', 'rahsia' => 'pro.rahsia.lihat'], 'pautan' => '/admin/pro/lot/{lot_id}', 'tarikh' => 'tarikh_kira', 'jadual_sumber' => 'lap_hasil_analitik',
                'medan' => [
                    'no_lot' => ['label' => 'No. lot', 'jenis' => 'teks'],
                    ...$c,
                    'jenis_analitik' => ['label' => 'Jenis analitik', 'jenis' => 'kod'],
                    'aras_risiko' => ['label' => 'Aras', 'jenis' => 'kod'],
                    'skor' => ['label' => 'Skor', 'jenis' => 'nombor'],
                    'tarikh_kira' => ['label' => 'Tarikh kira', 'jenis' => 'tarikh'],
                ],
            ],
            'log_laporan' => [
                'label' => 'Jejak penjanaan & eksport laporan', 'paparan' => 'v_log_penjanaan', 'modul' => 'lap', 'kategori' => 'pematuhan', 'kebenaran' => 'lap.log.lihat',
                'tahap' => ['sulit' => 'lap.log.lihat', 'rahsia' => 'aud.laporan.lihat'], 'pautan' => null, 'tarikh' => 'tarikh', 'jadual_sumber' => 'lap_log_penjanaan', 'tanpa_skop_daerah' => true,
                'medan' => [
                    'tajuk' => ['label' => 'Laporan', 'jenis' => 'teks'],
                    'kod_laporan' => ['label' => 'Kod laporan', 'jenis' => 'kod'],
                    'format_eksport' => ['label' => 'Format', 'jenis' => 'kod'],
                    'mod' => ['label' => 'Mod penjanaan', 'jenis' => 'kod'],
                    'status' => ['label' => 'Status', 'jenis' => 'kod'],
                    'nama_pengguna' => ['label' => 'Pengguna', 'jenis' => 'kod'],
                    'bilangan_rekod' => ['label' => 'Bilangan rekod', 'jenis' => 'nombor'],
                    'tarikh' => ['label' => 'Tarikh', 'jenis' => 'tarikh'],
                    'klasifikasi' => $c['klasifikasi'],
                ],
            ],
            'aktiviti_audit' => [
                'label' => 'Aktiviti audit sistem (capaian & penggunaan)', 'paparan' => 'v_aktiviti_audit', 'modul' => 'aud', 'kategori' => 'pematuhan', 'kebenaran' => 'aud.laporan.lihat',
                'tahap' => ['sulit' => 'aud.laporan.lihat', 'rahsia' => 'aud.laporan.lihat'], 'pautan' => null, 'tarikh' => 'tarikh', 'jadual_sumber' => 'audit_rekod', 'tanpa_skop_daerah' => true,
                'medan' => [
                    'modul' => ['label' => 'Modul', 'jenis' => 'kod', 'sumber' => 'audit_rekod.modul'],
                    'jenis_tindakan' => ['label' => 'Tindakan', 'jenis' => 'kod', 'sumber' => 'audit_rekod.jenis_tindakan'],
                    'status_transaksi' => ['label' => 'Status', 'jenis' => 'kod', 'sumber' => 'audit_rekod.status_transaksi'],
                    'nama_entiti' => ['label' => 'Entiti', 'jenis' => 'kod', 'sumber' => 'audit_rekod.nama_entiti'],
                    'saluran_capaian' => ['label' => 'Saluran', 'jenis' => 'kod', 'sumber' => 'audit_rekod.saluran_capaian'],
                    'pengecam_pengguna' => ['label' => 'Pengguna', 'jenis' => 'kod', 'sumber' => 'audit_rekod.pengecam_pengguna'],
                    'tarikh' => ['label' => 'Tarikh', 'jenis' => 'tarikh', 'sumber' => 'audit_rekod.tarikh_masa_tempatan'],
                    'klasifikasi' => $c['klasifikasi'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
