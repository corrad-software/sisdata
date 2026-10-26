# Deployment guide

## 1. Requirements
- PHP 7.4+ (developed on 8.5), MySQL 5.7+/MariaDB 10.4+, HTTPS.
- WordPress 6.0+ (developed on 7.1.3).

## 2. Install
1. Install WordPress as usual and copy `wp-content/themes/wakaf-selangor/` to the server.
2. **Activate the theme.** On activation it automatically:
   - creates the pages *Sumbang Wakaf*, *Laman Utama* and *Berita* and sets them as the static front page / posts page;
   - creates the three starter **Wakaf Khas** projects (with photos);
   - switches permalinks to `/%postname%/` and flushes rewrite rules.
3. Optional — import the latest news from the live portal (safe to re-run, skips duplicates):
   ```bash
   wp eval-file tools/import-news.php 6
   ```
4. Set **Settings → General → Site Title** to *Perbadanan Wakaf Selangor* and review the tagline.

## 3. Staff guide
| Task | Where in wp-admin |
|---|---|
| Update collection/distribution, Fisabilillah figures, district property statistics | **Data Wakaf** |
| Add or edit a Wakaf Khas project (name, cost, optional *collected* amount, photo, order) | **Wakaf Khas** |
| Publish news | **Posts** |

Notes: the homepage shows the first **three** Wakaf Khas projects (ordered by *Order*). If *Telah dikutip* is filled
in, a progress bar appears on the project page. District names and map marker positions are fixed to the map image.

## 4. External services the theme depends on
These are the same endpoints used by the current portal. If any changes, update them in the theme:

| Service | Used for | Location |
|---|---|---|
| `https://fpx.wakafselangor.gov.my/fpxForm.asp?action=addnew` (POST: `amount`, `nama`, `icno`) | Final step of the donation wizard | `page-sumbang-wakaf.php` |
| `https://fpx.wakafselangor.gov.my/ewo_login.asp`, `ewo_daftar.asp` | eWAKAF Online login / register | `front-page.php`, `footer.php` |
| `https://infaqpay.my/go/wakafselangor` | Monthly Direct Debit | `page-sumbang-wakaf.php` |
| JomPAY Biller Code **233064** (Ref-1 IC number, Ref-2 phone) | JomPAY instructions | `page-sumbang-wakaf.php` |
| `https://www.wakafselangor.gov.my/...` | Pages not yet rebuilt locally (menu, some links) | `pws_url()` in `functions.php` |

## 5. Before going live — checklist
- [ ] **Make one real small test donation** end to end. The wizard posts to the live payment portal and the behaviour after the POST is controlled by PWS, not this theme.
- [ ] Review wording that was not on the source site: the hadith (HR Muslim) on the wizard page and footer, the headlines, the minimum amount (RM1) and preset amounts (RM10–RM500).
- [ ] Confirm the *Wakaf Takaful* description (the source page repeated the *Wakaf Emas* text, so a neutral line is shown).
- [ ] HTTPS enabled and forced; HSTS if appropriate.
- [ ] Caching (page cache + object cache) and a CDN for `/wp-content/uploads` if traffic warrants.
- [ ] Upload replacement high-resolution mosque photos (source images are only 516 px wide and look soft in large hexagons).
- [ ] Strong admin credentials (the local install uses a throw-away password), 2FA, login-attempt limiting, automatic security updates.
- [ ] Backups (database + `wp-content/uploads`) with a tested restore.
- [ ] Search Console + submit `/wp-sitemap.xml`; verify Open Graph preview of the homepage.
- [ ] Privacy: the wizard collects name and IC number and sends them straight to the PWS portal; nothing is stored by WordPress. Link to the PWS *Notis Privasi* is shown on step 3.
- [ ] Run an accessibility pass with real assistive tech (automated checks done with axe-core during development).

## 6. Local development
```bash
php -S localhost:8080 dev-router.php   # routes pretty permalinks; development only
```
