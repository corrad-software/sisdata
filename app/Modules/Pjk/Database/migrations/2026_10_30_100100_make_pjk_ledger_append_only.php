<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SRS-PJK-11/17/28 + kawalan keselamatan: the ledger, status history and
 * contract versions are append-only at the database level (same approach as
 * MOD-AUD / MOD-HKM). Receipts can never be changed or deleted — the only
 * permitted update is attaching the DMS copy once. Reporting views follow
 * (SRS-DAT-07).
 */
return new class extends Migration
{
    private const TABLES = ['lejar_transaksi', 'sejarah_status_kontrak', 'sejarah_status_premis', 'versi_kontrak'];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION pjk_tolak_ubah() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Rekod kewangan PJK hanya-tambah: % ke atas % ditolak', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;

            CREATE OR REPLACE FUNCTION pjk_resit_kunci() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                   AND OLD.id_dokumen IS NULL
                   AND NEW.no_resit = OLD.no_resit AND NEW.jenis = OLD.jenis AND NEW.bayaran_id = OLD.bayaran_id
                   AND NEW.kontrak_id = OLD.kontrak_id AND NEW.amaun = OLD.amaun AND NEW.tarikh = OLD.tarikh
                   AND NEW.id_resit_asal IS NOT DISTINCT FROM OLD.id_resit_asal
                   AND NEW.emel_penerima IS NOT DISTINCT FROM OLD.emel_penerima THEN
                    RETURN NEW;
                END IF;
                RAISE EXCEPTION 'Resit rasmi tidak boleh diubah atau dipadam (%); gunakan resit kredit', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;

            CREATE TRIGGER resit_kunci BEFORE UPDATE OR DELETE ON resit FOR EACH ROW EXECUTE FUNCTION pjk_resit_kunci();
            CREATE TRIGGER resit_tiada_truncate BEFORE TRUNCATE ON resit FOR EACH STATEMENT EXECUTE FUNCTION pjk_tolak_ubah();
        SQL);

        foreach (self::TABLES as $table) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$table}_hanya_tambah BEFORE UPDATE OR DELETE ON {$table}
                    FOR EACH ROW EXECUTE FUNCTION pjk_tolak_ubah();
                CREATE TRIGGER {$table}_tiada_truncate BEFORE TRUNCATE ON {$table}
                    FOR EACH STATEMENT EXECUTE FUNCTION pjk_tolak_ubah();
                REVOKE UPDATE, DELETE, TRUNCATE ON {$table} FROM PUBLIC;
            SQL);
        }

        DB::unprepared(<<<'SQL'
            CREATE VIEW vw_pjk_tunggakan_umur AS
            SELECT c.id AS id_caj, c.kontrak_id, k.no_kontrak, k.kod_daerah, k.penyewa_id, c.kod_caj, c.tempoh, c.tarikh_akhir,
                   (c.amaun - c.amaun_dibayar - c.amaun_dikecualikan) AS baki,
                   (CURRENT_DATE - c.tarikh_akhir) AS umur_tunggakan,
                   CASE WHEN CURRENT_DATE - c.tarikh_akhir <= 30 THEN '1_30'
                        WHEN CURRENT_DATE - c.tarikh_akhir <= 60 THEN '31_60'
                        WHEN CURRENT_DATE - c.tarikh_akhir <= 90 THEN '61_90'
                        ELSE 'lebih_90' END AS kumpulan_umur
            FROM jadual_caj c
            JOIN kontrak_pajakan k ON k.id = c.kontrak_id AND k.deleted_at IS NULL
            WHERE c.dikeluarkan AND c.status_caj IN ('belum_bayar', 'separa') AND c.tarikh_akhir < CURRENT_DATE
              AND (c.amaun - c.amaun_dibayar - c.amaun_dikecualikan) > 0;

            CREATE VIEW vw_pjk_penghunian AS
            SELECT p.kod_daerah, p.kategori_hartanah,
                   count(*) AS jumlah_premis,
                   count(*) FILTER (WHERE p.status_premis = 'disewa') AS disewa,
                   count(*) FILTER (WHERE p.status_premis = 'kosong') AS kosong
            FROM premis_aset p WHERE p.deleted_at IS NULL
            GROUP BY p.kod_daerah, p.kategori_hartanah;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS vw_pjk_penghunian; DROP VIEW IF EXISTS vw_pjk_tunggakan_umur;');
        foreach (self::TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_hanya_tambah ON {$table}; DROP TRIGGER IF EXISTS {$table}_tiada_truncate ON {$table};");
        }
        DB::unprepared('DROP TRIGGER IF EXISTS resit_kunci ON resit; DROP TRIGGER IF EXISTS resit_tiada_truncate ON resit; DROP FUNCTION IF EXISTS pjk_resit_kunci(); DROP FUNCTION IF EXISTS pjk_tolak_ubah();');
    }
};
