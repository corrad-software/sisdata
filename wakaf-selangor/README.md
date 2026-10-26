# Perbadanan Wakaf Selangor — WordPress theme

Custom WordPress theme (`wp-content/themes/wakaf-selangor`) for the Perbadanan Wakaf Selangor (PWS) portal,
styled around the PWS crimson with a hexagon motif. Content is in Bahasa Melayu.

> **Scope of this directory.** This is a self-contained WordPress theme and its tooling — it is independent of the
> SISDATA Laravel/Next.js app and of the older `pws/` theme in this repository. WordPress core is **not** included:
> install WordPress separately, then copy (or symlink) `wp-content/themes/wakaf-selangor` into its `wp-content/themes/`
> and activate it. Commands below are run from the WordPress root.

## What is in the theme

| Area | Where |
|---|---|
| Homepage: hero slider, services, Wakaf Khas, products honeycomb, eWAKAF, distribution, district map, news | `front-page.php` |
| Sumbang Wakaf 4-step wizard + FAQ | `page-sumbang-wakaf.php` (loads for slug `sumbang-wakaf`) |
| Wakaf Khas list / project page | `archive-wakaf_khas.php`, `single-wakaf_khas.php` |
| News list / article, generic page | `index.php`, `single.php`, `page.php` |
| Header, footer, floating quick-link | `header.php`, `footer.php` |
| Editable figures (admin screen **Data Wakaf**) | `inc/data.php` |
| Wakaf Khas post type, seeding, SEO meta | `inc/content.php` |
| Styles / scripts | `style.css`, `assets/js/main.js` |
| Import latest news from the live PWS site | `tools/import-news.php` |

## Local development

```bash
# PHP built-in server with pretty-permalink routing
php -S localhost:8080 dev-router.php
```

Requires PHP 7.4+ and a MySQL/MariaDB database. See `DEPLOYMENT.md` for installation on a real server.
