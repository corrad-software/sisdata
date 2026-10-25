#!/usr/bin/env bash
# Smoke check of a running Portal Hartanah Wakaf Selangor (default
# http://127.0.0.1:8090): every portal page answers 200 with the official
# name, EN switch works, security headers are present, wp-config/xmlrpc are
# blocked, and a waqf pledge submitted through the real form comes back with
# a SISDATA PHWS reference (portal → SISDATA API → MOD-PER).
#   pws/bin/smoke.sh [base-url]
set -uo pipefail
BASE="${1:-http://127.0.0.1:8090}"
JAR="$(mktemp)"; trap 'rm -f "$JAR"' EXIT
fail=0
ok() { printf '  ✔ %s\n' "$*"; }
bad() { printf '  ✘ %s\n' "$*"; fail=1; }

echo "Halaman:"
for p in / /hartanah/ /projek-wakaf/ /permohonan-wakaf/ /permohonan-sewaan/ /semak-status/ /bayaran/ /agihan-manfaat/ /berita/ /soalan-lazim/ /aduan/ /statistik/ /hubungi/ /notis-privasi/; do
  body="$(curl -s -w '\n%{http_code}' "$BASE$p")"; code="${body##*$'\n'}"
  if [[ "$code" == 200 && "$body" == *"Portal Hartanah Wakaf Selangor"* && "$body" != *"Portal Wakaf "* ]]; then ok "$p 200"; else bad "$p → $code"; fi
done
[[ "$(curl -s "$BASE/hartanah/?lang=en")" == *'lang="en"'* ]] && ok "EN: lang=\"en\"" || bad "EN switch"
[[ "$(curl -s "$BASE/hartanah/")" == *"Kadar sebulan"* ]] && ok "katalog hartanah daripada SISDATA" || bad "katalog hartanah kosong / SISDATA tidak dicapai"
[[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/tiada-halaman-ini/")" == 404 ]] && ok "404" || bad "404"

echo "Keselamatan:"
h="$(curl -sI "$BASE/")"
for x in "X-Content-Type-Options: nosniff" "X-Frame-Options: SAMEORIGIN" "Content-Security-Policy:" "Referrer-Policy:"; do
  grep -qi "^$x" <<<"$h" && ok "$x" || bad "header $x"
done
grep -qi '^X-Pingback' <<<"$h" && bad "X-Pingback" || ok "tiada X-Pingback"
[[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/wp-config.php")" == 403 ]] && ok "wp-config.php disekat" || bad "wp-config.php"
[[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/xmlrpc.php")" == 403 ]] && ok "xmlrpc.php disekat" || bad "xmlrpc.php"
users="$(curl -s -o /dev/null -w '%{http_code}' "$BASE/wp-json/wp/v2/users")"
[[ "$users" != 200 ]] && ok "tiada enumerasi pengguna REST ($users)" || bad "REST users terdedah"
[[ "$(curl -s "$BASE/permohonan-wakaf/")" != *"HWS_CLIENT_SECRET"* && "$(curl -s "$BASE/permohonan-wakaf/")" != *"phws_s_"* ]] && ok "rahsia API tidak sampai ke pelayar" || bad "rahsia terdedah"

echo "Borang hasrat wakaf (hujung ke hujung):"
page="$(curl -s -c "$JAR" -b "$JAR" "$BASE/permohonan-wakaf/")"
field() { sed -n "s/.*name=\"$1\" value=\"\([^\"]*\)\".*/\1/p" <<<"$page" | head -1; }
nonce="$(field _hws_nonce)"; kunci="$(field hws_kunci)"; sig="$(field hws_captcha_sig)"
a="$(sed -n 's/.*berapakah \([0-9]\) + \([0-9]\)?.*/\1/p' <<<"$page" | head -1)"; b="$(sed -n 's/.*berapakah \([0-9]\) + \([0-9]\)?.*/\2/p' <<<"$page" | head -1)"
if [[ -z "$nonce" || -z "$sig" || -z "$a" ]]; then bad "borang tidak lengkap"; else
  loc="$(curl -s -o /dev/null -w '%{redirect_url}' -c "$JAR" -b "$JAR" -X POST "$BASE/wp-admin/admin-post.php" \
    --data-urlencode action=hws_wakaf --data-urlencode hws_kembali=permohonan-wakaf --data-urlencode "hws_kunci=$kunci" \
    --data-urlencode hws_lang=ms --data-urlencode "_hws_nonce=$nonce" --data-urlencode "hws_captcha=$((a + b))" --data-urlencode "hws_captcha_sig=$sig" \
    --data-urlencode "nama_pewakaf=Pewakaf Ujian Asap (SINTETIK)" --data-urlencode emel=asap@contoh.invalid --data-urlencode jenis_wakaf=am \
    --data-urlencode "tujuan_wakaf=Tapak surau (SINTETIK)" --data-urlencode persetujuan_pdpa=1)"
  result="$(curl -s -b "$JAR" "$loc")"
  ref="$(grep -o 'PHWS-[0-9]\{4\}-[A-Z0-9]\{7\}' <<<"$result" | head -1)"
  [[ -n "$ref" ]] && ok "rujukan SISDATA diterima: $ref" || bad "tiada rujukan PHWS ($loc)"
  if [[ -n "$ref" ]]; then
    page="$(curl -s -c "$JAR" -b "$JAR" "$BASE/semak-status/")"; nonce="$(field _hws_nonce)"
    loc="$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -X POST "$BASE/wp-admin/admin-post.php" --data-urlencode action=hws_status \
      --data-urlencode hws_kembali=semak-status --data-urlencode "_hws_nonce=$nonce" --data-urlencode "no_rujukan=$ref" --data-urlencode emel=asap@contoh.invalid)"
    [[ "$(curl -s -b "$JAR" "$loc")" == *"Menunggu semakan"* ]] && ok "status dua hala: Menunggu semakan" || bad "semakan status"
  fi
fi

[[ $fail -eq 0 ]] && echo "SMOKE: LULUS" || echo "SMOKE: GAGAL"
exit $fail
