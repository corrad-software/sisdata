#!/usr/bin/env bash
# Portal Hartanah Wakaf Selangor (MOD-PWS) — local WordPress set-up. Idempotent.
#
#   infra/bin/pws-setup.sh            # install / repair
#   PWS_SISDATA_DB=myds_laravel_test_pws PWS_SISDATA_URL=http://127.0.0.1:8011 infra/bin/pws-setup.sh
#
# The portal is a SEPARATE system (SRS-PWS-02): WordPress core lives OUTSIDE the
# repo in ~/sisdata-services/pws-wordpress; only the theme + plugin come from
# the repo (pws/wp-content, symlinked). It talks to SISDATA only through the
# public API (/api/v1/pws/awam/*) with its own client credentials.
#
# Steps: download WordPress (official zip, SHA-1 checked) if missing → MySQL
# database + user (Homebrew MySQL) → SISDATA integration seed + API client →
# wp-config.php with fresh salts → symlink theme/plugin → install WordPress,
# activate theme/plugin, create the portal pages (pws/bin/pasang.php).
#
# Secrets (DB password, API client secret) are kept in
# ~/sisdata-services/pws-wordpress.env (chmod 600) — never in the repo.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SVC_HOME="${SISDATA_SERVICES_HOME:-$HOME/sisdata-services}"
WP_HOME="${PWS_WP_HOME:-$SVC_HOME/pws-wordpress}"
ENV_FILE="${PWS_ENV_FILE:-$SVC_HOME/pws-wordpress.env}"
PORT="${PWS_PORT:-8090}"
SITE_URL="${PWS_SITE_URL:-http://127.0.0.1:$PORT}"
SISDATA_URL="${PWS_SISDATA_URL:-http://127.0.0.1:8000}"
ADMIN_EMAIL="${PWS_ADMIN_EMAIL:-pentadbir@hartanahwakaf.test}"
DB_NAME="${PWS_DB_NAME:-pws_wordpress}"
DB_USER="${PWS_DB_USER:-pws_wp}"
MYSQL="${MYSQL:-mysql -uroot}"

say() { printf '\n==> %s\n' "$*"; }

# ------------------------------------------------------------- 1. WordPress core
if [[ ! -f "$WP_HOME/wp-includes/version.php" ]]; then
  say "Muat turun WordPress (wordpress.org)"
  dl="$SVC_HOME/pws-dl"; mkdir -p "$dl"
  zip="$dl/wordpress.zip"
  if [[ ! -f "$zip" ]]; then curl -fsSL -o "$zip" https://wordpress.org/latest.zip; fi
  want="$(curl -fsSL https://wordpress.org/latest.zip.sha1 || true)"
  have="$(shasum "$zip" | cut -d' ' -f1)"
  if [[ -n "$want" && "$want" != "$have" ]]; then echo "SHA-1 tidak sepadan ($have ≠ $want) — dibatalkan"; rm -f "$zip"; exit 1; fi
  tmp="$(mktemp -d "$dl/unzip.XXXX")"
  unzip -q "$zip" -d "$tmp"
  mkdir -p "$(dirname "$WP_HOME")"
  rm -rf "$WP_HOME"; mv "$tmp/wordpress" "$WP_HOME"; rm -rf "$tmp" "$zip"
  # Hardening / disk: no bundled themes or demo plugins (only our theme + plugin are used).
  rm -rf "$WP_HOME"/wp-content/themes/twenty* "$WP_HOME/wp-content/plugins/akismet" "$WP_HOME/wp-content/plugins/hello.php"
  rmdir "$dl" 2>/dev/null || true
fi
echo "WordPress $(php -r "include '$WP_HOME/wp-includes/version.php'; echo \$wp_version;") di $WP_HOME"

# ------------------------------------------------------------- 2. secrets file
touch "$ENV_FILE"; chmod 600 "$ENV_FILE"
# shellcheck disable=SC1090
source "$ENV_FILE"
setvar() { # name value — persist in ENV_FILE
  if grep -q "^$1=" "$ENV_FILE"; then sed -i '' "s|^$1=.*|$1='$2'|" "$ENV_FILE"; else echo "$1='$2'" >>"$ENV_FILE"; fi
  printf -v "$1" '%s' "$2"
}
[[ -n "${PWS_DB_PASS:-}" ]] || setvar PWS_DB_PASS "$(openssl rand -hex 18)"

# ------------------------------------------------------------- 3. MySQL
say "MySQL: pangkalan data $DB_NAME"
brew services start mysql >/dev/null 2>&1 || true
for _ in $(seq 1 30); do $MYSQL -e 'select 1' >/dev/null 2>&1 && break; sleep 1; done
$MYSQL <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$PWS_DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$PWS_DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

