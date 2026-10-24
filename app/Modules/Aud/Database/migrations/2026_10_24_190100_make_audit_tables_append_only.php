<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SRS-AUD-06 / SRS-AUD-08: append-only at the database level.
 *
 * 1. Triggers reject UPDATE, DELETE and TRUNCATE on every audit table, for
 *    every role (including the owner) — unless the trigger is disabled, which
 *    needs table ownership and is caught by the hash-chain check (AUD-07).
 * 2. The only DELETE ever allowed is the archive move done by the SECURITY
 *    DEFINER function aud_arkib_pindah(): rows older than 24 months that
 *    already have an identical copy (same id + hash) in audit_arkib.
 * 3. Privileges: UPDATE/TRUNCATE are revoked from the role running the
 *    migration and from PUBLIC. Locally that role (myds) also OWNS the tables,
 *    and an owner can always re-grant itself, so locally (2)+(1) are what
 *    really holds. In production run the tables under a separate owner role
 *    and give the app role only SELECT/INSERT + EXECUTE on the archive
 *    function — see Database/sql/peranan-pengeluaran.sql. If AUD_DB_APP_ROLE
 *    names a different role, this migration applies those grants to it.
 */
return new class extends Migration
{
    private const TABLES = ['audit_rekod', 'audit_perubahan_medan', 'audit_eksport', 'audit_integriti_semakan', 'audit_arkib'];

    private const COLUMNS = 'id, no_urutan, cincangan_sebelum, cincangan_rekod, tarikh_masa_utc, tarikh_masa_tempatan, id_pengguna, pengecam_pengguna, peranan_semasa, id_sesi, alamat_ip, user_agent, jenis_peranti, lokasi_anggaran, saluran_capaian, modul, nama_entiti, id_rekod, jenis_tindakan, status_transaksi, sebab_perubahan, id_korelasi, klasifikasi, meta';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION aud_tolak_ubah() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                -- Archive move (aud_arkib_pindah) only: the row must be old enough
                -- and already copied, byte-identical hash, into audit_arkib.
                IF TG_OP = 'DELETE' AND current_setting('sisdata.aud_arkib', true) = 'on' THEN
                    -- Nested IFs: plpgsql resolves every OLD.<field> in an expression,
                    -- so table-specific fields must not share one condition.
                    IF TG_TABLE_NAME = 'audit_rekod' THEN
                        IF OLD.tarikh_masa_utc < clock_timestamp() - interval '24 months'
                           AND EXISTS (SELECT 1 FROM audit_arkib a WHERE a.id = OLD.id AND a.cincangan_rekod = OLD.cincangan_rekod) THEN
                            RETURN OLD;
                        END IF;
                    ELSIF TG_TABLE_NAME = 'audit_perubahan_medan' THEN
                        IF EXISTS (SELECT 1 FROM audit_arkib a WHERE a.id = OLD.id_audit) THEN
                            RETURN OLD;
                        END IF;
                    END IF;
                END IF;

                RAISE EXCEPTION 'Rekod audit hanya-tambah: % ke atas % ditolak', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;
        SQL);

        foreach (self::TABLES as $table) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$table}_hanya_tambah BEFORE UPDATE OR DELETE ON {$table}
                    FOR EACH ROW EXECUTE FUNCTION aud_tolak_ubah();
                CREATE TRIGGER {$table}_tiada_truncate BEFORE TRUNCATE ON {$table}
                    FOR EACH STATEMENT EXECUTE FUNCTION aud_tolak_ubah();
            SQL);
        }

        $columns = self::COLUMNS;
        $prefixed = implode(', ', array_map(fn ($c) => 'r.'.trim($c), explode(',', $columns)));

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION aud_arkib_pindah(p_had timestamptz, p_kelompok text) RETURNS integer
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp AS \$\$
            DECLARE
                v_jumlah integer;
            BEGIN
                -- SRS-AUD-20 floor: never archive anything younger than 24 months.
                IF p_had > clock_timestamp() - interval '24 months' THEN
                    RAISE EXCEPTION 'Had arkib % kurang daripada tempoh minimum 24 bulan', p_had;
                END IF;

                PERFORM pg_advisory_xact_lock(7319019);
                PERFORM set_config('sisdata.aud_arkib', 'on', true);

                INSERT INTO audit_arkib ({$columns}, perubahan, diarkib_pada, id_kelompok_arkib)
                SELECT {$prefixed},
                       (SELECT json_agg(json_build_object('nama_medan', p.nama_medan, 'nilai_asal', p.nilai_asal,
                                'nilai_baharu', p.nilai_baharu, 'sensitif_ditapis', p.sensitif_ditapis) ORDER BY p.id)
                          FROM audit_perubahan_medan p WHERE p.id_audit = r.id),
                       now(), p_kelompok
                  FROM audit_rekod r
                 WHERE r.tarikh_masa_utc < p_had
                 ORDER BY r.no_urutan;
                GET DIAGNOSTICS v_jumlah = ROW_COUNT;

                DELETE FROM audit_perubahan_medan p USING audit_rekod r
                 WHERE p.id_audit = r.id AND r.tarikh_masa_utc < p_had;
                DELETE FROM audit_rekod r WHERE r.tarikh_masa_utc < p_had;

                PERFORM set_config('sisdata.aud_arkib', 'off', true);

                RETURN v_jumlah;
            END;
            \$\$;
        SQL);

        $list = implode(', ', self::TABLES);
        DB::unprepared("REVOKE ALL ON {$list} FROM PUBLIC");
        DB::unprepared("REVOKE UPDATE, TRUNCATE ON {$list} FROM CURRENT_USER");
        DB::unprepared('REVOKE DELETE ON audit_eksport, audit_integriti_semakan, audit_arkib FROM CURRENT_USER');
        DB::unprepared('REVOKE ALL ON FUNCTION aud_arkib_pindah(timestamptz, text) FROM PUBLIC');

        $appRole = config('aud.db_app_role');
        $current = DB::selectOne('select current_user as u')->u;
        if ($appRole && $appRole !== $current && preg_match('/^[a-z_][a-z0-9_]*$/', $appRole)) {
            DB::unprepared("REVOKE ALL ON {$list} FROM {$appRole}");
            DB::unprepared("GRANT SELECT, INSERT ON {$list} TO {$appRole}");
            DB::unprepared("GRANT USAGE ON SEQUENCE audit_rekod_id_seq, audit_perubahan_medan_id_seq, audit_eksport_id_seq, audit_integriti_semakan_id_seq TO {$appRole}");
            DB::unprepared("GRANT EXECUTE ON FUNCTION aud_arkib_pindah(timestamptz, text) TO {$appRole}");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $list = implode(', ', self::TABLES);
        DB::unprepared("GRANT UPDATE, DELETE, TRUNCATE ON {$list} TO CURRENT_USER");
        foreach (self::TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_hanya_tambah ON {$table}; DROP TRIGGER IF EXISTS {$table}_tiada_truncate ON {$table};");
        }
        DB::unprepared('DROP FUNCTION IF EXISTS aud_arkib_pindah(timestamptz, text)');
        DB::unprepared('DROP FUNCTION IF EXISTS aud_tolak_ubah()');
    }
};
