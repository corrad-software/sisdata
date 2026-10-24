-- MOD-AUD · production DB roles (SRS-AUD-08, Kriteria penerimaan 2)
--
-- Locally the app connects as `myds`, which also OWNS the audit tables. An owner
-- can always re-grant itself privileges and disable triggers, so locally the
-- guarantees are: triggers reject UPDATE/DELETE/TRUNCATE for every role,
-- UPDATE/TRUNCATE privileges are revoked, and any tampering by the owner is
-- detected by the hash-chain check (aud:semak-integriti).
--
-- In production separate the roles so the application account physically
-- lacks UPDATE/DELETE:
--
--   sisdata_owner  owns the schema objects; used ONLY by the deploy pipeline
--                  to run migrations (php artisan migrate with DB_USERNAME=sisdata_owner
--                  and AUD_DB_APP_ROLE=sisdata_app).
--   sisdata_app    the role the running application connects as.
--
-- Run once as a superuser / DBA:

CREATE ROLE sisdata_owner LOGIN PASSWORD :'owner_pw';
CREATE ROLE sisdata_app   LOGIN PASSWORD :'app_pw';
GRANT CONNECT ON DATABASE sisdata TO sisdata_app;
GRANT USAGE ON SCHEMA public TO sisdata_app;

-- Every other table: normal CRUD for the app (adjust per module as needed).
ALTER DEFAULT PRIVILEGES FOR ROLE sisdata_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO sisdata_app;
ALTER DEFAULT PRIVILEGES FOR ROLE sisdata_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO sisdata_app;

-- After `php artisan migrate` (as sisdata_owner) the AUD migration
-- 2026_10_24_190100_make_audit_tables_append_only applies the following when
-- AUD_DB_APP_ROLE=sisdata_app; shown here so a DBA can re-apply / verify:

REVOKE ALL ON audit_rekod, audit_perubahan_medan, audit_eksport, audit_integriti_semakan, audit_arkib FROM sisdata_app;
GRANT SELECT, INSERT ON audit_rekod, audit_perubahan_medan, audit_eksport, audit_integriti_semakan, audit_arkib TO sisdata_app;
GRANT USAGE ON SEQUENCE audit_rekod_id_seq, audit_perubahan_medan_id_seq, audit_eksport_id_seq, audit_integriti_semakan_id_seq TO sisdata_app;
-- Archiving is the only path that removes online rows; it runs as the owner
-- (SECURITY DEFINER) and only moves rows > 24 months old with an identical copy.
GRANT EXECUTE ON FUNCTION aud_arkib_pindah(timestamptz, text) TO sisdata_app;

-- Verify (must all be false):
SELECT has_table_privilege('sisdata_app', 'audit_rekod', 'UPDATE')   AS boleh_update,
       has_table_privilege('sisdata_app', 'audit_rekod', 'DELETE')   AS boleh_delete,
       has_table_privilege('sisdata_app', 'audit_rekod', 'TRUNCATE') AS boleh_truncate;

-- Optional hardening: let a separate read-only role (SIEM collector, external
-- auditor's reporting tool) read the trail without going through the app.
-- CREATE ROLE sisdata_audit_ro LOGIN PASSWORD :'ro_pw';
-- GRANT SELECT ON audit_rekod, audit_perubahan_medan, audit_arkib, audit_eksport, audit_integriti_semakan TO sisdata_audit_ro;
