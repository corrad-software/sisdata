#!/usr/bin/env bash
# One-off, as a Postgres superuser (your macOS user on Homebrew PG17):
# creates the app + test databases and the extensions the app role can't
# create itself (PostGIS isn't a trusted extension).
set -euo pipefail

APP_ROLE="${DB_USERNAME:-myds}"
for db in myds_laravel myds_laravel_test; do
  psql -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$db'" | grep -q 1 \
    || psql -d postgres -c "CREATE DATABASE $db OWNER $APP_ROLE"
  psql -d "$db" -v ON_ERROR_STOP=1 -c "CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS vector; CREATE EXTENSION IF NOT EXISTS pg_trgm;"
  echo "$db: $(psql -d "$db" -tAc "SELECT string_agg(extname||' '||extversion, ', ') FROM pg_extension")"
done
