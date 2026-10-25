<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `laporan` reporting schema (SRS-LAP-08, SRS-RBC-08, PLAN §2 RBAC/BI).
 *
 * Every report of MOD-LAP and the BI platform reads THESE views, never the
 * raw tables. Two tiers:
 *
 *  - `laporan.v_*` — row-level views, created WITH (security_barrier) and
 *    filtered inside the view by `laporan.dalam_skop()` against session
 *    settings `sisdata.skop_daerah`, `sisdata.skop_mukim`,
 *    `sisdata.skop_bahagian` and `sisdata.tahap_klasifikasi`. MOD-LAP sets
 *    them with SET LOCAL (set_config(..., true)) from the user's RBAC scope
 *    in the same transaction as the query. Unset = NO rows (deny by default),
 *    so a query that forgets the scope returns nothing rather than everything.
 *    '*' = unrestricted on that dimension.
 *  - `laporan.bi_*` — aggregates without personal data or record
 *    identifiers, the ONLY objects granted to the read-only BI role
 *    `sisdata_bi` (Metabase OSS cannot pass per-user context to Postgres, so
 *    per-user scope there is a signed, locked embedding parameter instead).
 *
 * Grants are applied only when the roles exist (infra/bin/sisdata-bi-setup.sh
 * creates them as superuser); views are recreated on every migrate.
 */
return new class extends Migration
{
    private const DAERAH = '(SELECT d.nama FROM ref_daerah d WHERE d.kod = %s.kod_daerah AND d.sah_hingga IS NULL LIMIT 1)';

    private const MUKIM = '(SELECT m.nama FROM ref_mukim m WHERE m.kod = %s.kod_mukim AND m.sah_hingga IS NULL LIMIT 1)';

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE SCHEMA IF NOT EXISTS laporan;
COMMENT ON SCHEMA laporan IS 'MOD-LAP: paparan pelaporan berskop (row-level security melalui tetapan sesi sisdata.*) dan agregat BI';

CREATE OR REPLACE FUNCTION laporan.dalam_skop(nilai text, kunci text) RETURNS boolean
LANGUAGE sql STABLE PARALLEL SAFE AS $$
    SELECT CASE
        WHEN coalesce(current_setting(kunci, true), '') = '' THEN false
        WHEN current_setting(kunci, true) = '*' THEN true
        ELSE coalesce(nilai = ANY (string_to_array(current_setting(kunci, true), ',')), false)
    END
$$;
COMMENT ON FUNCTION laporan.dalam_skop(text, text) IS 'true jika nilai berada dalam skop sesi (tetapan kosong = tolak, * = semua)';
SQL);

        foreach ($this->views() as $name => $sql) {
            DB::unprepared("DROP VIEW IF EXISTS laporan.{$name} CASCADE");
            $barrier = str_starts_with($name, 'v_') ? ' WITH (security_barrier = true)' : '';
            DB::unprepared("CREATE VIEW laporan.{$name}{$barrier} AS {$sql}");
        }

        $bi = implode(', ', array_map(fn ($v) => "laporan.{$v}", array_values(array_filter(array_keys($this->views()), fn ($v) => str_starts_with($v, 'bi_')))));
        $rows = implode(', ', array_map(fn ($v) => "laporan.{$v}", array_values(array_filter(array_keys($this->views()), fn ($v) => str_starts_with($v, 'v_')))));
        DB::unprepared(<<<SQL
DO \$\$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'sisdata_bi') THEN
        GRANT USAGE ON SCHEMA laporan TO sisdata_bi;
        GRANT SELECT ON {$bi} TO sisdata_bi;
        REVOKE SELECT ON {$rows} FROM sisdata_bi;
    END IF;
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'sisdata_lap') THEN
        GRANT USAGE ON SCHEMA laporan TO sisdata_lap;
        GRANT SELECT ON ALL TABLES IN SCHEMA laporan TO sisdata_lap;
    END IF;
