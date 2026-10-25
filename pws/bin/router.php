<?php

/**
 * Router for PHP's built-in web server (local development only):
 *   php -S 127.0.0.1:8090 -t ~/sisdata-services/pws-wordpress pws/bin/router.php
 * Serves existing static files, blocks sensitive files, and sends everything
 * else to WordPress (pretty permalinks). Production uses nginx/Apache + WAF.
 */
$root = $_SERVER['DOCUMENT_ROOT'];
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rawurldecode($path);

if (preg_match('#(^|/)\.|wp-config\.php|/xmlrpc\.php$|\.(log|sql|bak|ini|sh)$#i', $path)) {
    http_response_code(403);
    echo 'Forbidden';

    return true;
}
// No traversal; theme/plugin are symlinks into the repo, so don't realpath-contain.
if (str_contains($path, '..') || str_contains($path, "\0")) {
    http_response_code(400);

    return true;
}
$file = $root.$path;
if (is_file($file)) {
    if (str_ends_with($file, '.php')) {
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['SCRIPT_NAME'] = $path;
        $_SERVER['PHP_SELF'] = $path;
        chdir(dirname($file));
        require $file;

        return true;
    }

    return false; // static file
}
if (is_dir($file) && is_file(rtrim($file, '/').'/index.php')) {
    $file = rtrim($file, '/');
    $_SERVER['SCRIPT_FILENAME'] = $file.'/index.php';
    chdir($file);
    require $file.'/index.php';

    return true;
}
$_SERVER['SCRIPT_FILENAME'] = $root.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
chdir($root);
require $root.'/index.php';

return true;
