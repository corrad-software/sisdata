#!/usr/bin/env bash
#
# Local "SSO MAIS" OIDC IdP for SISDATA (SRS-RBC-01 SSO, SRS-RBC-03 MFA).
# Native Keycloak in dev mode (no Docker), dev-file (H2) database.
#
# Usage: infra/bin/keycloak-dev.sh {start|stop|restart|status|logs|import}
#
#   start    Start Keycloak in the background. On first start the realm
#            infra/keycloak/realm-mais.json is imported (--import-realm skips
#            realms that already exist, so later starts keep your changes).
#   stop     Stop it.
#   status   Show whether it is running and whether realm "mais" answers.
#   logs     Tail the log file.
#   import   (Keycloak must be stopped) Re-import the realm file, OVERWRITING
#            the existing "mais" realm (users' TOTP enrolments are lost).
#
# Environment (all optional):
#   KEYCLOAK_HOME                Keycloak install dir
#                                (default: ~/sisdata-services/keycloak/keycloak-26.8.1)
#   JAVA_HOME                    JDK 21 (default: /opt/homebrew/opt/openjdk@21)
#   KC_HTTP_PORT                 HTTP port (default: 8180)
#   KC_MANAGEMENT_PORT           management port (default: 9180; avoids php-fpm's 9000)
#   KC_BOOTSTRAP_ADMIN_USERNAME  master-realm admin created on the very first start
#                                (default: admin)
#   KC_BOOTSTRAP_ADMIN_PASSWORD  its password (default: admin)
#   KC_RUN_DIR                   pid + log dir (default: ~/sisdata-services/keycloak/run)
#
# Admin console: http://localhost:8180/admin  (master realm, bootstrap admin creds)
# Realm:         http://localhost:8180/realms/mais/.well-known/openid-configuration

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REALM_FILE="$(cd "$SCRIPT_DIR/../keycloak" && pwd)/realm-mais.json"

KEYCLOAK_HOME="${KEYCLOAK_HOME:-$HOME/sisdata-services/keycloak/keycloak-26.8.1}"
export JAVA_HOME="${JAVA_HOME:-/opt/homebrew/opt/openjdk@21}"
KC_HTTP_PORT="${KC_HTTP_PORT:-8180}"
KC_MANAGEMENT_PORT="${KC_MANAGEMENT_PORT:-9180}"
export KC_BOOTSTRAP_ADMIN_USERNAME="${KC_BOOTSTRAP_ADMIN_USERNAME:-admin}"
export KC_BOOTSTRAP_ADMIN_PASSWORD="${KC_BOOTSTRAP_ADMIN_PASSWORD:-admin}"
KC_RUN_DIR="${KC_RUN_DIR:-$HOME/sisdata-services/keycloak/run}"

PID_FILE="$KC_RUN_DIR/keycloak.pid"
LOG_FILE="$KC_RUN_DIR/keycloak.log"
KC="$KEYCLOAK_HOME/bin/kc.sh"
REALM_URL="http://localhost:${KC_HTTP_PORT}/realms/mais/.well-known/openid-configuration"

die() { echo "keycloak-dev: $*" >&2; exit 1; }

preflight() {
    [ -x "$KC" ] || die "Keycloak not found at $KEYCLOAK_HOME (set KEYCLOAK_HOME)"
    [ -x "$JAVA_HOME/bin/java" ] || die "Java not found at $JAVA_HOME (brew install openjdk@21, or set JAVA_HOME)"
    mkdir -p "$KC_RUN_DIR" "$KEYCLOAK_HOME/data/import"
    # Copied (not symlinked) on every start/import so Keycloak always sees the
    # repo's current file, and a moved/deleted checkout can't leave a dangling
    # link that breaks --import-realm.
    rm -f "$KEYCLOAK_HOME/data/import/realm-mais.json"
    cp "$REALM_FILE" "$KEYCLOAK_HOME/data/import/realm-mais.json"
}

is_running() {
    [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null
}

start() {
    if is_running; then
        echo "Keycloak already running (pid $(cat "$PID_FILE")) on :$KC_HTTP_PORT"
        return 0
    fi
    preflight
    echo "Starting Keycloak (dev mode) on :$KC_HTTP_PORT — log: $LOG_FILE"
    nohup "$KC" start-dev \
        --http-port="$KC_HTTP_PORT" \
        --http-management-port="$KC_MANAGEMENT_PORT" \
        --import-realm \
        >>"$LOG_FILE" 2>&1 &
    echo $! >"$PID_FILE"

    for _ in $(seq 1 90); do
        if curl -fsS -o /dev/null "$REALM_URL" 2>/dev/null; then
            echo "Keycloak is up: $REALM_URL"
            return 0
        fi
        is_running || die "Keycloak exited during startup — see $LOG_FILE"
        sleep 2
    done
    die "Keycloak did not become ready in 180s — see $LOG_FILE"
}

stop() {
    if ! is_running; then
        echo "Keycloak is not running"
        rm -f "$PID_FILE"
        return 0
    fi
    local pid
    pid="$(cat "$PID_FILE")"
    echo "Stopping Keycloak (pid $pid)"
    kill "$pid"
    for _ in $(seq 1 30); do
        kill -0 "$pid" 2>/dev/null || { rm -f "$PID_FILE"; echo "Stopped"; return 0; }
        sleep 1
    done
    kill -9 "$pid" 2>/dev/null || true
    rm -f "$PID_FILE"
    echo "Killed"
}

status() {
    if is_running; then
        echo "Keycloak running (pid $(cat "$PID_FILE")) on :$KC_HTTP_PORT"
    else
        echo "Keycloak not running"
    fi
    if curl -fsS -o /dev/null "$REALM_URL" 2>/dev/null; then
        echo "Realm mais: OK ($REALM_URL)"
    else
        echo "Realm mais: not reachable"
        return 1
    fi
}

import_realm() {
    is_running && die "stop Keycloak first"
    preflight
    "$KC" import --file "$KEYCLOAK_HOME/data/import/realm-mais.json" --override true
}

case "${1:-}" in
    start) start ;;
    stop) stop ;;
    restart) stop; start ;;
    status) status ;;
    logs) tail -f "$LOG_FILE" ;;
    import) import_realm ;;
    *) echo "Usage: $0 {start|stop|restart|status|logs|import}" >&2; exit 2 ;;
esac
