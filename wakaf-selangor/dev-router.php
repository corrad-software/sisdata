<?php
/**
 * Penghala pelayan pembangunan PHP untuk WordPress (pautan cantik).
 * Guna:  php -S localhost:8080 dev-router.php
 * Hanya untuk pembangunan tempatan — jangan gunakan di pelayan sebenar.
 */
$path = rawurldecode( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
$file = __DIR__ . $path;

if ( '/' !== $path && is_file( $file ) ) {
	return false; // fail statik atau skrip PHP sedia ada
}
if ( is_dir( $file ) && is_file( rtrim( $file, '/' ) . '/index.php' ) ) {
	$_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php';
	chdir( $file );
	require rtrim( $file, '/' ) . '/index.php';
	return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir( __DIR__ );
require __DIR__ . '/index.php';
