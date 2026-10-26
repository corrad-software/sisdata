<?php
/**
 * Data yang boleh dikemas kini oleh kakitangan melalui Dashboard > Data Wakaf.
 * Nilai asal di bawah diambil daripada portal wakafselangor.gov.my.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PWS_DATA_OPTION = 'pws_data';

function pws_default_data() {
	return array(
		'periode'            => 'Januari – Mac 2026',
		'periode_pendek'     => 'Jan–Mac 2026',
		'kutipan'            => 17.706,   // juta RM
		'agihan'             => 14.187,   // juta RM
		'fisabilillah_tahun' => '2025',
		'fisabilillah'       => array(
			array( 'label' => 'Sekolah',   'amount' => 1946360, 'count' => '55 sekolah' ),
			array( 'label' => 'Surau',     'amount' => 1055400, 'count' => '30 surau' ),
			array( 'label' => 'Masjid',    'amount' => 648393,  'count' => '15 masjid' ),
			array( 'label' => 'Lain-lain', 'amount' => 426720,  'count' => '10 institusi' ),
		),
		'hartanah_tahun'     => '2024',
		'hartanah_tempoh'    => 'sehingga November 2024',
		'jumlah_ekar'        => '68.67',  // kosongkan = jumlahkan secara automatik
		'jumlah_daftar'      => '97',     // kosongkan = jumlahkan secara automatik
		'daerah'             => array(
			1 => array( 19.36, 12 ), 2 => array( 5.07, 4 ),  3 => array( 5.16, 6 ),
			4 => array( 1.14, 3 ),   5 => array( 0.96, 4 ),  6 => array( 1.16, 6 ),
			7 => array( 22.61, 38 ), 8 => array( 12.28, 15 ), 9 => array( 0.94, 9 ),
		),
	);
}

/** Nama daerah dan kedudukan penanda (% pada imej peta) — tetap, mengikut imej peta. */
function pws_districts_base() {
	return array(
		1 => array( 'Sabak Bernam',  31.36, 24.64 ),
		2 => array( 'Hulu Selangor', 62.71, 28.50 ),
		3 => array( 'Kuala Selangor', 43.29, 36.21 ),
		4 => array( 'Gombak',        64.29, 44.93 ),
		5 => array( 'Klang',         50.50, 56.86 ),
		6 => array( 'Petaling',      62.36, 54.36 ),
		7 => array( 'Kuala Langat',  57.21, 69.93 ),
		8 => array( 'Sepang',        71.36, 74.29 ),
		9 => array( 'Hulu Langat',   82.00, 57.29 ),
	);
}

/** Data semasa: nilai tersimpan digabungkan dengan nilai asal. */
function pws_data( $key = null ) {
	static $data = null;
	if ( null === $data ) {
		$saved = get_option( PWS_DATA_OPTION, array() );
		$data  = is_array( $saved ) ? array_replace_recursive( pws_default_data(), $saved ) : pws_default_data();
	}
	if ( null === $key ) {
		return $data;
	}
	return isset( $data[ $key ] ) ? $data[ $key ] : null;
}

/** Prestasi kutipan & agihan. */
function pws_performance() {
	$k   = (float) pws_data( 'kutipan' );
	$a   = (float) pws_data( 'agihan' );
	$pct = $k > 0 ? (int) round( $a / $k * 100 ) : 0;
	return array(
		'periode' => pws_data( 'periode' ),
		'short'   => pws_data( 'periode_pendek' ),
		'kutipan' => $k,
		'agihan'  => $a,
		'pct'     => max( 0, min( 100, $pct ) ),
	);
}

/** Agihan Fisabilillah: baris dan jumlah. */
function pws_fisabilillah() {
	$rows  = (array) pws_data( 'fisabilillah' );
	$total = 0;
	foreach ( $rows as $r ) {
		$total += (float) $r['amount'];
	}
	return array( 'year' => pws_data( 'fisabilillah_tahun' ), 'rows' => $rows, 'total' => $total );
}

/** Taburan hartanah: setiap daerah [no, nama, ekar, pendaftaran, x, y] serta jumlah. */
function pws_hartanah() {
	$base  = pws_districts_base();
	$vals  = (array) pws_data( 'daerah' );
	$rows  = array();
	$ekar  = 0;
	$daftar = 0;
	foreach ( $base as $no => $b ) {
		$v      = isset( $vals[ $no ] ) ? $vals[ $no ] : array( 0, 0 );
		$rows[] = array( $no, $b[0], (float) $v[0], (int) $v[1], $b[1], $b[2] );
		$ekar  += (float) $v[0];
		$daftar += (int) $v[1];
	}
	$t_ekar   = trim( (string) pws_data( 'jumlah_ekar' ) );
	$t_daftar = trim( (string) pws_data( 'jumlah_daftar' ) );
	return array(
		'rows'    => $rows,
		'ekar'    => '' !== $t_ekar ? (float) $t_ekar : round( $ekar, 2 ),
		'daftar'  => '' !== $t_daftar ? (int) $t_daftar : $daftar,
		'year'    => pws_data( 'hartanah_tahun' ),
		'period'  => pws_data( 'hartanah_tempoh' ),
	);
}

