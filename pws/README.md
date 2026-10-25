# Portal Hartanah Wakaf Selangor (MOD-PWS)

Public portal of Majlis Agama Islam Selangor for waqf property — a **separate
system** from SISDATA (SRS-PWS-02): WordPress (SRS-PWS-03) on its own
server/domain, talking to SISDATA **only** through the versioned public API
`/api/v1/pws/awam/*`. "PWS" is an internal code; the display name is always
**Portal Hartanah Wakaf Selangor** (SRS-PWS-01).

```
browser ──HTTPS──▶ WordPress (theme hartanah-wakaf-selangor + plugin sisdata-connector)
                        │  server-to-server, OAuth2 client credentials, Idempotency-Key,
                        │  X-Pengguna-IP; API key never reaches the browser
                        ▼
              SISDATA /api/v1/pws/awam/*  (app/Modules/Pws — scopes, IP allowlist,
                        │                  rate limits, CORS, integration log, Awam only)
        ┌───────────────┼───────────────┬──────────────┬─────────────┬──────────────┐
     MOD-PJK         MOD-PER         MOD-ADU         MOD-TKT       MOD-KBA        MOD-WFE
 catalogue, rental  waqf case        public         support      public FAQ     approval of
 apps, payments     "menunggu        complaints     tickets      (user null,    portal content
 (mock FPX/card +   semakan"                                     local LLM)     & statistics
 signed webhook)
```

## What is here

| Path | |
| --- | --- |
| `wp-content/themes/hartanah-wakaf-selangor/` | Responsive theme in MAIS identity (green/gold), BM/EN switch, WCAG basics (skip link, landmarks, focus, contrast, error summaries, `lang`) |
| `wp-content/plugins/sisdata-connector/` | API client (`includes/class-hws-api.php`), forms + PRG handlers, shortcodes, BM/EN dictionary, analytics (no visitor ids), hardening |
| `bin/pasang.php` | Idempotent WordPress install/config + portal pages (no wp-cli) |
| `bin/router.php` | Router for `php -S` (local only) — blocks dotfiles, wp-config, xmlrpc |
| `bin/lint.sh` | `php -l` of all files + BM/EN key completeness |
| `bin/smoke.sh` | Curl smoke test incl. a real waqf submission through the form |

WordPress core is **not** in the repo: it lives in `~/sisdata-services/pws-wordpress`.

## Local set-up

Prerequisites: Homebrew MySQL (`brew services start mysql`), PHP 8.3+ with mysqli, a running SISDATA API.

```bash
# SISDATA API on its own port, on a DB you are allowed to write (developers: your test DB)
DB_DATABASE=myds_laravel_test_pws php artisan serve --no-reload --port=8011 &

# one-off, idempotent: download WP (SHA-1 checked), DB pws_wordpress + user, PwsSeeder
# (integration account + WFE flows), API client, wp-config with fresh salts, symlinks, pages
PWS_SISDATA_DB=myds_laravel_test_pws PWS_SISDATA_URL=http://127.0.0.1:8011 infra/bin/pws-setup.sh

infra/bin/sisdata-services.sh pws start      # php -S 127.0.0.1:8090 (also part of `start`)
open http://127.0.0.1:8090
pws/bin/smoke.sh                             # pages, headers, end-to-end submission
```

Secrets (DB password, API client id/secret) are written to `~/sisdata-services/pws-wordpress.env`
(chmod 600) and into a managed block of `wp-config.php` — never into the repo or the WP database.
`PWS_ROTATE=1 infra/bin/pws-setup.sh` rotates the client secret (old secret valid 24 h).
The lead runs the script without `PWS_SISDATA_DB` (uses `.env`) once MOD-PWS is merged and migrated.

wp-admin: `http://127.0.0.1:8090/wp-admin` (user `pentadbir_hws`, password printed once by the
installer). *Settings → SISDATA Connector* tests the connection.

## Pages (created by `bin/pasang.php`)

