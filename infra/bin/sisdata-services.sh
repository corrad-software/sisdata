#!/usr/bin/env bash
# Local SISDATA backing services, native (Homebrew) — no Docker.
#
#   infra/bin/sisdata-services.sh start|stop|status
#
# brew services: postgresql@17, redis, mailpit
# self-managed (pid files under ~/sisdata-services/run): minio, geoserver, clamd
# (ClamAV config: /opt/homebrew/etc/clamav/clamd.conf; signatures: run `freshclam`)
# keycloak has its own script: infra/bin/keycloak-dev.sh (called from here)
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

  if [[ -x "$ROOT/infra/bin/keycloak-dev.sh" ]]; then "$ROOT/infra/bin/keycloak-dev.sh" start; fi

  wait_port 9000 minio
  # Bucket for MOD-DMS (idempotent).
  (cd "$ROOT" && php artisan sisdata:bucket >/dev/null 2>&1) && echo "  minio bucket: ok" || echo "  minio bucket: run 'php artisan sisdata:bucket' once minio is up"
  status
}

stop() {
  [[ -x "$ROOT/infra/bin/keycloak-dev.sh" ]] && "$ROOT/infra/bin/keycloak-dev.sh" stop || true
  stop_bg clamd
  stop_bg geoserver
  stop_bg minio
  for s in mailpit; do brew services stop "$s" >/dev/null 2>&1 || true; echo "  $s: stopped"; done
  echo "  (postgresql@17 and redis left running — shared with other projects)"
}

status() {
  echo "ports:"
  for p in "5432 postgres" "6379 redis" "1025 mailpit-smtp" "8025 mailpit-ui" "9000 minio" "9001 minio-console" \
           "$GEOSERVER_PORT geoserver" "8180 keycloak"; do
    set -- $p
    if nc -z 127.0.0.1 "$1" 2>/dev/null; then echo "  ✔ $2 :$1"; else echo "  ✘ $2 :$1"; fi
  done
  local sock="${CLAMAV_SOCKET:-/opt/homebrew/var/run/clamav/clamd.sock}"
  if [[ -S "$sock" ]]; then echo "  ✔ clamd $sock"; else echo "  ✘ clamd $sock"; fi
}

case "${1:-status}" in
  start) start ;;
  stop) stop ;;
  status) status ;;
  *) echo "usage: $0 start|stop|status" >&2; exit 1 ;;
esac