/** "RM19 juta", "RM12.2 juta", "RM11.15 juta". */
function pws_juta_label( $value ) {
	$s = rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' );
	return 'RM' . $s . ' juta';
}

/* ------------------------------------------------------------------
 * Skrin admin
 * ------------------------------------------------------------------ */

function pws_data_menu() {
	add_menu_page( 'Data Wakaf', 'Data Wakaf', 'manage_options', 'pws-data', 'pws_data_page', 'dashicons-chart-bar', 26 );
}
add_action( 'admin_menu', 'pws_data_menu' );

function pws_data_register() {
	register_setting( 'pws_data_group', PWS_DATA_OPTION, array( 'type' => 'array', 'sanitize_callback' => 'pws_data_sanitize', 'default' => array() ) );
}
add_action( 'admin_init', 'pws_data_register' );

function pws_data_sanitize( $in ) {
	$def = pws_default_data();
	$in  = is_array( $in ) ? wp_unslash( $in ) : array();
	$txt = function ( $k ) use ( $in, $def ) {
		return isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : $def[ $k ];
	};
	$num = function ( $v, $d ) {
		return is_numeric( $v ) && $v >= 0 ? (float) $v : $d;
	};
	$out = array(
		'periode'            => $txt( 'periode' ),
		'periode_pendek'     => $txt( 'periode_pendek' ),
		'kutipan'            => $num( isset( $in['kutipan'] ) ? $in['kutipan'] : '', $def['kutipan'] ),
		'agihan'             => $num( isset( $in['agihan'] ) ? $in['agihan'] : '', $def['agihan'] ),
		'fisabilillah_tahun' => $txt( 'fisabilillah_tahun' ),
		'hartanah_tahun'     => $txt( 'hartanah_tahun' ),
		'hartanah_tempoh'    => $txt( 'hartanah_tempoh' ),
		'jumlah_ekar'        => isset( $in['jumlah_ekar'] ) && '' !== trim( $in['jumlah_ekar'] ) && is_numeric( $in['jumlah_ekar'] ) ? (string) (float) $in['jumlah_ekar'] : '',
		'jumlah_daftar'      => isset( $in['jumlah_daftar'] ) && '' !== trim( $in['jumlah_daftar'] ) && is_numeric( $in['jumlah_daftar'] ) ? (string) (int) $in['jumlah_daftar'] : '',
		'fisabilillah'       => array(),
		'daerah'             => array(),
	);
	foreach ( $def['fisabilillah'] as $i => $row ) {
		$r = isset( $in['fisabilillah'][ $i ] ) ? $in['fisabilillah'][ $i ] : array();
		$out['fisabilillah'][ $i ] = array(
			'label'  => isset( $r['label'] ) && '' !== trim( $r['label'] ) ? sanitize_text_field( $r['label'] ) : $row['label'],
			'amount' => isset( $r['amount'] ) && is_numeric( $r['amount'] ) && $r['amount'] >= 0 ? (float) $r['amount'] : $row['amount'],
			'count'  => isset( $r['count'] ) ? sanitize_text_field( $r['count'] ) : $row['count'],
		);
	}
	foreach ( $def['daerah'] as $no => $row ) {
		$r = isset( $in['daerah'][ $no ] ) ? $in['daerah'][ $no ] : array();
		$out['daerah'][ $no ] = array(
			isset( $r[0] ) && is_numeric( $r[0] ) && $r[0] >= 0 ? (float) $r[0] : $row[0],
			isset( $r[1] ) && is_numeric( $r[1] ) && $r[1] >= 0 ? (int) $r[1] : $row[1],
		);
	}
	return $out;
}

