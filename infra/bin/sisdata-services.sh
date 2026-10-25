#!/usr/bin/env bash
# Local SISDATA backing services, native (Homebrew) — no Docker.
#
#   infra/bin/sisdata-services.sh start|stop|status
#
# brew services: postgresql@17, redis, mailpit
# self-managed (pid files under ~/sisdata-services/run): minio, geoserver, clamd, ollama
# (ollama: LOCAL models only, loopback :11434 — MOD-KBA embeddings/answers, SRS-KBA-15)
# (ClamAV config: /opt/homebrew/etc/clamav/clamd.conf; signatures: run `freshclam`)
# keycloak has its own script: infra/bin/keycloak-dev.sh (called from here)
# metabase (MOD-LAP BI, :3030) has its own script: infra/bin/metabase-dev.sh (called from here;
#   install once with `infra/bin/metabase-dev.sh install`, roles/app DB via infra/bin/sisdata-bi-setup.sh)
# pws-wordpress (Portal Hartanah Wakaf Selangor, MOD-PWS — a SEPARATE public system):
#   WordPress in ~/sisdata-services/pws-wordpress served by `php -S 127.0.0.1:8090`
#   (MySQL from Homebrew). Install once with infra/bin/pws-setup.sh; started here only if installed.
#   Also: infra/bin/sisdata-services.sh pws start|stop|status
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SVC_HOME="${SISDATA_SERVICES_HOME:-$HOME/sisdata-services}"
RUN="$SVC_HOME/run"
LOG="$SVC_HOME/log"
mkdir -p "$RUN" "$LOG"

BREW_SERVICES=(postgresql@17 redis mailpit)

MINIO_DATA="$SVC_HOME/minio-data"
export MINIO_ROOT_USER="${MINIO_ROOT_USER:-sisdata}"
export MINIO_ROOT_PASSWORD="${MINIO_ROOT_PASSWORD:-sisdata_minio_pw}"

GEOSERVER_PORT="${GEOSERVER_PORT:-8085}"
OLLAMA_PORT="${OLLAMA_PORT:-11434}"
PWS_PORT="${PWS_PORT:-8090}"
PWS_WP_HOME="${PWS_WP_HOME:-$SVC_HOME/pws-wordpress}"
GEOSERVER_DATA="$SVC_HOME/geoserver-data"

is_running() { [[ -f "$RUN/$1.pid" ]] && kill -0 "$(cat "$RUN/$1.pid")" 2>/dev/null; }

start_bg() { # name, command...
  local name="$1"; shift
  if is_running "$name"; then echo "  $name: already running"; return; fi
  nohup "$@" >"$LOG/$name.log" 2>&1 &
  echo $! >"$RUN/$name.pid"
  echo "  $name: started (pid $!, log $LOG/$name.log)"
}

stop_bg() {
  local name="$1"
  if is_running "$name"; then kill "$(cat "$RUN/$name.pid")" && echo "  $name: stopped"; else echo "  $name: not running"; fi
  rm -f "$RUN/$name.pid"
}

wait_port() { # port, label
  for _ in $(seq 1 60); do nc -z 127.0.0.1 "$1" 2>/dev/null && return 0; sleep 1; done
  echo "  WARNING: $2 not listening on :$1 after 60s"
}

