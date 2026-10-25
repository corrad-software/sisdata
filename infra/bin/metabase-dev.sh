#!/usr/bin/env bash
#
# Metabase OSS for SISDATA MOD-LAP (SRS-LAP-01) — native jar, no Docker.
#
# Usage: infra/bin/metabase-dev.sh {install|start|stop|restart|status|logs|setup}
#
#   install  Download the pinned OSS jar into $MB_HOME (needs ~0.7 GB; refuses
#            when less than 2 GB would remain free).
#   start    Run it on :3030 with its application DB in Postgres (metabase_app,
#            created by infra/bin/sisdata-bi-setup.sh).
#   stop     Stop it.
#   status   Running? healthy? version?
#   setup    First-run bootstrap through the API (idempotent): admin user,
#            static embedding on with METABASE_EMBED_SECRET, the SISDATA
#            connection as the read-only role sisdata_bi limited to schema
#            `laporan`, and a sample embeddable question with a LOCKED
#            kod_daerah filter (registered in SISDATA via /admin/lap/bi).
#
# Environment (optional):
#   MB_HOME               (default ~/sisdata-services/metabase)
#   MB_VERSION            (default v0.64.1 — OSS)
#   MB_PORT               (default 3030)
#   JAVA_HOME             (default /opt/homebrew/opt/openjdk@21)
#   METABASE_DB_PASSWORD  app DB password (default metabase_local_pw)
#   METABASE_EMBED_SECRET 64-hex embedding secret — the SAME value goes in SISDATA's .env
#   MB_ADMIN_EMAIL / MB_ADMIN_PASSWORD  (default admin@sisdata.local / Sisdata#Metabase1)
#   MB_SOURCE_DB          SISDATA DB Metabase reads (default myds_laravel)
#   SISDATA_BI_PASSWORD   (default sisdata_bi_local_pw)
#
# Production notes (see docs/dev/lap-bi.md): point MB_SOURCE_* at the READ
# REPLICA; SSO (SAML/JWT) and data sandboxing are Metabase Pro/Enterprise
# features — OSS uses its own logins for analysts and signed embedding for
# everyone else.
set -euo pipefail

MB_HOME="${MB_HOME:-$HOME/sisdata-services/metabase}"
MB_VERSION="${MB_VERSION:-v0.64.1}"
MB_PORT="${MB_PORT:-3030}"
export JAVA_HOME="${JAVA_HOME:-/opt/homebrew/opt/openjdk@21}"
RUN="${SISDATA_SERVICES_HOME:-$HOME/sisdata-services}/run"
LOG="${SISDATA_SERVICES_HOME:-$HOME/sisdata-services}/log"
PID_FILE="$RUN/metabase.pid"
LOG_FILE="$LOG/metabase.log"
JAR="$MB_HOME/metabase.jar"
URL="http://127.0.0.1:${MB_PORT}"
mkdir -p "$RUN" "$LOG" "$MB_HOME"
# Dev convenience: a local secret file (chmod 600) when the env var is unset.
if [[ -z "${METABASE_EMBED_SECRET:-}" && -f "$MB_HOME/embed-secret.txt" ]]; then METABASE_EMBED_SECRET="$(cat "$MB_HOME/embed-secret.txt")"; fi

die() { echo "metabase-dev: $*" >&2; exit 1; }
is_running() { [[ -f "$PID_FILE" ]] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; }
json() { python3 -I -c "import sys, json; d = json.load(sys.stdin); print($1)"; }

install() {
  [[ -f "$JAR" ]] && { echo "already installed: $JAR"; return 0; }
  local free_kb; free_kb=$(df -k "$MB_HOME" | awk 'NR==2 {print $4}')
  (( free_kb > 2900000 )) || die "not enough disk: need ~0.7 GB and must keep 2 GB free ($(( free_kb / 1024 )) MB available)"
  echo "downloading Metabase OSS $MB_VERSION (~700 MB)…"
  curl -fSL -o "$JAR.part" "https://downloads.metabase.com/${MB_VERSION}/metabase.jar"
  mv "$JAR.part" "$JAR"
  echo "installed: $JAR"
}