END
\$\$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP SCHEMA IF EXISTS laporan CASCADE');
    }

    /** @param  array<string, string>  $dims  scope type => column, e.g. daerah => x.kod_daerah */
    private function scope(array $dims): string
    {
        $map = ['daerah' => 'sisdata.skop_daerah', 'mukim' => 'sisdata.skop_mukim', 'bahagian' => 'sisdata.skop_bahagian', 'klasifikasi' => 'sisdata.tahap_klasifikasi'];
        $sql = [];
        foreach ($dims as $type => $column) {
            $sql[] = "laporan.dalam_skop({$column}::text, '{$map[$type]}')";
        }

        return implode(' AND ', $sql);
    }

    private function names(string $alias): string
    {
        return sprintf(self::DAERAH, $alias).' AS nama_daerah, '.sprintf(self::MUKIM, $alias).' AS nama_mukim';
    }

    /** @return array<string, string> */
    private function views(): array
    {
        $lotScope = $this->scope(['daerah' => 'l.kod_daerah', 'mukim' => 'l.kod_mukim', 'bahagian' => 'l.bahagian_pemilik', 'klasifikasi' => 'l.klasifikasi']);
        $dmk = fn (string $a) => $this->scope(['daerah' => "{$a}.kod_daerah", 'mukim' => "{$a}.kod_mukim", 'klasifikasi' => "{$a}.klasifikasi"]);
        $umur = "CASE WHEN %1\$s <= 30 THEN '0-30' WHEN %1\$s <= 90 THEN '31-90' WHEN %1\$s <= 180 THEN '91-180' WHEN %1\$s <= 365 THEN '181-365' ELSE '>365' END";

        $bakiCaj = 'greatest(c.amaun - coalesce(c.amaun_dibayar, 0) - coalesce(c.amaun_dikecualikan, 0), 0)';
        $tunggakCaj = "(c.tarikh_akhir < current_date AND {$bakiCaj} > 0 AND c.status_caj NOT IN ('dibatalkan', 'dikecualikan', 'dijadualkan', 'dibayar'))";
        $bakiCukai = 'greatest(x.jumlah + coalesce(x.amaun_denda, 0) - coalesce(x.amaun_dibayar, 0), 0)';
        $tunggakCukai = "(x.tarikh_akhir < current_date AND coalesce(x.status_bayaran, '') <> 'dibayar' AND {$bakiCukai} > 0)";
        $kesSelesai = "('selesai', 'ditutup', 'diputuskan', 'ditarik_balik')";

        return [
            // ---------------------------------------------------- row-level
            'v_lot' => "SELECT l.id, l.no_lot, l.kod_daerah, l.kod_mukim, {$this->names('l')}, l.kategori_guna_tanah, l.zoning, l.status_lot,
                    l.bahagian_pemilik AS bahagian, l.keluasan, l.unit_keluasan, l.klasifikasi, hm.status_semasa AS status_hak_milik, hm.jenis_hak_milik,
                    (SELECT p.nilai_pasaran FROM penilaian p WHERE p.lot_id = l.id AND p.deleted_at IS NULL ORDER BY p.tarikh_penilaian DESC LIMIT 1) AS nilai_pasaran,
                    EXISTS (SELECT 1 FROM kontrak_pajakan k WHERE k.lot_id = l.id AND k.deleted_at IS NULL AND k.status IN ('aktif', 'hampir_tamat')) AS ada_pajakan_aktif,
                    EXISTS (SELECT 1 FROM kes_mahkamah_lot kl JOIN kes_mahkamah km ON km.id = kl.kes_mahkamah_id WHERE kl.lot_id = l.id AND km.deleted_at IS NULL
                        AND coalesce(km.status, '') NOT IN {$kesSelesai}) AS ada_kes_aktif,
                    EXISTS (SELECT 1 FROM isu_pemantauan i WHERE i.lot_id = l.id AND i.status_isu IN ('terbuka', 'dalam_tindakan')
                        AND i.kategori_isu IN ('pencerobohan', 'pendudukan_haram', 'binaan_haram')) AS ada_isu_pencerobohan,
                    EXISTS (SELECT 1 FROM isu_pemantauan i WHERE i.lot_id = l.id AND i.status_isu IN ('terbuka', 'dalam_tindakan') AND i.kategori_isu = 'tanah_terbiar') AS ada_isu_terbiar,
                    lw.tarikh_lawatan AS lawatan_terakhir, lw.keadaan_tapak AS keadaan_tapak_terakhir,
                    CASE lower(coalesce(l.unit_keluasan, 'm2')) WHEN 'hektar' THEN l.keluasan * 10000 WHEN 'ha' THEN l.keluasan * 10000 WHEN 'ekar' THEN l.keluasan * 4046.8564
                        WHEN 'kaki_persegi' THEN l.keluasan * 0.092903 ELSE l.keluasan END AS keluasan_m2,
                    l.created_at::date AS tarikh_daftar, l.updated_at AS dikemas_kini
                FROM lot_tanah l
                LEFT JOIN LATERAL (SELECT h.status_semasa, h.jenis_hak_milik FROM hak_milik h WHERE h.lot_id = l.id AND h.deleted_at IS NULL
                    ORDER BY (h.status_semasa = 'aktif') DESC, h.created_at DESC LIMIT 1) hm ON true
                LEFT JOIN LATERAL (SELECT r.tarikh_lawatan, r.keadaan_tapak FROM rekod_lawatan r WHERE r.lot_id = l.id
                    ORDER BY r.tarikh_lawatan DESC LIMIT 1) lw ON true
                WHERE l.deleted_at IS NULL AND l.status_lot NOT IN ('arkib', 'digabung') AND {$lotScope}",

            'v_hak_milik' => "SELECT h.id, h.lot_id, l.no_lot, h.no_hak_milik, h.jenis_hak_milik, h.kategori_tanah, h.status_semasa, h.tarikh_pendaftaran,
                    h.tarikh_tamat_tempoh, (h.tarikh_tamat_tempoh - current_date) AS baki_hari, h.disahkan, h.keluasan, h.kod_daerah, h.kod_mukim, {$this->names('h')},
                    h.klasifikasi, h.updated_at AS dikemas_kini
                FROM hak_milik h LEFT JOIN lot_tanah l ON l.id = h.lot_id
                WHERE h.deleted_at IS NULL AND {$dmk('h')}",

            'v_kontrak_pajakan' => "SELECT k.id, k.lot_id, l.no_lot, k.no_kontrak, k.status, k.jenis_perjanjian, k.kategori_hartanah, k.tarikh_mula, k.tarikh_tamat,
                    (k.tarikh_tamat - current_date) AS baki_hari, k.kadar, k.nilai_kontrak, k.kitaran_caj, k.kod_daerah, k.kod_mukim, {$this->names('k')},
                    k.bahagian, k.klasifikasi, k.updated_at AS dikemas_kini
                FROM kontrak_pajakan k LEFT JOIN lot_tanah l ON l.id = k.lot_id
                WHERE k.deleted_at IS NULL AND ".$this->scope(['daerah' => 'k.kod_daerah', 'mukim' => 'k.kod_mukim', 'bahagian' => 'k.bahagian', 'klasifikasi' => 'k.klasifikasi']),

            'v_caj_sewaan' => "SELECT c.id, c.kontrak_id, k.no_kontrak, c.no_caj, c.kod_caj, c.tempoh, c.tarikh_caj, c.tarikh_akhir, c.amaun,
                    coalesce(c.amaun_dibayar, 0) AS amaun_dibayar, coalesce(c.amaun_dikecualikan, 0) AS amaun_dikecualikan, {$bakiCaj} AS baki, c.status_caj,
                    {$tunggakCaj} AS tertunggak, CASE WHEN {$tunggakCaj} THEN current_date - c.tarikh_akhir END AS umur_tunggakan_hari,
                    CASE WHEN {$tunggakCaj} THEN ".sprintf($umur, '(current_date - c.tarikh_akhir)')." END AS kumpulan_umur,
                    k.kategori_hartanah, k.kod_daerah, k.kod_mukim, {$this->names('k')}, k.bahagian, k.klasifikasi, c.updated_at AS dikemas_kini
                FROM jadual_caj c JOIN kontrak_pajakan k ON k.id = c.kontrak_id
                WHERE k.deleted_at IS NULL AND c.dikeluarkan = true AND ".$this->scope(['daerah' => 'k.kod_daerah', 'mukim' => 'k.kod_mukim', 'bahagian' => 'k.bahagian', 'klasifikasi' => 'k.klasifikasi']),

            'v_cukai' => "SELECT x.id, x.lot_id, l.no_lot, x.no_bil, x.jenis, x.tahun_taksiran, x.tarikh_bil, x.tarikh_akhir, x.tarikh_bayar, x.jumlah,
                    coalesce(x.amaun_denda, 0) AS amaun_denda, coalesce(x.amaun_dibayar, 0) AS amaun_dibayar, {$bakiCukai} AS baki, x.status_bayaran,
                    {$tunggakCukai} AS tertunggak, CASE WHEN {$tunggakCukai} THEN current_date - x.tarikh_akhir END AS umur_tunggakan_hari,
                    CASE WHEN {$tunggakCukai} THEN ".sprintf($umur, '(current_date - x.tarikh_akhir)')." END AS kumpulan_umur,
                    x.kaedah_bayaran, x.pihak_pengeluar, x.kod_daerah, x.kod_mukim, {$this->names('x')}, x.klasifikasi, x.updated_at AS dikemas_kini
                FROM cukai x LEFT JOIN lot_tanah l ON l.id = x.lot_id
                WHERE x.deleted_at IS NULL AND {$dmk('x')}",

            'v_aduan' => "SELECT a.id, a.lot_id, a.no_rujukan_aduan, a.kategori, a.subkategori, a.status, a.saluran_penerimaan, a.tahap_keutamaan,
                    coalesce(a.tarikh_terima::date, a.tarikh_aduan) AS tarikh_aduan, a.tarikh_tutup::date AS tarikh_tutup,
                    (a.tarikh_tutup::date - coalesce(a.tarikh_terima::date, a.tarikh_aduan)) AS tempoh_selesai_hari,
                    (a.status NOT IN ('ditutup', 'pendua')) AS terbuka, a.status_bukan_tanah_mais AS bukan_tanah_mais,
                    a.kod_daerah, a.kod_mukim, {$this->names('a')}, a.klasifikasi, a.updated_at AS dikemas_kini
                FROM aduan a WHERE a.deleted_at IS NULL AND {$dmk('a')}",

            'v_isu_pemantauan' => "SELECT i.id, i.lot_id, l.no_lot, i.no_rujukan, i.kategori_isu, i.tahap_keterukan, i.status_isu, i.tarikh_dibuka, i.tarikh_ditutup,
                    (coalesce(i.tarikh_ditutup, current_date) - i.tarikh_dibuka) AS tempoh_hari, (i.status_isu IN ('terbuka', 'dalam_tindakan')) AS terbuka,
                    i.kod_daerah, i.kod_mukim, {$this->names('i')}, i.klasifikasi, i.updated_at AS dikemas_kini
                FROM isu_pemantauan i LEFT JOIN lot_tanah l ON l.id = i.lot_id WHERE {$dmk('i')}",

            'v_tugasan' => 'SELECT t.id, w.modul, w.kod_aliran, t.tajuk, t.status, t.created_at::date AS tarikh_cipta, t.tarikh_akhir_sla, t.diselesaikan_pada,
                    (t.diselesaikan_pada IS NOT NULL) AS selesai,
                    (CASE WHEN t.tarikh_akhir_sla IS NULL THEN true WHEN t.diselesaikan_pada IS NOT NULL THEN t.diselesaikan_pada <= t.tarikh_akhir_sla ELSE now() <= t.tarikh_akhir_sla END) AS dalam_sla,
                    (t.diselesaikan_pada IS NULL AND t.tarikh_akhir_sla < now()) AS lewat_terbuka, coalesce(t.aras_eskalasi, 0) AS aras_eskalasi,
                    w.kod_daerah, '.sprintf(self::DAERAH, 'w').' AS nama_daerah, w.pautan, t.updated_at AS dikemas_kini
                FROM wf_tugasan t JOIN wf_instans w ON w.id = t.id_instans
                WHERE '.$this->scope(['daerah' => 'w.kod_daerah']),

            'v_kes_mahkamah' => "SELECT k.id, k.no_kes, k.no_rujukan, k.jenis, k.status, k.peringkat_mahkamah, k.mahkamah, k.peranan_mais, k.tarikh_pemfailan,
                    k.tarikh_keputusan, k.tarikh_prosiding_berikutnya, k.nilai_tuntutan,
                    coalesce((SELECT sum(g.amaun) FROM kos_guaman g WHERE g.kes_mahkamah_id = k.id), 0) AS kos_guaman,
                    (coalesce(k.status, '') NOT IN {$kesSelesai}) AS aktif, k.kod_daerah, k.kod_mukim, {$this->names('k')}, k.klasifikasi, k.updated_at AS dikemas_kini
                FROM kes_mahkamah k WHERE k.deleted_at IS NULL AND coalesce(k.status_arkib, false) = false AND {$dmk('k')}",

            'v_aset' => "SELECT a.id, a.lot_id, a.no_rujukan_aset, a.nama_aset, a.jenis_aset, a.status_aset, a.status_kondisi, a.tahun_perolehan, a.kos_perolehan,
                    a.nilai_semasa, a.tarikh_pemeriksaan_terakhir, (current_date - a.tarikh_pemeriksaan_terakhir) AS hari_sejak_pemeriksaan,
                    a.kod_daerah, a.kod_mukim, {$this->names('a')}, a.klasifikasi, a.updated_at AS dikemas_kini
                FROM aset_fizikal a WHERE coalesce(a.status_arkib, false) = false AND {$dmk('a')}",

            'v_penyelenggaraan' => "SELECT r.id, r.id_aset, a.no_rujukan_aset, r.no_rujukan, r.jenis_kerja, r.jenis_penyelenggaraan, r.keutamaan, r.status,
                    r.tarikh_dijadual, r.tarikh_siap, r.kos_anggaran, coalesce(r.kos, 0) AS kos, r.kod_daerah, r.kod_mukim, {$this->names('r')}, r.klasifikasi,
                    r.updated_at AS dikemas_kini
                FROM rekod_penyelenggaraan r LEFT JOIN aset_fizikal a ON a.id = r.id_aset WHERE {$dmk('r')}",

            'v_pemerolehan' => "SELECT p.id, p.no_kes, p.tajuk, p.id_saluran, p.status_pemerolehan, p.peringkat_semasa, p.tarikh_mula_kes, p.tarikh_selesai,
                    coalesce(p.tempoh_keseluruhan_hari, (coalesce(p.tarikh_selesai, current_date) - p.tarikh_mula_kes)) AS tempoh_hari, p.tempoh_sasaran_hari,
                    (coalesce(p.tempoh_keseluruhan_hari, (coalesce(p.tarikh_selesai, current_date) - p.tarikh_mula_kes)) > p.tempoh_sasaran_hari) AS melebihi_sasaran,
                    p.nilai_pemerolehan, p.kod_bahagian AS bahagian, p.kod_daerah, p.kod_mukim, {$this->names('p')}, p.klasifikasi, p.updated_at AS dikemas_kini
                FROM pemerolehan p WHERE p.deleted_at IS NULL AND ".$this->scope(['daerah' => 'p.kod_daerah', 'mukim' => 'p.kod_mukim', 'bahagian' => 'p.kod_bahagian', 'klasifikasi' => 'p.klasifikasi']),

            'v_risiko_lot' => "SELECT r.id, r.lot_id, l.no_lot, r.skor_pencerobohan, r.skor_tunggakan_cukai, r.skor_pertikaian, r.skor_dokumen, r.skor_tamat_kontrak,
                    r.skor_potensi_hasil, r.skor_keseluruhan, r.kod_warna, r.tarikh_kiraan, r.kod_daerah, r.kod_mukim, {$this->names('r')}, r.klasifikasi,
                    r.updated_at AS dikemas_kini
                FROM risk_rating_lot r LEFT JOIN lot_tanah l ON l.id = r.lot_id WHERE r.semasa = true AND {$dmk('r')}",

            'v_hasil_analitik' => "SELECT h.id, h.id_lot AS lot_id, h.no_lot, h.jenis_analitik, h.skor, h.aras_risiko, h.tarikh_kira, h.versi_model,
                    h.kod_daerah, h.kod_mukim, {$this->names('h')}, h.klasifikasi, h.updated_at AS dikemas_kini
                FROM lap_hasil_analitik h WHERE h.semasa = true AND {$dmk('h')}",

            // Governance: whole-system logs, guarded by permission (no district dimension).
            'v_log_penjanaan' => "SELECT g.id, g.tajuk, g.kod_laporan, g.set_data, g.format_eksport, g.mod, g.status, g.bilangan_rekod, g.cap_masa,
                    g.cap_masa::date AS tarikh, g.id_pengguna, u.name AS nama_pengguna, g.klasifikasi, g.updated_at AS dikemas_kini
                FROM lap_log_penjanaan g LEFT JOIN users u ON u.id = g.id_pengguna
                WHERE laporan.dalam_skop(g.klasifikasi, 'sisdata.tahap_klasifikasi')",

            'v_aktiviti_audit' => "SELECT r.id, r.tarikh_masa_tempatan AS tarikh_masa, r.tarikh_masa_tempatan::date AS tarikh, r.modul, r.jenis_tindakan, r.status_transaksi,
                    r.nama_entiti, r.saluran_capaian, r.pengecam_pengguna, coalesce(r.klasifikasi, 'dalaman') AS klasifikasi, r.tarikh_masa_utc AS dikemas_kini
                FROM audit_rekod r WHERE laporan.dalam_skop(coalesce(r.klasifikasi, 'dalaman'), 'sisdata.tahap_klasifikasi')",

            // ---------------------------------------------------- BI aggregates (no record ids, no personal data)
            'bi_portfolio' => 'SELECT l.kod_daerah, '.sprintf(self::DAERAH, 'l')." AS nama_daerah, l.kategori_guna_tanah, l.status_lot, l.bahagian_pemilik AS bahagian,
                    count(*) AS bilangan_lot, coalesce(sum(l.keluasan), 0) AS jumlah_keluasan
                FROM lot_tanah l WHERE l.deleted_at IS NULL AND l.status_lot NOT IN ('arkib', 'digabung') AND l.klasifikasi IN ('awam', 'dalaman')
                GROUP BY l.kod_daerah, l.kategori_guna_tanah, l.status_lot, l.bahagian_pemilik",

            'bi_hasil_sewaan_bulanan' => 'SELECT k.kod_daerah, '.sprintf(self::DAERAH, 'k')." AS nama_daerah, date_trunc('month', c.tarikh_caj)::date AS bulan,
                    count(*) AS bilangan_caj, sum(c.amaun) AS jumlah_caj, sum(coalesce(c.amaun_dibayar, 0)) AS jumlah_dibayar
                FROM jadual_caj c JOIN kontrak_pajakan k ON k.id = c.kontrak_id
                WHERE k.deleted_at IS NULL AND c.dikeluarkan = true AND c.kod_caj <> 'deposit'
                GROUP BY k.kod_daerah, date_trunc('month', c.tarikh_caj)",

            'bi_tunggakan' => 'SELECT k.kod_daerah, '.sprintf(self::DAERAH, 'k')." AS nama_daerah, 'sewaan'::text AS sumber,
                    ".sprintf($umur, '(current_date - c.tarikh_akhir)')." AS kumpulan_umur, count(*) AS bilangan, sum({$bakiCaj}) AS jumlah_baki
                FROM jadual_caj c JOIN kontrak_pajakan k ON k.id = c.kontrak_id
                WHERE k.deleted_at IS NULL AND c.dikeluarkan = true AND {$tunggakCaj} GROUP BY 1, 2, 3, 4
                UNION ALL
                SELECT x.kod_daerah, ".sprintf(self::DAERAH, 'x')." AS nama_daerah, 'cukai'::text, ".sprintf($umur, '(current_date - x.tarikh_akhir)').", count(*), sum({$bakiCukai})
                FROM cukai x WHERE x.deleted_at IS NULL AND {$tunggakCukai} GROUP BY 1, 2, 3, 4",

            'bi_aduan_bulanan' => 'SELECT a.kod_daerah, '.sprintf(self::DAERAH, 'a')." AS nama_daerah, date_trunc('month', coalesce(a.tarikh_terima::date, a.tarikh_aduan))::date AS bulan,
                    a.kategori, CASE WHEN a.status IN ('ditutup', 'pendua') THEN 'ditutup' ELSE 'terbuka' END AS status_kumpulan, count(*) AS bilangan
                FROM aduan a WHERE a.deleted_at IS NULL GROUP BY 1, 2, 3, 4, 5",

            'bi_sla_tugasan' => 'SELECT w.modul, w.kod_daerah, '.sprintf(self::DAERAH, 'w')." AS nama_daerah, date_trunc('month', t.created_at)::date AS bulan, count(*) AS jumlah,
                    count(*) FILTER (WHERE t.diselesaikan_pada IS NOT NULL AND (t.tarikh_akhir_sla IS NULL OR t.diselesaikan_pada <= t.tarikh_akhir_sla)) AS selesai_dalam_sla,
                    count(*) FILTER (WHERE (t.diselesaikan_pada IS NULL AND t.tarikh_akhir_sla < now()) OR t.diselesaikan_pada > t.tarikh_akhir_sla) AS lewat_sla
                FROM wf_tugasan t JOIN wf_instans w ON w.id = t.id_instans GROUP BY 1, 2, 3, 4",

            'bi_aset_kondisi' => 'SELECT a.kod_daerah, '.sprintf(self::DAERAH, 'a').' AS nama_daerah, a.jenis_aset, a.status_kondisi, count(*) AS bilangan,
                    coalesce(sum(a.nilai_semasa), 0) AS jumlah_nilai_semasa
                FROM aset_fizikal a WHERE coalesce(a.status_arkib, false) = false GROUP BY 1, 2, 3, 4',
        ];
    }
};