function pws_data_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$d    = pws_data();
	$name = PWS_DATA_OPTION;
	$base = pws_districts_base();
	?>
	<div class="wrap pws-data">
		<h1>Data Wakaf</h1>
		<?php settings_errors(); ?>
		<p>Kemas kini angka yang dipaparkan di laman utama. Perubahan terus dipaparkan selepas disimpan. Projek Wakaf Khas diurus di menu <strong>Wakaf Khas</strong>.</p>
		<style>
			.pws-data .card { max-width: 760px; margin: 16px 0; padding: 4px 20px 16px; }
			.pws-data table.form-table th { width: 220px; }
			.pws-data .grid { border-collapse: collapse; width: 100%; }
			.pws-data .grid th, .pws-data .grid td { padding: 6px 8px 6px 0; text-align: left; vertical-align: middle; }
			.pws-data .grid input[type=number] { width: 120px; }
			.pws-data .small-text { width: 100px; }
			.pws-data .hint { color: #646970; font-size: 12px; margin-top: 4px; }
		</style>
		<form method="post" action="options.php">
			<?php settings_fields( 'pws_data_group' ); ?>

			<div class="card">
				<h2>Prestasi kutipan &amp; agihan</h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="pd-periode">Tempoh (penuh)</label></th><td><input id="pd-periode" class="regular-text" name="<?php echo esc_attr( $name ); ?>[periode]" value="<?php echo esc_attr( $d['periode'] ); ?>"></td></tr>
					<tr><th><label for="pd-pendek">Tempoh (ringkas)</label></th><td><input id="pd-pendek" class="regular-text" name="<?php echo esc_attr( $name ); ?>[periode_pendek]" value="<?php echo esc_attr( $d['periode_pendek'] ); ?>"><p class="hint">Digunakan dalam heksagon kecil, cth. Jan–Mac 2026.</p></td></tr>
					<tr><th><label for="pd-kutipan">Kutipan (juta RM)</label></th><td><input id="pd-kutipan" type="number" step="0.001" min="0" name="<?php echo esc_attr( $name ); ?>[kutipan]" value="<?php echo esc_attr( $d['kutipan'] ); ?>"></td></tr>
					<tr><th><label for="pd-agihan">Agihan (juta RM)</label></th><td><input id="pd-agihan" type="number" step="0.001" min="0" name="<?php echo esc_attr( $name ); ?>[agihan]" value="<?php echo esc_attr( $d['agihan'] ); ?>"><p class="hint">Peratus agihan daripada kutipan dikira secara automatik.</p></td></tr>
				</table>
			</div>

			<div class="card">
				<h2>Agihan Fisabilillah</h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="pd-fyear">Tahun</label></th><td><input id="pd-fyear" class="small-text" name="<?php echo esc_attr( $name ); ?>[fisabilillah_tahun]" value="<?php echo esc_attr( $d['fisabilillah_tahun'] ); ?>"></td></tr>
				</table>
				<table class="grid">
					<tr><th>Kategori</th><th>Jumlah (RM)</th><th>Penerima</th></tr>
					<?php foreach ( $d['fisabilillah'] as $i => $r ) : ?>
						<tr>
							<td><input name="<?php echo esc_attr( $name ); ?>[fisabilillah][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $r['label'] ); ?>"></td>
							<td><input type="number" step="1" min="0" name="<?php echo esc_attr( $name ); ?>[fisabilillah][<?php echo (int) $i; ?>][amount]" value="<?php echo esc_attr( $r['amount'] ); ?>"></td>
							<td><input name="<?php echo esc_attr( $name ); ?>[fisabilillah][<?php echo (int) $i; ?>][count]" value="<?php echo esc_attr( $r['count'] ); ?>"></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p class="hint">Jumlah keseluruhan dikira secara automatik daripada baris di atas.</p>
			</div>

			<div class="card">
				<h2>Taburan hartanah wakaf</h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="pd-htahun">Tahun</label></th><td><input id="pd-htahun" class="small-text" name="<?php echo esc_attr( $name ); ?>[hartanah_tahun]" value="<?php echo esc_attr( $d['hartanah_tahun'] ); ?>"></td></tr>
					<tr><th><label for="pd-htempoh">Tempoh</label></th><td><input id="pd-htempoh" class="regular-text" name="<?php echo esc_attr( $name ); ?>[hartanah_tempoh]" value="<?php echo esc_attr( $d['hartanah_tempoh'] ); ?>"><p class="hint">Cth. sehingga November 2024.</p></td></tr>
					<tr><th><label for="pd-jekar">Jumlah keluasan (ekar)</label></th><td><input id="pd-jekar" class="small-text" name="<?php echo esc_attr( $name ); ?>[jumlah_ekar]" value="<?php echo esc_attr( $d['jumlah_ekar'] ); ?>"><p class="hint">Kosongkan untuk jumlahkan secara automatik.</p></td></tr>
					<tr><th><label for="pd-jdaftar">Jumlah pendaftaran</label></th><td><input id="pd-jdaftar" class="small-text" name="<?php echo esc_attr( $name ); ?>[jumlah_daftar]" value="<?php echo esc_attr( $d['jumlah_daftar'] ); ?>"><p class="hint">Kosongkan untuk jumlahkan secara automatik.</p></td></tr>
				</table>
				<table class="grid">
					<tr><th>#</th><th>Daerah</th><th>Keluasan (ekar)</th><th>Pendaftaran</th></tr>
					<?php foreach ( $base as $no => $b ) :
						$v = isset( $d['daerah'][ $no ] ) ? $d['daerah'][ $no ] : array( 0, 0 );
						?>
						<tr>
							<td><?php echo (int) $no; ?></td>
							<td><strong><?php echo esc_html( $b[0] ); ?></strong></td>
							<td><input type="number" step="0.01" min="0" name="<?php echo esc_attr( $name ); ?>[daerah][<?php echo (int) $no; ?>][0]" value="<?php echo esc_attr( $v[0] ); ?>"></td>
							<td><input type="number" step="1" min="0" name="<?php echo esc_attr( $name ); ?>[daerah][<?php echo (int) $no; ?>][1]" value="<?php echo esc_attr( $v[1] ); ?>"></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>

			<?php submit_button( 'Simpan perubahan' ); ?>
		</form>
	</div>
	<?php
}