start() {
  if is_running; then echo "  metabase: already running (pid $(cat "$PID_FILE")) on :$MB_PORT"; return 0; fi
  [[ -f "$JAR" ]] || { echo "  metabase: not installed (run: $0 install)"; return 0; }
  [[ -x "$JAVA_HOME/bin/java" ]] || die "Java not found at $JAVA_HOME"
  (
    cd "$MB_HOME"
    export MB_DB_TYPE=postgres MB_DB_HOST=127.0.0.1 MB_DB_PORT=5432 MB_DB_DBNAME=metabase_app MB_DB_USER=metabase
    export MB_DB_PASS="${METABASE_DB_PASSWORD:-metabase_local_pw}"
    export MB_JETTY_HOST=127.0.0.1 MB_JETTY_PORT="$MB_PORT"
    export MB_ANON_TRACKING_ENABLED=false MB_CHECK_FOR_UPDATES=false MB_SITE_LOCALE=ms
    export MB_ENABLE_EMBEDDING_STATIC=true
    [[ -n "${METABASE_EMBED_SECRET:-}" ]] && export MB_EMBEDDING_SECRET_KEY="$METABASE_EMBED_SECRET"
    nohup "$JAVA_HOME/bin/java" -Xmx1g -XX:+UseG1GC -jar "$JAR" >>"$LOG_FILE" 2>&1 &
    echo $! >"$PID_FILE"
  )
  echo "  metabase: started (pid $(cat "$PID_FILE"), log $LOG_FILE) — first start migrates its DB (1–3 min)"
}

stop() {
  if is_running; then kill "$(cat "$PID_FILE")" && echo "  metabase: stopped"; else echo "  metabase: not running"; fi
  rm -f "$PID_FILE"
}

status() {
  if is_running; then
    local h; h=$(curl -s -m 3 "$URL/api/health" || true)
    echo "  metabase: running (pid $(cat "$PID_FILE")) :$MB_PORT health=${h:-starting}"
  elif [[ -f "$JAR" ]]; then
    echo "  metabase: stopped (installed $JAR)"
  else
    echo "  metabase: not installed"
  fi
}

wait_healthy() {
  for _ in $(seq 1 180); do
    curl -s -m 2 "$URL/api/health" 2>/dev/null | grep -q '"ok"' && return 0
    sleep 2
  done
  die "Metabase not healthy after 6 min (see $LOG_FILE)"
}

