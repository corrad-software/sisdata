<?php

/**
 * Every literal hws_t('key') used in the theme/plugin must exist in both the
 * BM and EN dictionaries. Usage: php pws/bin/check-i18n.php pws/wp-content
 */
define('ABSPATH', '/');
$root = $argv[1] ?? __DIR__.'/../wp-content';
$dict = require $root.'/plugins/sisdata-connector/includes/i18n.php';
$used = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->getExtension() !== 'php') {
        continue;
    }
    preg_match_all("/hws_t\\('([a-z_0-9]+)'[,)]/", (string) file_get_contents($f->getPathname()), $m);
    $used = array_merge($used, $m[1]);
}
$used = array_values(array_unique($used));
$problems = [];
foreach (['ms', 'en'] as $lang) {
    foreach (array_diff($used, array_keys($dict[$lang])) as $k) {
        $problems[] = "tiada dalam {$lang}: {$k}";
    }
}
foreach (array_diff(array_keys($dict['ms']), array_keys($dict['en'])) as $k) {
    $problems[] = "tiada terjemahan en: {$k}";
}
if ($problems) {
    fwrite(STDERR, implode("\n", $problems)."\n");
    exit(1);
}
echo 'i18n: '.count($used)." kunci digunakan, lengkap BM/EN\n";