# ------------------------------------------------------------- 4. SISDATA client
if [[ -z "${HWS_CLIENT_ID:-}" || -z "${HWS_CLIENT_SECRET:-}" || "${PWS_ROTATE:-0}" == "1" ]]; then
  say "SISDATA: akaun integrasi + klien API (DB ${PWS_SISDATA_DB:-dari .env})"
  art=(php artisan)
  envs=()
  [[ -n "${PWS_SISDATA_DB:-}" ]] && envs=(env "DB_DATABASE=$PWS_SISDATA_DB" CACHE_STORE=array QUEUE_CONNECTION=sync)
  (cd "$ROOT" && ${envs[@]+"${envs[@]}"} "${art[@]}" db:seed --class=PwsSeeder --force --no-interaction >/dev/null)
  json="$(cd "$ROOT" && ${envs[@]+"${envs[@]}"} "${art[@]}" pws:cipta-klien "Portal Hartanah Wakaf Selangor (tempatan)" --json --no-interaction | tail -1)"
  setvar HWS_CLIENT_ID "$(php -r '$j=json_decode($argv[1],true); echo $j["client_id"] ?? "";' "$json")"
  setvar HWS_CLIENT_SECRET "$(php -r '$j=json_decode($argv[1],true); echo $j["client_secret"] ?? "";' "$json")"
  [[ -n "$HWS_CLIENT_ID" ]] || { echo "Gagal mencipta klien API: $json"; exit 1; }
fi
setvar HWS_SISDATA_URL "$SISDATA_URL"

# ------------------------------------------------------------- 5. wp-config.php
say "wp-config.php"
salt() { openssl rand -base64 48 | tr -d '\n/+=' | cut -c1-64; }
if [[ ! -f "$WP_HOME/wp-config.php" ]]; then
  {
    echo "<?php"
    echo "// Portal Hartanah Wakaf Selangor — generated by infra/bin/pws-setup.sh"
    echo "define('DB_NAME', '$DB_NAME');"
    echo "define('DB_USER', '$DB_USER');"
    echo "define('DB_PASSWORD', '$PWS_DB_PASS');"
    echo "define('DB_HOST', '127.0.0.1');"
    echo "define('DB_CHARSET', 'utf8mb4');"
    echo "define('DB_COLLATE', '');"
    for k in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
      echo "define('$k', '$(salt)');"
    done
    echo "\$table_prefix = 'hws_';"
    echo "define('WP_DEBUG', false);"
    echo "define('DISALLOW_FILE_EDIT', true);"
    echo "define('DISALLOW_FILE_MODS', true);"
    echo "define('WP_AUTO_UPDATE_CORE', 'minor');"
    echo "define('WP_POST_REVISIONS', 10);"
    echo "define('AUTOMATIC_UPDATER_DISABLED', true);"
    echo "// >>> HWS (managed by pws-setup.sh)"
    echo "// <<< HWS"
    echo "if (! defined('ABSPATH')) { define('ABSPATH', __DIR__.'/'); }"
    echo "require_once ABSPATH.'wp-settings.php';"
  } >"$WP_HOME/wp-config.php"
fi
# Refresh the managed block with the current connector settings.
php -r '
$f = $argv[1];
$s = file_get_contents($f);
$block = "// >>> HWS (managed by pws-setup.sh)\n"
  ."define(\x27HWS_SISDATA_URL\x27, ".var_export($argv[2], true).");\n"
  ."define(\x27HWS_CLIENT_ID\x27, ".var_export($argv[3], true).");\n"
  ."define(\x27HWS_CLIENT_SECRET\x27, ".var_export($argv[4], true).");\n"
  ."define(\x27HWS_SMTP_HOST\x27, \x27127.0.0.1\x27);\ndefine(\x27HWS_SMTP_PORT\x27, 1025);\n"
  ."define(\x27HWS_GATEWAY_UJIAN\x27, true); // NON-PRODUCTION ONLY\n"
  ."// <<< HWS";
$s = preg_replace("~// >>> HWS.*?// <<< HWS~s", $block, $s);
file_put_contents($f, $s);
' "$WP_HOME/wp-config.php" "$HWS_SISDATA_URL" "$HWS_CLIENT_ID" "$HWS_CLIENT_SECRET"
chmod 640 "$WP_HOME/wp-config.php"

# ------------------------------------------------------------- 6. theme + plugin
say "Pautan tema dan pemalam daripada repo"
ln -sfn "$ROOT/pws/wp-content/themes/hartanah-wakaf-selangor" "$WP_HOME/wp-content/themes/hartanah-wakaf-selangor"
ln -sfn "$ROOT/pws/wp-content/plugins/sisdata-connector" "$WP_HOME/wp-content/plugins/sisdata-connector"

# ------------------------------------------------------------- 7. install + pages
say "Pasang WordPress dan halaman portal"
php "$ROOT/pws/bin/pasang.php" "$WP_HOME" "$SITE_URL" "$ADMIN_EMAIL"

say "Siap. Mulakan: infra/bin/sisdata-services.sh pws start  →  $SITE_URL"
echo "SISDATA API: $HWS_SISDATA_URL (klien $HWS_CLIENT_ID; rahsia dalam $ENV_FILE)"