setup() {
  wait_healthy
  local email="${MB_ADMIN_EMAIL:-admin@sisdata.local}" pass="${MB_ADMIN_PASSWORD:-Sisdata#Metabase1}"
  local secret="${METABASE_EMBED_SECRET:?set METABASE_EMBED_SECRET (64 hex chars, same as SISDATA .env)}"
  local token; token=$(curl -s "$URL/api/session/properties" | json "d.get('setup-token') or ''")
  if [[ -n "$token" ]] && curl -s "$URL/api/session/properties" | json "d.get('has-user-setup')" | grep -q False; then
    curl -sf -H 'Content-Type: application/json' "$URL/api/setup" -d "{\"token\":\"$token\",\"user\":{\"email\":\"$email\",\"password\":\"$pass\",\"first_name\":\"Pentadbir\",\"last_name\":\"BI\",\"site_name\":\"SISDATA BI\"},\"prefs\":{\"site_name\":\"SISDATA BI\",\"site_locale\":\"ms\",\"allow_tracking\":false}}" >/dev/null
    echo "  admin user created: $email"
  fi
  local sid; sid=$(curl -sf -H 'Content-Type: application/json' "$URL/api/session" -d "{\"username\":\"$email\",\"password\":\"$pass\"}" | json "d['id']")
  local H=(-H "X-Metabase-Session: $sid" -H 'Content-Type: application/json')
  curl -sf "${H[@]}" -X PUT "$URL/api/setting/enable-embedding-static" -d '{"value":true}' >/dev/null
  curl -sf "${H[@]}" -X PUT "$URL/api/setting/embedding-secret-key" -d "{\"value\":\"$secret\"}" >/dev/null
  echo "  static embedding: on (secret set)"

  local src="${MB_SOURCE_DB:-myds_laravel}"
  local dbid; dbid=$(curl -sf "${H[@]}" "$URL/api/database" | json "next((x['id'] for x in d['data'] if x['name']=='SISDATA (laporan)'), '')")
  if [[ -z "$dbid" ]]; then
    dbid=$(curl -sf "${H[@]}" "$URL/api/database" -d "{\"engine\":\"postgres\",\"name\":\"SISDATA (laporan)\",\"details\":{\"host\":\"${MB_SOURCE_HOST:-127.0.0.1}\",\"port\":${MB_SOURCE_PORT:-5432},\"dbname\":\"$src\",\"user\":\"sisdata_bi\",\"password\":\"${SISDATA_BI_PASSWORD:-sisdata_bi_local_pw}\",\"ssl\":false,\"schema-filters-type\":\"inclusion\",\"schema-filters-patterns\":\"laporan\"},\"auto_run_queries\":true,\"is_full_sync\":true}" | json "d['id']")
    echo "  database connection: SISDATA (laporan) id=$dbid → $src as sisdata_bi"
  fi
  curl -sf "${H[@]}" -X POST "$URL/api/database/$dbid/sync_schema" >/dev/null || true
  local fid=""
  for _ in $(seq 1 30); do
    fid=$(curl -sf "${H[@]}" "$URL/api/database/$dbid/metadata" | json "next((f['id'] for t in d['tables'] if t['name']=='bi_portfolio' for f in t['fields'] if f['name']=='kod_daerah'), '')")
    [[ -n "$fid" ]] && break; sleep 2
  done
  [[ -n "$fid" ]] || die "laporan.bi_portfolio not visible yet — has MOD-LAP been migrated on $src?"

  local cid; cid=$(curl -sf "${H[@]}" "$URL/api/card" | json "next((c['id'] for c in d if c['name']=='Portfolio lot mengikut daerah (SISDATA)'), '')")
  if [[ -z "$cid" ]]; then
    local q="SELECT nama_daerah AS daerah, kategori_guna_tanah, sum(bilangan_lot) AS bilangan_lot, sum(jumlah_keluasan) AS jumlah_keluasan FROM laporan.bi_portfolio WHERE {{kod_daerah}} GROUP BY 1, 2 ORDER BY 1, 2"
    cid=$(curl -sf "${H[@]}" "$URL/api/card" -d "{\"name\":\"Portfolio lot mengikut daerah (SISDATA)\",\"display\":\"bar\",\"visualization_settings\":{},\"dataset_query\":{\"type\":\"native\",\"database\":$dbid,\"native\":{\"query\":\"$q\",\"template-tags\":{\"kod_daerah\":{\"id\":\"6f1d0a52-0a3c-4b5e-9d1e-5a2f2b7c0d11\",\"name\":\"kod_daerah\",\"display-name\":\"Daerah\",\"type\":\"dimension\",\"dimension\":[\"field\",$fid,null],\"widget-type\":\"string/=\"}}}}}" | json "d['id']")
    echo "  sample question: id=$cid"
  fi
  curl -sf "${H[@]}" -X PUT "$URL/api/card/$cid" -d '{"enable_embedding":true,"embedding_params":{"kod_daerah":"locked"}}' >/dev/null
  echo "  question $cid embeddable with LOCKED kod_daerah — register it in SISDATA: /admin/lap/bi (jenis question, id_metabase $cid, parameter kod_daerah → daerah)"
}

case "${1:-status}" in
  install) install ;;
  start) start ;;
  stop) stop ;;
  restart) stop; start ;;
  status) status ;;
  logs) tail -f "$LOG_FILE" ;;
  setup) setup ;;
  *) echo "usage: $0 install|start|stop|restart|status|logs|setup" >&2; exit 1 ;;
esac