| Slug | Shortcode | SRS |
| --- | --- | --- |
| `/` | `[hws_ringkasan_utama]` | 01, 03 |
| `/hartanah/` | `[hws_hartanah]` — vacant premises flagged for the portal (PJK) | 06 |
| `/projek-wakaf/` | `[hws_projek]` — approved project content with progress | 06 |
| `/permohonan-wakaf/` | `[hws_borang_wakaf]` → PER case "menunggu semakan" | 07, 08, 21 |
| `/permohonan-sewaan/` | `[hws_borang_sewaan]` → PJK application | 07, 08, 21 |
| `/semak-status/` | `[hws_semak_status]` (reference + e-mail) | 09 (partial), 21 |
| `/bayaran/`, `/gateway-ujian/` | `[hws_bayaran]`, `[hws_gateway_ujian]` (non-prod) | 10–12, 16 |
| `/agihan-manfaat/` | `[hws_agihan]` — confirmed figures, wakaf vs dibeli, disclaimer | 17, 18, 19 (partial) |
| `/berita/`, `/soalan-lazim/`, `/statistik/` | published content / KBA public FAQ / approved statistics | 04, 27 |
| `/aduan/` | `[hws_aduan]` → MOD-ADU public API | ADU-02..04 |
| `/hubungi/` | `[hws_hubungi]` → MOD-TKT ticket | — |
| `/notis-privasi/` | PDPA notice | SEC-02 |

Language: `?lang=en` / `?lang=ms` (cookie). Page EN titles/intros are post meta
(`hws_tajuk_en`, `hws_intro_en`, `hws_kandungan_en`); content from SISDATA carries BM/EN fields.
No Polylang — fewer plugins to patch and harden.

## SISDATA public API (v1) — summary

All under `/api/v1/pws/awam/`, JSON, `Accept-Language: ms|en`.

| Method | Path | Scope | Notes |
| --- | --- | --- | --- |
| POST | `token` | — | client_credentials (body or Basic) → Bearer, 15 min |
| GET | `hartanah`, `agihan`, `kandungan`, `kandungan/{slug}`, `statistik`, `soalan-lazim` | baca | whitelisted Awam fields only |
| POST | `soalan-lazim/tanya` | baca | KBA answer, user null, local model |
| POST | `permohonan/wakaf`, `permohonan/sewaan` | permohonan | `Idempotency-Key` required; multipart `lampiran[]` |
| POST | `permohonan/status` | permohonan | reference + e-mail (same 404 for both failures) |
| GET | `permohonan/perubahan?sejak=` | permohonan | two-way status feed |
| GET/POST | `bayaran/saluran`, `bayaran`, `bayaran/{ruj}`, `bayaran/{ruj}/simulasi` | bayaran | amount from SISDATA; status by server-side query; simulation only non-prod + mock |
| GET/POST | `aduan/rujukan`, `aduan`, `aduan/semak` | aduan | MOD-ADU with end-user IP, CAPTCHA |
| POST | `sokongan` | sokongan | MOD-TKT `TicketIntake` (503 until MOD-TKT enabled) |
| POST | `analitik` | analitik | daily page counters |

Headers: `X-Pengguna-IP` (visitor IP, trusted only from an authenticated client),
`Idempotency-Key` (8–100 chars). Errors: `{message, code, errors?}`; 401 / 403 / 422 / 429 (+`Retry-After`) / 503.

## Security notes (SRS-PWS-23..25)

* Client secret stored as HMAC; token stored as SHA-256; IP allowlist per client; expiry; rotation with grace; immediate revocation; managing keys requires MFA (SSO).
* Per-client rate limit (admin-set), per-end-user-IP limits on submissions (ADM parameters `pws.had_*`), failed-credential throttling.
* CORS only for `PWS_PORTAL_ORIGINS`, never with credentials (server-to-server is the norm).
* Every inbound/outbound call logged in `pws_log_integrasi` with a **masked** payload summary (personal fields, credentials and anything shaped like a card number are masked) and mirrored to the ADM integration log.
* Portal: CSP (`script-src 'self'`, no inline scripts), X-Frame-Options, nosniff, Referrer-Policy, HSTS on TLS; XML-RPC off; no REST user enumeration/author archives; `DISALLOW_FILE_EDIT`/`DISALLOW_FILE_MODS`; arithmetic CAPTCHA + honeypot (replace with the MAIS-approved CAPTCHA provider in production — set `HWS_CAPTCHA_TOKEN`/provider hook).
* Operational, not code (SRS-PWS-25/28): WAF in front, TLS 1.3, OS/web-server hardening, patch schedule, VA/PT before go-live, separate network segment with no route to the SISDATA DB.

## Not done yet (see the MOD-PWS report)

Public user accounts with e-mail/phone verification and 2FA (SRS-PWS-05) and the account dashboard
with document downloads (SRS-PWS-09 full) — the portal currently uses reference + e-mail;
PDF receipt download from the portal (SRS-PWS-13: PJK issues and e-mails the receipt, but exposes no
portal-scoped receipt endpoint yet); policy version/effective date on the distribution feed
(SRS-PWS-19: PJK's public feed doesn't return them); photos/map location of premises (SRS-PWS-06).