start() {
  echo "brew services:"
  for s in "${BREW_SERVICES[@]}"; do brew services start "$s" >/dev/null 2>&1 || true; echo "  $s"; done

  echo "self-managed:"
  mkdir -p "$MINIO_DATA" "$GEOSERVER_DATA"
  start_bg minio minio server "$MINIO_DATA" --address 127.0.0.1:9000 --console-address 127.0.0.1:9001

  local gs_home; gs_home="$(brew --prefix geoserver)/libexec"
  if [[ ! -f "$GEOSERVER_DATA/global.xml" ]]; then cp -R "$gs_home/data_dir/." "$GEOSERVER_DATA/"; fi
  # Brew's `geoserver` wrapper can't take a port, so launch jetty directly.
  (cd "$gs_home" && start_bg geoserver "$(brew --prefix openjdk@21)/bin/java" \
      -DGEOSERVER_DATA_DIR="$GEOSERVER_DATA" -Xmx1g -jar start.jar "jetty.http.port=$GEOSERVER_PORT")

  start_bg clamd clamd --foreground

  # Local AI (MOD-KBA/DSH): bound to loopback only; never a public AI service.
  if command -v ollama >/dev/null 2>&1; then
    OLLAMA_HOST="127.0.0.1:$OLLAMA_PORT" OLLAMA_FLASH_ATTENTION=1 OLLAMA_KV_CACHE_TYPE=q8_0 \
      start_bg ollama ollama serve
  else
    echo "  ollama: not installed (brew install ollama) — MOD-KBA falls back to extractive answers"
  fi

  if [[ -x "$ROOT/infra/bin/keycloak-dev.sh" ]]; then "$ROOT/infra/bin/keycloak-dev.sh" start; fi
  if [[ -x "$ROOT/infra/bin/metabase-dev.sh" ]]; then "$ROOT/infra/bin/metabase-dev.sh" start; fi

  pws_start

  wait_port 9000 minio
  # Bucket for MOD-DMS (idempotent).
  (cd "$ROOT" && php artisan sisdata:bucket >/dev/null 2>&1) && echo "  minio bucket: ok" || echo "  minio bucket: run 'php artisan sisdata:bucket' once minio is up"
  status
}

pws_start() {
  if [[ ! -f "$PWS_WP_HOME/wp-config.php" ]]; then echo "  pws-wordpress: not installed (infra/bin/pws-setup.sh)"; return; fi
  brew services start mysql >/dev/null 2>&1 || true
  start_bg pws-wordpress php -d upload_max_filesize=6M -d post_max_size=32M -S "127.0.0.1:$PWS_PORT" -t "$PWS_WP_HOME" "$ROOT/pws/bin/router.php"
}

pws_stop() { stop_bg pws-wordpress; }

pws_status() {
  if nc -z 127.0.0.1 "$PWS_PORT" 2>/dev/null; then echo "  ✔ pws-wordpress :$PWS_PORT ($PWS_WP_HOME)"; else echo "  ✘ pws-wordpress :$PWS_PORT"; fi
  if nc -z 127.0.0.1 3306 2>/dev/null; then echo "  ✔ mysql :3306"; else echo "  ✘ mysql :3306"; fi
}

stop() {
  [[ -x "$ROOT/infra/bin/keycloak-dev.sh" ]] && "$ROOT/infra/bin/keycloak-dev.sh" stop || true
  [[ -x "$ROOT/infra/bin/metabase-dev.sh" ]] && "$ROOT/infra/bin/metabase-dev.sh" stop || true
  pws_stop
  stop_bg ollama
  stop_bg clamd
  stop_bg geoserver
  stop_bg minio
  for s in mailpit; do brew services stop "$s" >/dev/null 2>&1 || true; echo "  $s: stopped"; done
  echo "  (postgresql@17 and redis left running — shared with other projects)"
}

status() {
  echo "ports:"
  for p in "5432 postgres" "6379 redis" "1025 mailpit-smtp" "8025 mailpit-ui" "9000 minio" "9001 minio-console" \
           "$GEOSERVER_PORT geoserver" "8180 keycloak" "$OLLAMA_PORT ollama" "3030 metabase"; do
    set -- $p
    if nc -z 127.0.0.1 "$1" 2>/dev/null; then echo "  ✔ $2 :$1"; else echo "  ✘ $2 :$1"; fi
  done
  local sock="${CLAMAV_SOCKET:-/opt/homebrew/var/run/clamav/clamd.sock}"
  if [[ -S "$sock" ]]; then echo "  ✔ clamd $sock"; else echo "  ✘ clamd $sock"; fi
  pws_status
}

case "${1:-status}" in
  start) start ;;
  stop) stop ;;
  status) status ;;
  pws) case "${2:-status}" in start) pws_start ;; stop) pws_stop ;; status) pws_status ;; *) echo "usage: $0 pws start|stop|status" >&2; exit 1 ;; esac ;;
  *) echo "usage: $0 start|stop|status | pws start|stop|status" >&2; exit 1 ;;
esac
