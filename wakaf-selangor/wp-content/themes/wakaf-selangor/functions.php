<?php
/**
 * Wakaf Selangor theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PWS_VERSION', '1.0.0' );
define( 'PWS_SITE', 'https://www.wakafselangor.gov.my/' );

require_once get_template_directory() . '/inc/data.php';
require_once get_template_directory() . '/inc/content.php';

function pws_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_image_size( 'pws-card', 720, 450, true );

	register_nav_menus( array(
		'primary' => __( 'Menu Utama', 'wakaf-selangor' ),
	) );
}
add_action( 'after_setup_theme', 'pws_setup' );

/** The portal's content is in Bahasa Melayu. */
function pws_html_lang( $output ) {
	return preg_replace( '/lang="[^"]*"/', 'lang="ms-MY"', $output );
}
add_filter( 'language_attributes', 'pws_html_lang' );

function pws_assets() {
	wp_enqueue_style(
		'pws-fonts',
		'https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,300;9..144,500;9..144,600;9..144,700;9..144,800&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'pws-style', get_stylesheet_uri(), array( 'pws-fonts' ), PWS_VERSION );
	wp_enqueue_script( 'pws-main', get_template_directory_uri() . '/assets/js/main.js', array(), PWS_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
}
add_action( 'wp_enqueue_scripts', 'pws_assets' );

function pws_resource_hints( $urls, $relation ) {
	if ( 'preconnect' === $relation ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'pws_resource_hints', 10, 2 );

/** URL of a page on the live Wakaf Selangor portal. */
function pws_url( $path = '' ) {
	return esc_url( PWS_SITE . ltrim( $path, '/' ) );
}

/** URL of the Sumbang Wakaf (donation wizard) page. */
function pws_donate_url( $args = array() ) {
	$page = get_page_by_path( 'sumbang-wakaf' );
	$url  = $page ? get_permalink( $page ) : home_url( '/sumbang-wakaf/' );
	return esc_url( $args ? add_query_arg( $args, $url ) : $url );
}

/**
 * GET forms drop the action's query string, so with plain permalinks
 * the page id has to travel as a hidden field.
 */
function pws_donate_hidden_field() {
	$page = get_page_by_path( 'sumbang-wakaf' );
	if ( $page && ! get_option( 'permalink_structure' ) ) {
		echo '<input type="hidden" name="page_id" value="' . (int) $page->ID . '">';
	}
}

/** URL of an image bundled with the theme. */
function pws_img( $file ) {
	static $webp = array();
	if ( ! isset( $webp[ $file ] ) ) {
		$alt          = preg_replace( '/\.png$/i', '.webp', $file );
		$webp[ $file ] = ( $alt !== $file && file_exists( get_template_directory() . '/assets/img/' . $alt ) ) ? $alt : $file;
	}
	return esc_url( get_template_directory_uri() . '/assets/img/' . $webp[ $file ] );
}

/** Inline SVG icon (stroke style, inherits currentColor). */
function pws_icon( $name, $size = 20 ) {
	$paths = array(
		'coins'    => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>',
		'wallet'   => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
		'landmark' => '<line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/>',
		'search'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><circle cx="11.5" cy="14.5" r="2.5"/><path d="M13.3 16.3 15 18"/>',
		'phone'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>',
		'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
		'arrow'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'pin'      => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
		'menu'     => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
		'close'    => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'heart'    => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
		'school'   => '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
		'home'     => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
		'building' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>',
		'gem'      => '<path d="M6 3h12l4 6-10 13L2 9Z"/><path d="M11 3 8 9l4 13 4-13-3-6"/><path d="M2 9h20"/>',
		'shield'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
		'book'     => '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
		'trend'    => '<path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/>',
		'file'     => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
		'handshake'=> '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
		'key'      => '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>',
		'chevron'  => '<path d="m6 9 6 6 6-6"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="icon icon-%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		(int) $size,
		$paths[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput
	);
}

/**
 * Default navigation, mirroring the live portal's structure.
 * Used until a menu is assigned to the "Menu Utama" location in Appearance > Menus.
 */
function pws_default_menu() {
	$menu = array(
		'Maklumat Korporat' => array(
			'Sejarah'                 => 'sejarah/',
			'Punca Kuasa'             => 'punca-kuasa/',
			'Visi, Misi dan Objektif' => 'penubuhan-pws/',
			'Nilai-Nilai Wakaf'       => 'nilai-nilai-wakaf/',
			'Pengurusan'              => 'pengurusan-pws/',
			'Carta Organisasi'        => 'carta-organisasi-pengurusan/',
		),
		'Info Wakaf'        => array(
			'Definisi Wakaf'            => 'infowakaf/',
			'Produk Wakaf Selangor'     => 'produk-wakaf-selangor/',
			'Prosedur Penyertaan Wakaf' => 'prosedur/',
			'Kompilasi Fatwa'           => 'kompilasi-fatwa/',
		),
		'Informasi'         => array(
			'Statistik'            => 'statistik/',
			'Tunai Wakaf'          => 'wakaftunai/',
			'Wakaf Khas'           => 'wakaf-khas/',
			'Platform Berwakaf'    => 'platform/',
			'Info Wakaf Hartanah'  => 'hartanah-wakaf/',
			'Sewaan Hartanah'      => 'sewaan-2/',
			'Aktiviti dan Berita'  => 'berita/',
			'Laporan Tahunan'      => 'laporantahunan/',
			'Buletin'              => 'buletin/',
			'Kerjaya'              => 'kerjaya/',
		),
		'Khidmat Pelanggan' => array(
			'Hubungi Kami'          => 'hubungi-kami/',
			'Soalan Lazim'          => 'soalan-lazim/',
			'Borang-Borang PWS'     => 'muat-turun/',
			'Wakaf Selangor Online' => 'wakaf-selangor-online/',
		),
	);

	echo '<ul class="menu">';
	foreach ( $menu as $label => $children ) {
		echo '<li class="menu-item menu-item-has-children"><a href="#" aria-haspopup="true" aria-expanded="false">' . esc_html( $label ) . pws_icon( 'chevron', 14 ) . '</a><ul class="sub-menu">';
		foreach ( $children as $child => $path ) {
			echo '<li class="menu-item"><a href="' . pws_url( $path ) . '">' . esc_html( $child ) . '</a></li>';
		}
		echo '</ul></li>';
	}
	echo '</ul>';
}
