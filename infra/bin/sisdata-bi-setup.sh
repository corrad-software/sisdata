#!/usr/bin/env bash
# MOD-LAP BI database roles (SRS-LAP-01/08, SRS-RBC-08). Run once as a
# Postgres SUPERUSER (your macOS user on Homebrew PG17), like
# infra/bin/sisdata-db-setup.sh:
#
#   infra/bin/sisdata-bi-setup.sh [db ...]      (default: myds_laravel myds_laravel_test)
#
# Creates:
#   sisdata_bi   LOGIN, read-only, may read ONLY the aggregate views
#                laporan.bi_* (granted by MOD-LAP's migration whenever it runs;
#                re-granted here for DBs already migrated). Used by Metabase.
#   sisdata_lap  LOGIN, read-only, may read the row-level laporan.v_* views
#                only (no raw tables) — the recommended LAP_DB_READ_USERNAME
#                for the read replica in production.
#   metabase     LOGIN, owner of the Metabase application DB `metabase_app`.
#
# Passwords (dev defaults; set real ones via env in any shared environment):
#   SISDATA_BI_PASSWORD   (default sisdata_bi_local_pw)
#   SISDATA_LAP_PASSWORD  (default sisdata_lap_local_pw)
#   METABASE_DB_PASSWORD  (default metabase_local_pw)
set -euo pipefail

BI_PW="${SISDATA_BI_PASSWORD:-sisdata_bi_local_pw}"
LAP_PW="${SISDATA_LAP_PASSWORD:-sisdata_lap_local_pw}"
MB_PW="${METABASE_DB_PASSWORD:-metabase_local_pw}"
DBS=("$@")
[[ ${#DBS[@]} -eq 0 ]] && DBS=(myds_laravel myds_laravel_test)

role() { # name, password
  psql -d postgres -v ON_ERROR_STOP=1 -q <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '$1') THEN
    CREATE ROLE $1 LOGIN PASSWORD '$2' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT;
  ELSE
    ALTER ROLE $1 LOGIN PASSWORD '$2';
  END IF;
END \$\$;
SQL
  echo "role $1: ok"
}

role sisdata_bi "$BI_PW"
role sisdata_lap "$LAP_PW"
role metabase "$MB_PW"

psql -d postgres -v ON_ERROR_STOP=1 -q <<'SQL'
ALTER ROLE sisdata_bi SET default_transaction_read_only = on;
ALTER ROLE sisdata_bi SET statement_timeout = '60s';
ALTER ROLE sisdata_bi SET search_path = laporan;
ALTER ROLE sisdata_lap SET default_transaction_read_only = on;
ALTER ROLE sisdata_lap SET statement_timeout = '120s';
SQL

psql -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='metabase_app'" | grep -q 1 \
  || psql -d postgres -c "CREATE DATABASE metabase_app OWNER metabase"
echo "database metabase_app: ok"

for db in "${DBS[@]}"; do
  if ! psql -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$db'" | grep -q 1; then
    echo "$db: does not exist, skipped"; continue
  fi
  psql -d "$db" -v ON_ERROR_STOP=1 -q -c "GRANT CONNECT ON DATABASE $db TO sisdata_bi, sisdata_lap"
  psql -d "$db" -v ON_ERROR_STOP=1 -q <<'SQL'
-- No raw-table access for the BI roles (PUBLIC has none on tables by default; be explicit).
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM sisdata_bi, sisdata_lap;
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'laporan') THEN
    GRANT USAGE ON SCHEMA laporan TO sisdata_bi, sisdata_lap;
    EXECUTE (SELECT 'GRANT SELECT ON ' || string_agg('laporan.' || quote_ident(table_name), ', ') || ' TO sisdata_bi'
             FROM information_schema.views WHERE table_schema = 'laporan' AND table_name LIKE 'bi\_%');
    GRANT SELECT ON ALL TABLES IN SCHEMA laporan TO sisdata_lap;
  END IF;
END $$;
SQL
  echo "$db: grants ok ($(psql -d "$db" -tAc "SELECT count(*) FROM information_schema.role_table_grants WHERE grantee='sisdata_bi'") views for sisdata_bi)"
done
