<?php
/**
 * Import berita terkini dari portal wakafselangor.gov.my (REST API awam).
 *
 * Guna:  wp eval-file tools/import-news.php [bilangan]
 *
 * - Selamat dijalankan berulang kali: pos yang sudah diimport (ikut URL sumber) dilangkau.
 * - Hanya teks (perenggan, tajuk, senarai) dan gambar utama diambil; markup page-builder dibuang.
 */
if ( ! defined( 'WP_CLI' ) ) {
	exit( "Jalankan melalui WP-CLI.\n" );
}

$count = isset( $args[0] ) ? max( 1, (int) $args[0] ) : 6;
$api   = 'https://www.wakafselangor.gov.my/wp-json/wp/v2/posts?per_page=' . $count . '&_embed=wp:featuredmedia';

$res = wp_remote_get( $api, array( 'timeout' => 30, 'user-agent' => 'Mozilla/5.0 (PWS theme importer)' ) );
if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
	WP_CLI::error( 'Gagal mengambil berita dari portal sumber.' );
}
$items = json_decode( wp_remote_retrieve_body( $res ), true );
if ( ! is_array( $items ) ) {
	WP_CLI::error( 'Respons tidak sah.' );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$allowed = array(
	'p' => array(), 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'br' => array(),
	'h2' => array(), 'h3' => array(), 'h4' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(),
	'blockquote' => array(), 'a' => array( 'href' => true ),
);

$made = 0;
foreach ( $items as $it ) {
	$link = esc_url_raw( $it['link'] );
	$dup  = get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_pws_source_url', 'meta_value' => $link, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $dup ) {
		WP_CLI::log( 'Langkau (sudah ada): ' . wp_strip_all_tags( $it['title']['rendered'] ) );
		continue;
	}

	// Ambil hanya blok teks bermakna daripada markup page-builder.
	$html = '';
	if ( preg_match_all( '#<(p|h[2-4]|ul|ol|blockquote)\b[^>]*>.*?</\1>#is', $it['content']['rendered'], $m ) ) {
		$html = wp_kses( implode( "\n\n", $m[0] ), $allowed );
	}
	$html = trim( preg_replace( '#<p>\s*(&nbsp;)?\s*</p>#i', '', $html ) );
	if ( '' === $html ) {
		$html = '<p>' . esc_html( wp_strip_all_tags( $it['excerpt']['rendered'] ) ) . '</p>';
	}

	$id = wp_insert_post( array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => wp_strip_all_tags( html_entity_decode( $it['title']['rendered'], ENT_QUOTES, 'UTF-8' ) ),
		'post_content' => $html,
		'post_excerpt' => wp_trim_words( wp_strip_all_tags( $it['excerpt']['rendered'] ), 40 ),
		'post_date_gmt' => get_gmt_from_date( $it['date'] ) ? $it['date_gmt'] : null,
		'post_date'    => get_date_from_gmt( $it['date_gmt'] ),
	), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( 'Gagal: ' . $id->get_error_message() );
		continue;
	}
	update_post_meta( $id, '_pws_source_url', $link );

	$media = isset( $it['_embedded']['wp:featuredmedia'][0]['source_url'] ) ? $it['_embedded']['wp:featuredmedia'][0]['source_url'] : '';
	if ( $media ) {
		$thumb = media_sideload_image( esc_url_raw( $media ), $id, wp_strip_all_tags( $it['title']['rendered'] ), 'id' );
		if ( ! is_wp_error( $thumb ) ) {
			set_post_thumbnail( $id, $thumb );
		} else {
			WP_CLI::warning( 'Gambar gagal: ' . $thumb->get_error_message() );
		}
	}
	$made++;
	WP_CLI::success( 'Diimport: ' . get_the_title( $id ) );
}
WP_CLI::log( $made . ' pos baharu diimport.' );
