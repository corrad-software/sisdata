#!/usr/bin/env bash
# PHP syntax check of the portal theme + plugin, and a check that every UI
# string used (hws_t('…')) exists in both languages.
#   pws/bin/lint.sh
set -euo pipefail
DIR="$(cd "$(dirname "$0")/.." && pwd)"
fail=0
while IFS= read -r -d '' f; do
  if ! out=$(php -l "$f" 2>&1); then echo "$out"; fail=1; fi
done < <(find "$DIR/wp-content" "$DIR/bin" -name '*.php' -print0)
php "$DIR/bin/check-i18n.php" "$DIR/wp-content" || fail=1
[[ $fail -eq 0 ]] && echo "pws lint: OK ($(find "$DIR/wp-content" "$DIR/bin" -name '*.php' | wc -l | tr -d ' ') fail PHP)"
exit $fail
