<?php
/**
 * Kandungan: jenis pos Wakaf Khas, penyemaian kandungan asal, dan meta SEO.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Jenis pos: Wakaf Khas
 * ------------------------------------------------------------------ */
function pws_register_post_types() {
	register_post_type( 'wakaf_khas', array(
		'labels'        => array(
			'name'               => 'Wakaf Khas',
			'singular_name'      => 'Projek Wakaf Khas',
			'add_new'            => 'Tambah projek',
			'add_new_item'       => 'Tambah projek Wakaf Khas',
			'edit_item'          => 'Edit projek',
			'new_item'           => 'Projek baharu',
			'view_item'          => 'Lihat projek',
			'search_items'       => 'Cari projek',
			'not_found'          => 'Tiada projek dijumpai',
			'all_items'          => 'Semua projek',
			'menu_name'          => 'Wakaf Khas',
		),
		'public'        => true,
		'has_archive'   => 'wakaf-khas',
		'rewrite'       => array( 'slug' => 'wakaf-khas', 'with_front' => false ),
		'menu_icon'     => 'dashicons-building',
		'menu_position' => 25,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
		'show_in_rest'  => true,
	) );
}
add_action( 'init', 'pws_register_post_types' );

function pws_khas_meta_box() {
	add_meta_box( 'pws_khas', 'Maklumat projek', 'pws_khas_meta_box_html', 'wakaf_khas', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'pws_khas_meta_box' );

function pws_khas_meta_box_html( $post ) {
	wp_nonce_field( 'pws_khas_save', 'pws_khas_nonce' );
	$kos   = get_post_meta( $post->ID, '_pws_kos', true );
	$kutip = get_post_meta( $post->ID, '_pws_kutip', true );
	?>
	<p><label for="pws_kos"><strong>Kos pembinaan (juta RM)</strong></label><br>
		<input type="number" step="0.01" min="0" id="pws_kos" name="pws_kos" value="<?php echo esc_attr( $kos ); ?>" style="width:100%"></p>
	<p><label for="pws_kutip"><strong>Telah dikutip (juta RM)</strong></label><br>
		<input type="number" step="0.01" min="0" id="pws_kutip" name="pws_kutip" value="<?php echo esc_attr( $kutip ); ?>" style="width:100%">
		<span class="description">Pilihan. Jika diisi, bar kemajuan dipaparkan pada halaman projek.</span></p>
	<p class="description">Gambar projek: guna <em>Featured image</em>. Susunan: guna <em>Order</em> di bawah Atribut Halaman. Hanya tiga projek pertama dipaparkan pada laman utama.</p>
	<?php
}

function pws_khas_save( $post_id ) {
	if ( ! isset( $_POST['pws_khas_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pws_khas_nonce'] ) ), 'pws_khas_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array( 'pws_kos' => '_pws_kos', 'pws_kutip' => '_pws_kutip' ) as $field => $meta ) {
		$v = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		if ( '' !== $v && is_numeric( $v ) && (float) $v >= 0 ) {
			update_post_meta( $post_id, $meta, (string) (float) $v );
		} else {
			delete_post_meta( $post_id, $meta );
		}
	}
}
add_action( 'save_post_wakaf_khas', 'pws_khas_save' );

/** Projek asal (digunakan jika belum ada projek dalam pangkalan data). */
function pws_default_projects() {
	return array(
		array( 'slug' => 'masjid-denai-alam',  'name' => 'Masjid Denai Alam',                'kos' => 19,    'img' => 'Masjid-Denai-Alam.png',                'alt' => 'Pandangan udara Masjid Denai Alam' ),
		array( 'slug' => 'masjid-an-nur',      'name' => 'Masjid An-Nur, Temasya Glenmarie', 'kos' => 12.2,  'img' => 'Masjid-An-Nur-Temasya-Glenmarie.png', 'alt' => 'Pandangan udara Masjid An-Nur, Temasya Glenmarie' ),
		array( 'slug' => 'masjid-saujana-putra', 'name' => 'Masjid Saujana Putra',           'kos' => 11.15, 'img' => 'Masjid-Raja-Lumu.png',                 'alt' => 'Pandangan udara Masjid Saujana Putra' ),
	);
}

/**
 * Projek Wakaf Khas untuk paparan: setiap item mengandungi
 * id, name, kos, label, kutip, img (url), alt, link.
 */
function pws_projects( $limit = -1 ) {
	$q = new WP_Query( array(
		'post_type'      => 'wakaf_khas',
		'posts_per_page' => $limit,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
		'no_found_rows'  => true,
	) );
	$out = array();
	foreach ( $q->posts as $p ) {
		$kos   = (float) get_post_meta( $p->ID, '_pws_kos', true );
		$kutip = get_post_meta( $p->ID, '_pws_kutip', true );
		$thumb = get_the_post_thumbnail_url( $p->ID, 'large' );
		$out[] = array(
			'id'    => $p->ID,
			'name'  => get_the_title( $p ),
			'kos'   => $kos,
			'label' => $kos > 0 ? pws_juta_label( $kos ) : '',
			'kutip' => '' !== $kutip ? (float) $kutip : null,
			'img'   => $thumb ? esc_url( $thumb ) : pws_img( 'Masjid-Denai-Alam.png' ),
			'alt'   => 'Pandangan ' . get_the_title( $p ),
			'link'  => get_permalink( $p ),
		);
	}
	if ( $out ) {
		return $out;
	}
	foreach ( pws_default_projects() as $d ) {
		$out[] = array(
			'id'    => 0,
			'name'  => $d['name'],
			'kos'   => $d['kos'],
			'label' => pws_juta_label( $d['kos'] ),
			'kutip' => null,
			'img'   => pws_img( $d['img'] ),
			'alt'   => $d['alt'],
			'link'  => pws_url( 'wakaf-khas/' ),
		);
	}
	return $limit > 0 ? array_slice( $out, 0, $limit ) : $out;
}

