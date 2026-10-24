<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SRS-HKM-09 / kawalan keselamatan: status history, ownership chronology and
 * title versions are append-only at the database level — same approach as
 * MOD-AUD (SRS-AUD-06): triggers reject UPDATE, DELETE and TRUNCATE for every
 * role. Plus the reporting views named in the SRS entity list.
 */
return new class extends Migration
{
    private const TABLES = ['sejarah_status_tanah', 'kronologi_pemilikan', 'hak_milik_versi'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION hkm_tolak_ubah() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Sejarah hak milik hanya-tambah: % ke atas % ditolak', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;
        SQL);

        foreach (self::TABLES as $table) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$table}_hanya_tambah BEFORE UPDATE OR DELETE ON {$table}
                    FOR EACH ROW EXECUTE FUNCTION hkm_tolak_ubah();
                CREATE TRIGGER {$table}_tiada_truncate BEFORE TRUNCATE ON {$table}
                    FOR EACH STATEMENT EXECUTE FUNCTION hkm_tolak_ubah();
                REVOKE UPDATE, DELETE, TRUNCATE ON {$table} FROM PUBLIC;
            SQL);
        }

        // SRS-HKM-04 inventory, SRS-HKM-07 overdue, SRS-HKM-17 expiring / unverified.
        DB::unprepared(<<<'SQL'
            CREATE VIEW vw_daftar_peti_besi_inventori AS
            SELECT d.id AS id_daftar, p.id AS id_peti_besi, p.kod_peti_besi, p.nama AS nama_peti_besi,
                   p.lokasi_bangunan, p.tingkat, p.bilik, d.lokasi_rak, d.no_fail, d.no_kotak_pengikat,
                   d.jenis_dokumen_asal, d.status_dokumen_asal, d.tarikh_masuk_simpanan, d.catatan_lokasi,
                   h.id AS id_hak_milik, h.no_hak_milik, h.jenis_hak_milik, h.kod_mukim, h.kod_daerah,
                   l.id AS id_lot, l.no_lot
            FROM daftar_peti_besi d
            JOIN peti_besi p ON p.id = d.peti_besi_id
            JOIN hak_milik h ON h.id = d.hak_milik_id AND h.deleted_at IS NULL
            JOIN lot_tanah l ON l.id = d.lot_id;

            CREATE VIEW vw_dokumen_asal_belum_dipulangkan AS
            SELECT e.id AS id_pengeluaran, e.daftar_peti_besi_id AS id_daftar, e.tarikh_pengeluaran, e.penerima_nama,
                   e.penerima_jawatan, e.tujuan_pengeluaran, e.tarikh_jangka_pulang, e.id_pegawai_keluar,
                   (CURRENT_DATE - e.tarikh_jangka_pulang) AS hari_lewat, e.tarikh_jangka_pulang < CURRENT_DATE AS lewat,
                   d.peti_besi_id, p.kod_peti_besi, p.id_pegawai_penjaga, h.id AS id_hak_milik, h.no_hak_milik,
                   h.kod_daerah, h.kod_mukim
            FROM pengeluaran_dokumen e
            JOIN daftar_peti_besi d ON d.id = e.daftar_peti_besi_id
            JOIN peti_besi p ON p.id = d.peti_besi_id
            JOIN hak_milik h ON h.id = d.hak_milik_id AND h.deleted_at IS NULL
            WHERE e.status_pengeluaran = 'dikeluarkan';

            CREATE VIEW vw_hak_milik_hampir_tamat_tempoh AS
            SELECT h.id AS id_hak_milik, h.no_hak_milik, h.jenis_hak_milik, h.lot_id, h.kod_daerah, h.kod_mukim,
                   h.tarikh_tamat_tempoh, (h.tarikh_tamat_tempoh - CURRENT_DATE) AS baki_hari
            FROM hak_milik h
            WHERE h.deleted_at IS NULL AND h.tarikh_tamat_tempoh IS NOT NULL
              AND h.status_semasa NOT IN ('diganti', 'dibatalkan', 'dilupuskan');

            CREATE VIEW vw_lot_tanpa_hak_milik_disahkan AS
            SELECT l.id AS id_lot, l.no_lot, l.kod_mukim, l.kod_daerah, l.created_at,
                   (CURRENT_DATE - l.created_at::date) AS umur_hari
            FROM lot_tanah l
            WHERE l.deleted_at IS NULL
              AND NOT EXISTS (SELECT 1 FROM hak_milik h WHERE h.lot_id = l.id AND h.deleted_at IS NULL AND h.disahkan);
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP VIEW IF EXISTS vw_lot_tanpa_hak_milik_disahkan, vw_hak_milik_hampir_tamat_tempoh, vw_dokumen_asal_belum_dipulangkan, vw_daftar_peti_besi_inventori');
        foreach (self::TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_hanya_tambah ON {$table}; DROP TRIGGER IF EXISTS {$table}_tiada_truncate ON {$table};");
        }
        DB::unprepared('DROP FUNCTION IF EXISTS hkm_tolak_ubah()');
    }
};