/** Pautan ke halaman Berita (halaman jawatan) atau portal jika belum ada. */
function pws_news_url() {
	$id = (int) get_option( 'page_for_posts' );
	return $id ? esc_url( get_permalink( $id ) ) : pws_url( 'berita/' );
}

/** Pautan arkib Wakaf Khas tempatan. */
function pws_khas_url() {
	$u = get_post_type_archive_link( 'wakaf_khas' );
	return $u ? esc_url( $u ) : pws_url( 'wakaf-khas/' );
}

/* ------------------------------------------------------------------
 * Penyemaian kandungan (sekali, ketika tema diaktifkan)
 * ------------------------------------------------------------------ */
function pws_sideload_theme_image( $file, $post_id, $desc ) {
	$path = get_template_directory() . '/assets/img/' . $file;
	if ( ! file_exists( $path ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( $file );
	copy( $path, $tmp );
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), $post_id, $desc );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	return (int) $id;
}

function pws_seed_content() {
	// Halaman Sumbang Wakaf (wizard).
	if ( ! get_page_by_path( 'sumbang-wakaf' ) ) {
		wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Sumbang Wakaf', 'post_name' => 'sumbang-wakaf', 'post_status' => 'publish' ) );
	}
	// Halaman Laman Utama + Berita (halaman jawatan).
	$front = get_page_by_path( 'laman-utama' );
	if ( ! $front ) {
		$front_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Laman Utama', 'post_name' => 'laman-utama', 'post_status' => 'publish' ) );
	} else {
		$front_id = $front->ID;
	}
	$news = get_page_by_path( 'berita' );
	if ( ! $news ) {
		$news_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Berita', 'post_name' => 'berita', 'post_status' => 'publish' ) );
	} else {
		$news_id = $news->ID;
	}
	if ( 'page' !== get_option( 'show_on_front' ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_id );
		update_option( 'page_for_posts', $news_id );
	}

	// Projek Wakaf Khas asal.
	if ( ! get_posts( array( 'post_type' => 'wakaf_khas', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids' ) ) ) {
		foreach ( pws_default_projects() as $i => $d ) {
			$id = wp_insert_post( array(
				'post_type'    => 'wakaf_khas',
				'post_title'   => $d['name'],
				'post_name'    => $d['slug'],
				'post_status'  => 'publish',
				'menu_order'   => $i,
				'post_excerpt' => 'Projek pembinaan ' . $d['name'] . ' di bawah Wakaf Khas Perbadanan Wakaf Selangor.',
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_pws_kos', (string) $d['kos'] );
				$thumb = pws_sideload_theme_image( $d['img'], $id, $d['alt'] );
				if ( $thumb ) {
					set_post_thumbnail( $id, $thumb );
				}
			}
		}
	}

	// Pautan cantik (diperlukan untuk /sumbang-wakaf/, /wakaf-khas/, /berita/).
	if ( '' === get_option( 'permalink_structure' ) ) {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
	}
	flush_rewrite_rules();
}

function pws_on_activate() {
	pws_register_post_types();
	pws_seed_content();
}
add_action( 'after_switch_theme', 'pws_on_activate' );

/* ------------------------------------------------------------------
 * SEO: meta description, Open Graph, JSON-LD organisasi
 * ------------------------------------------------------------------ */
function pws_seo_description() {
	if ( is_front_page() ) {
		return 'Perbadanan Wakaf Selangor mengurus dan membangunkan harta wakaf umat Islam Selangor — wakaf tunai, Wakaf Khas pembinaan masjid dan hartanah wakaf. Sumbang wakaf anda dalam talian.';
	}
	if ( is_page( 'sumbang-wakaf' ) ) {
		return 'Sumbang wakaf dalam talian dengan mudah melalui Perbadanan Wakaf Selangor — sumbangan sekali, Direct Debit bulanan atau JomPAY.';
	}
	if ( is_singular() ) {
		$d = has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 28 );
		return $d;
	}
	return 'Perbadanan Wakaf Selangor — mengurus dan membangunkan harta wakaf di negeri Selangor.';
}

function pws_head_meta() {
	$desc  = wp_strip_all_tags( pws_seo_description() );
	$title = wp_get_document_title();
	$img   = pws_img( 'logoPWSlogo.png' );
	if ( is_singular() && has_post_thumbnail() ) {
		$img = get_the_post_thumbnail_url( null, 'large' );
	}
	echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta name="theme-color" content="#b82020">' . "\n";
	echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:locale" content="ms_MY">' . "\n";
	echo '<meta property="og:site_name" content="Perbadanan Wakaf Selangor">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

	if ( is_front_page() ) {
		$org = array(
			'@context'  => 'https://schema.org',
			'@type'     => 'NGO',
			'name'      => 'Perbadanan Wakaf Selangor',
			'url'       => home_url( '/' ),
			'logo'      => pws_img( 'logoPWSlogo.png' ),
			'telephone' => '+60-3-5518-8001',
			'address'   => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => 'Tingkat 10, Menara Selatan, Bangunan Sultan Idris Shah',
				'postalCode'      => '40000',
				'addressLocality' => 'Shah Alam',
				'addressRegion'   => 'Selangor',
				'addressCountry'  => 'MY',
			),
			'openingHoursSpecification' => array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ),
				'opens'     => '08:30',
				'closes'    => '16:30',
			),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
add_action( 'wp_head', 'pws_head_meta', 5 );
