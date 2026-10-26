<?php
/**
 * Homepage.
 */
get_header();

$projects  = pws_projects( 3 );
$hero_p    = array_pad( $projects, 3, end( $projects ) );
$khas_total = array_sum( wp_list_pluck( $projects, 'kos' ) );
$perf      = pws_performance();

$services = array(
	array( 'icon' => 'coins',    'title' => 'Wakaf Tunai',        'text' => 'Berwakaf mengikut kemampuan anda, bermula daripada jumlah yang kecil.',        'url' => pws_url( 'wakaftunai/' ) ),
	array( 'icon' => 'wallet',   'title' => 'Potongan Gaji',      'text' => 'Wakaf berkala secara automatik melalui potongan gaji bulanan.',                'url' => pws_url( 'produk-wakaf-selangor/' ) ),
	array( 'icon' => 'landmark', 'title' => 'Wakaf Khas',         'text' => 'Sertai projek pembinaan masjid dan prasarana ummah di seluruh Selangor.',        'url' => pws_khas_url() ),
	array( 'icon' => 'search',   'title' => 'Semak Rekod Wakaf',  'text' => 'Semak rekod dan buat sumbangan wakaf anda melalui sistem eWakaf Online.',         'url' => 'https://fpx.wakafselangor.gov.my/ewo_login.asp' ),
);

$fis        = pws_fisabilillah();
$agihan     = $fis['rows'];
$agihan_max = max( 1, max( wp_list_pluck( $agihan, 'amount' ) ) );

$news = new WP_Query( array( 'posts_per_page' => 3, 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
?>

<!-- Hero -->
<?php
// Floating hexagon confetti: [kind, size px, left %, top %, drift seconds, delay seconds]
$confetti = array(
	array( 'pink', 46, 2, 10, 11, 0 ),   array( 'solid', 14, 3, 47, 8, 1 ),    array( 'line', 40, 4, 62, 13, 2 ),
	array( 'gold', 26, 14, 82, 9, .5 ),  array( 'pink', 30, 30, 90, 12, 3 ),   array( 'solid', 10, 46, 6, 7, 1.5 ),
	array( 'line', 30, 58, 3, 12, 2.5 ), array( 'pink', 36, 90, 20, 10, 1 ),   array( 'gold', 22, 96, 52, 8, 0 ),
	array( 'solid', 18, 88, 86, 9, 2 ),  array( 'line', 44, 74, 92, 14, 1 ),   array( 'pink', 24, 60, 94, 10, 3.5 ),
);
?>
<section class="hero" data-hero aria-roledescription="carousel" aria-label="Sorotan utama">
	<div class="hero__bg" aria-hidden="true">
		<?php foreach ( $confetti as $c ) : ?>
			<span class="cf cf--<?php echo esc_attr( $c[0] ); ?>" style="--s:<?php echo (int) $c[1]; ?>px;--l:<?php echo (int) $c[2]; ?>%;--t:<?php echo (int) $c[3]; ?>%;--dur:<?php echo (float) $c[4]; ?>s;--dl:<?php echo (float) $c[5]; ?>s"></span>
		<?php endforeach; ?>
	</div>

	<div class="container hero__stage">
		<div class="hero__slides">

			<!-- Slaid 1: Jenama -->
			<article class="slide is-active" role="group" aria-roledescription="slaid" aria-label="1 daripada 3">
				<div class="slide__copy">
					<p class="slide__kicker">Perbadanan Wakaf Selangor</p>
					<h1 class="slide__title"><span class="t-kick">Berwakaf hari ini,</span><span class="t-light">pahala mengalir</span> <strong>selamanya.</strong></h1>
					<p class="slide__text">Kami mengurus dan membangunkan harta wakaf <b>umat Islam Selangor</b> — daripada wakaf tunai hingga pembinaan masjid — supaya setiap sumbangan terus memberi manfaat kepada masyarakat.</p>
					<div class="slide__cta">
						<a class="btn btn--primary btn--lg" href="<?php echo pws_donate_url(); ?>">Sumbang Wakaf <?php echo pws_icon( 'arrow', 18 ); ?></a>
						<a class="btn btn--outline btn--lg" href="<?php echo pws_url( 'infowakaf/' ); ?>">Kenali Wakaf</a>
					</div>
				</div>

				<div class="slide__visual" aria-hidden="true">
					<div class="hx" style="--w:26;--l:21;--t:0;--z:10;--i:1"><span class="hx__shape"><span class="hx__in"><img src="<?php echo $hero_p[2]['img']; ?>" alt="" width="516" height="318"></span></span><span class="hx__badge hx__badge--gold"><?php echo pws_icon( 'landmark', 16 ); ?></span></div>
					<div class="hx" style="--w:41;--l:46;--t:13;--z:22;--i:0"><span class="hx__shape"><span class="hx__in"><img src="<?php echo $hero_p[1]['img']; ?>" alt="" width="516" height="318" fetchpriority="high"></span></span><span class="hx__badge"><?php echo pws_icon( 'landmark', 18 ); ?></span></div>
					<div class="hx" style="--w:26;--l:15;--t:47;--z:14;--i:2"><span class="hx__shape"><span class="hx__in"><img src="<?php echo $hero_p[0]['img']; ?>" alt="" width="504" height="322"></span></span><span class="hx__badge hx__badge--teal"><?php echo pws_icon( 'landmark', 16 ); ?></span></div>
					<div class="hx" style="--w:31;--l:69;--t:-5;--z:30;--i:3"><span class="hx__shape hx__shape--red"><span class="stat"><small>Kutipan</small><b>RM<span class="count" data-to="<?php echo esc_attr( $perf['kutipan'] ); ?>" data-dec="3"><?php echo esc_html( number_format( $perf['kutipan'], 3 ) ); ?></span></b><i>juta · <?php echo esc_html( $perf['short'] ); ?></i></span></span></div>
					<div class="hx" style="--w:31;--l:64;--t:58;--z:26;--i:4"><span class="hx__shape hx__shape--gold"><span class="stat"><small>Agihan</small><b>RM<span class="count" data-to="<?php echo esc_attr( $perf['agihan'] ); ?>" data-dec="3"><?php echo esc_html( number_format( $perf['agihan'], 3 ) ); ?></span></b><i>juta · <?php echo (int) $perf['pct']; ?>% dikutip</i></span></span></div>
				</div>
			</article>

			<!-- Slaid 2: Wakaf Khas -->
			<article class="slide" role="group" aria-roledescription="slaid" aria-label="2 daripada 3" aria-hidden="true">
				<div class="slide__copy">
					<p class="slide__kicker">Wakaf Khas</p>
					<h2 class="slide__title"><span class="t-kick">Bina masjid,</span><span class="t-light">bina</span> <strong>ummah.</strong></h2>
					<p class="slide__text">Sertai projek <b>Wakaf Khas</b> pembinaan masjid di seluruh Selangor — jumlah kos <?php echo esc_html( pws_juta_label( $khas_total ) ); ?> bagi <?php echo (int) count( $projects ); ?> projek.</p>
					<div class="slide__cta">
						<a class="btn btn--primary btn--lg" href="<?php echo pws_khas_url(); ?>" tabindex="-1">Lihat Wakaf Khas <?php echo pws_icon( 'arrow', 18 ); ?></a>
						<a class="btn btn--outline btn--lg" href="<?php echo pws_donate_url(); ?>" tabindex="-1">Sumbang Wakaf</a>
					</div>
				</div>

				<div class="slide__visual" aria-hidden="true">
					<div class="hx" style="--w:36;--l:32;--t:-2;--z:20;--i:0"><span class="hx__shape"><span class="hx__in"><img src="<?php echo $hero_p[0]['img']; ?>" alt="" width="504" height="322"></span></span><span class="hx__tag"><?php echo esc_html( $hero_p[0]['name'] ); ?> <b><?php echo esc_html( $hero_p[0]['label'] ); ?></b></span></div>
					<div class="hx" style="--w:36;--l:6;--t:38;--z:14;--i:1"><span class="hx__shape"><span class="hx__in"><img src="<?php echo $hero_p[1]['img']; ?>" alt="" width="516" height="318"></span></span><span class="hx__tag"><?php echo esc_html( $hero_p[1]['name'] ); ?> <b><?php echo esc_html( $hero_p[1]['label'] ); ?></b></span></div>
					<div class="hx" style="--w:36;--l:58;--t:38;--z:26;--i:2"><span class="hx__shape"><span class="hx__in"><img src="<?php echo $hero_p[2]['img']; ?>" alt="" width="516" height="318"></span></span><span class="hx__tag"><?php echo esc_html( $hero_p[2]['name'] ); ?> <b><?php echo esc_html( $hero_p[2]['label'] ); ?></b></span></div>
					<div class="hx" style="--w:20;--l:78;--t:6;--z:34;--i:3"><span class="hx__shape hx__shape--red hx__shape--icon"><?php echo pws_icon( 'landmark', 34 ); ?></span></div>
					<div class="hx" style="--w:16;--l:2;--t:12;--z:30;--i:4"><span class="hx__shape hx__shape--gold hx__shape--icon"><?php echo pws_icon( 'coins', 28 ); ?></span></div>
				</div>
			</article>

			<!-- Slaid 3: Hartanah -->
			<article class="slide" role="group" aria-roledescription="slaid" aria-label="3 daripada 3" aria-hidden="true">
				<div class="slide__copy">
					<p class="slide__kicker">Sewaan Hartanah</p>
					<h2 class="slide__title"><span class="t-kick">Hartanah wakaf,</span><span class="t-light">manfaat</span> <strong>berpanjangan.</strong></h2>
					<p class="slide__text">PWS mengalu-alukan <b>permohonan sewaan</b> bagi hartanah wakaf yang sedang diduduki. Hasil sewaan disalurkan semula untuk kebajikan ummah.</p>
					<div class="slide__cta">
						<a class="btn btn--primary btn--lg" href="<?php echo pws_url( 'sewaan/' ); ?>" tabindex="-1">Mohon Sewaan <?php echo pws_icon( 'arrow', 18 ); ?></a>
						<a class="btn btn--outline btn--lg" href="<?php echo pws_url( 'hartanah-wakaf/' ); ?>" tabindex="-1">Info Hartanah</a>
					</div>
				</div>

				<div class="slide__visual" aria-hidden="true">
					<div class="hx" style="--w:52;--l:24;--t:6;--z:20;--i:0"><span class="hx__shape hx__shape--map"><img src="<?php echo pws_img( 'tanah2023.png' ); ?>" alt="" width="700" height="700"></span></div>
					<div class="hx" style="--w:25;--l:1;--t:10;--z:30;--i:1"><span class="hx__shape hx__shape--red"><span class="stat"><b>9</b><i>daerah di Selangor</i></span></span></div>
					<div class="hx" style="--w:22;--l:78;--t:46;--z:34;--i:2"><span class="hx__shape hx__shape--gold hx__shape--icon"><?php echo pws_icon( 'pin', 32 ); ?></span></div>
					<div class="hx" style="--w:20;--l:12;--t:58;--z:26;--i:3"><span class="hx__shape hx__shape--dark hx__shape--icon"><?php echo pws_icon( 'landmark', 30 ); ?></span></div>
				</div>
			</article>

		</div>

		<div class="hero__controls">
			<div class="dots" role="tablist" aria-label="Pilih slaid">
				<button type="button" class="dot is-active" role="tab" aria-selected="true" aria-label="Slaid 1: Berwakaf hari ini"><span></span></button>
				<button type="button" class="dot" role="tab" aria-selected="false" aria-label="Slaid 2: Wakaf Khas"><span></span></button>
				<button type="button" class="dot" role="tab" aria-selected="false" aria-label="Slaid 3: Hartanah Wakaf"><span></span></button>
			</div>
			<button type="button" class="hero__pause" aria-label="Jeda slaid" data-state="playing">
				<svg class="ic-pause" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="5" y="4" width="5" height="16" rx="1"/><rect x="14" y="4" width="5" height="16" rx="1"/></svg>
				<svg class="ic-play" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12-7.5a1 1 0 0 0 0-1.72l-12-7.5A1 1 0 0 0 7 4.5z"/></svg>
			</button>
		</div>
	</div>

	<div class="ornament" aria-hidden="true">
		<span>Perbadanan Wakaf</span>
		<span class="ornament__badge"><img src="<?php echo pws_img( 'logoPWSlogo.png' ); ?>" alt="" width="40" height="35"></span>
		<span>Selangor</span>
	</div>
</section>

<!-- Services -->
<section class="services" aria-labelledby="services-title">
	<div class="container">
		<h2 id="services-title" class="screen-reader-text">Perkhidmatan utama</h2>
		<ul class="services__grid">
			<?php foreach ( $services as $s ) : ?>
				<li class="reveal">
					<a class="service-card" href="<?php echo esc_url( $s['url'] ); ?>">
						<span class="service-card__icon"><?php echo pws_icon( $s['icon'], 26 ); ?></span>
						<h3><?php echo esc_html( $s['title'] ); ?></h3>
						<p><?php echo esc_html( $s['text'] ); ?></p>
						<span class="service-card__more">Selanjutnya <?php echo pws_icon( 'arrow', 16 ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<!-- Wakaf Khas -->
<?php
// Hexagon positions (% of the tile field): left, top, width, parallax depth.
$khas_pos = array(
	array( 6.5, 0, 27, 0 ), array( 35, 0, 27, 1 ), array( 63.5, 0, 27, 2 ),
);
$khas_cf = array(
	array( 'pink', 44, 1, 8, 12, 0 ),   array( 'solid', 14, 4, 46, 8, 1 ),   array( 'line', 34, 2, 78, 13, 2 ),
	array( 'gold', 24, 12, 94, 9, .5 ), array( 'pink', 30, 94, 14, 10, 1 ),  array( 'solid', 12, 97, 44, 7, 2 ),
	array( 'gold', 26, 95, 70, 9, 0 ),  array( 'line', 40, 88, 92, 14, 1 ),  array( 'pink', 22, 50, 97, 11, 3 ),
	array( 'solid', 10, 50, 3, 8, 1 ),  array( 'line', 26, 70, 2, 12, 2 ),
);
?>
<section class="section khas" aria-labelledby="khas-title">
	<div class="khas__bg" aria-hidden="true">
		<?php foreach ( $khas_cf as $c ) : ?>
			<span class="cf cf--<?php echo esc_attr( $c[0] ); ?>" style="--s:<?php echo (int) $c[1]; ?>px;--l:<?php echo (int) $c[2]; ?>%;--t:<?php echo (int) $c[3]; ?>%;--dur:<?php echo (float) $c[4]; ?>s;--dl:<?php echo (float) $c[5]; ?>s"></span>
		<?php endforeach; ?>
	</div>

	<div class="container">
		<header class="khas__head reveal">
			<p class="eyebrow">Wakaf Khas</p>
			<h2 id="khas-title" class="khas__title"><span>Projek masjid</span> <strong>yang sedang dibangunkan</strong></h2>
			<p class="khas__lead">Projek Wakaf Khas pembinaan masjid di Selangor yang menerima sumbangan wakaf anda.</p>
		</header>

		<div class="khas__tiles reveal">
			<?php foreach ( $projects as $i => $p ) : ?>
				<a class="hx hx--link" href="<?php echo esc_url( $p['link'] ); ?>" style="--l:<?php echo (float) $khas_pos[ $i ][0]; ?>;--t:<?php echo (float) $khas_pos[ $i ][1]; ?>;--w:<?php echo (float) $khas_pos[ $i ][2]; ?>;--z:<?php echo 10 + $i * 8; ?>;--i:<?php echo (int) $i; ?>">
					<span class="hx__shape"><span class="hx__in"><img src="<?php echo esc_url( $p['img'] ); ?>" alt="<?php echo esc_attr( $p['alt'] ); ?>" width="516" height="318" loading="lazy"></span></span>
					<span class="hx__tag"><?php echo esc_html( $p['name'] ); ?> <b><?php echo esc_html( $p['label'] ); ?></b></span>
				</a>
			<?php endforeach; ?>

			<div class="hx" style="--l:20.75;--t:42.9;--w:27;--z:24;--i:3">
				<span class="hx__shape hx__shape--red">
					<span class="stat stat--lg"><small>Jumlah kos <?php echo (int) count( $projects ); ?> projek</small><b>RM<?php echo esc_html( number_format( $khas_total, 2 ) ); ?></b><i>juta</i></span>
				</span>
			</div>
			<a class="hx hx--link" href="<?php echo pws_donate_url(); ?>" style="--l:49.25;--t:42.9;--w:27;--z:30;--i:4" aria-label="Sumbang Wakaf Khas">
				<span class="hx__shape hx__shape--gold">
					<span class="stat stat--lg"><?php echo pws_icon( 'coins', 34 ); ?><b class="stat__cta">Sumbang<br>Wakaf Khas</b></span>
				</span>
			</a>
		</div>

		<p class="khas__more reveal"><a class="btn btn--primary btn--lg" href="<?php echo pws_khas_url(); ?>">Lihat Semua Projek <?php echo pws_icon( 'arrow', 18 ); ?></a></p>
	</div>
</section>

<!-- eWakaf Online -->
<section class="ewo" aria-labelledby="ewo-title">
	<div class="ewo__art" aria-hidden="true">
		<svg class="fcta__hex " style="left:-3%;top:-34%;width:260px;--dur:16s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
		<svg class="fcta__hex " style="left:34%;bottom:-40%;width:200px;--dur:13s;--dl:-3s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
		<svg class="fcta__hex " style="right:8%;top:-30%;width:240px;--dur:14s;--dl:-6s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
		<svg class="fcta__hex fcta__hex--fill" style="right:30%;top:18%;width:40px;--dur:9s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
		<svg class="fcta__hex fcta__hex--fill" style="left:24%;top:70%;width:28px;--dur:10s;--dl:-2s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
	</div>
	<div class="container ewo__inner">
		<span class="ewo__badge" aria-hidden="true"><span class="hx__shape hx__shape--gold hx__shape--icon"><?php echo pws_icon( 'search', 40 ); ?></span></span>
		<div class="ewo__copy">
			<p class="eyebrow eyebrow--gold">Sistem eWAKAF Online (EWO)</p>
			<h2 id="ewo-title" class="ewo__title">Semak rekod dan buat sumbangan wakaf dalam talian.</h2>
			<p>Pewakaf berdaftar boleh menyemak rekod dan membuat sumbangan bila-bila masa melalui sistem EWO.</p>
		</div>
		<div class="ewo__actions">
			<a class="btn btn--gold btn--lg" href="https://fpx.wakafselangor.gov.my/ewo_login.asp" rel="noopener">Log Masuk</a>
			<a class="btn btn--outline-light btn--lg" href="https://fpx.wakafselangor.gov.my/ewo_daftar.asp" rel="noopener">Daftar EWO</a>
		</div>
	</div>
</section>

<!-- Produk Wakaf -->
<?php
// Sumber: halaman Produk Wakaf Selangor, wakafselangor.gov.my. [nama, ikon, keterangan]
$products = array(
	array( 'Wakaf Tunai',             'coins',     'Apa-apa sumbangan wang tunai, nilaian emas atau yang seumpama dengannya daripada mana-mana orang bagi apa-apa maksud wakaf.' ),
	array( 'Wakaf Saham',             'trend',     'Harta yang diperoleh atau dibangunkan daripada hasil jualan saham, bon, sukuk atau surat cara lain bagi jaminan berharga.' ),
	array( 'Wakaf Wasiat',            'file',      'Mewakafkan harta berbentuk tunai, emas mahupun hartanah yang mula berkuat kuasa selepas kematian.' ),
	array( 'Wakaf Emas',              'gem',       'Berwakaf dengan aset yang disimpan seperti barang kemas, kepingan emas dan lain-lain.' ),
	array( 'Wakaf Takaful',           'shield',    'Lihat butiran Wakaf Takaful pada halaman produk wakaf.' ),
	array( 'Wakaf Harta Intelek',     'book',      'Apa-apa yang berkaitan dengan hak cipta, paten, reka bentuk perindustrian, cakera optik, cap dagangan dan yang seumpama dengannya.' ),
	array( 'Wakaf Ilmu',              'school',    'Kerjasama antara PWS dan Institut Pengajian Tinggi awam/swasta untuk manfaat pendidikan dan pembangunan IPT berkenaan.' ),
	array( 'Wakaf Selangor Muamalat', 'handshake', 'Kerjasama PWS dan Bank Muamalat Malaysia Berhad bersama orang awam untuk mewakafkan harta bagi pengagihan kepada agensi/institusi di dalam dan luar Selangor untuk kesihatan, pendidikan dan pelaburan.' ),
	array( 'Wakaf Perkhidmatan',      'building',  'Kepakaran dan perkhidmatan yang mempunyai nilai mengikut Hukum Syarak.' ),
	array( 'Wakaf Irsod',             'landmark',  'Tanah yang diwakafkan oleh mana-mana perbadanan, institusi, pertubuhan atau badan lain mengikut Hukum Syarak.' ),
	array( 'Wakaf Taklik',            'key',       'Boleh diwujudkan oleh sesiapa dan berkuat kuasa apabila berlakunya sesuatu kejadian yang disyaratkan oleh waqif.' ),
);
// Susunan sarang lebah 4 / 3 / 4 — [lajur, baris] bagi setiap produk.
$prod_rows = array( 4, 3, 4 );
$prod_pos  = array();
foreach ( $prod_rows as $r => $cols ) {
	for ( $c = 0; $c < $cols; $c++ ) {
		$prod_pos[] = array( 'l' => ( 1 === $r ? 14.5 : 2.5 ) + $c * 24, 't' => $r * 30.6, 'col' => $c, 'cols' => $cols );
	}
}
?>
<section class="section prod" aria-labelledby="prod-title">
	<div class="container">
		<header class="khas__head reveal">
			<p class="eyebrow">Produk Wakaf</p>
			<h2 id="prod-title" class="khas__title"><span>Pilih cara anda</span> <strong>berwakaf.</strong></h2>
			<p class="khas__lead"><?php echo (int) count( $products ); ?> produk wakaf Perbadanan Wakaf Selangor.<span class="only-pointer"> Arahkan tetikus atau fokus pada setiap heksagon untuk keterangan.</span></p>
		</header>

		<div class="prod__tiles reveal">
			<?php foreach ( $products as $i => $pr ) :
				$pos = $prod_pos[ $i ];
				$tip = 0 === $pos['col'] ? ' prod__desc--start' : ( $pos['col'] === $pos['cols'] - 1 ? ' prod__desc--end' : '' );
				?>
				<a class="hx hx--link prod__tile" href="<?php echo pws_url( 'produk-wakaf-selangor/' ); ?>" style="--l:<?php echo (float) $pos['l']; ?>;--t:<?php echo (float) $pos['t']; ?>;--w:22.5;--z:<?php echo 8 + ( $i % 4 ) * 6; ?>;--i:<?php echo (int) $i; ?>">
					<span class="hx__shape <?php echo 0 === $i ? 'hx__shape--gold' : 'hx__shape--red'; ?>"><span class="stat"><?php echo pws_icon( $pr[1], 30 ); ?><b><?php echo esc_html( $pr[0] ); ?></b></span></span>
					<span class="prod__desc<?php echo esc_attr( $tip ); ?>"><strong><?php echo esc_html( $pr[0] ); ?></strong><span><?php echo esc_html( $pr[2] ); ?></span></span>
				</a>
			<?php endforeach; ?>
		</div>

		<p class="khas__more reveal"><a class="btn btn--primary btn--lg" href="<?php echo pws_url( 'produk-wakaf-selangor/' ); ?>">Lihat Semua Produk Wakaf <?php echo pws_icon( 'arrow', 18 ); ?></a></p>
	</div>
</section>

<!-- Agihan -->
<?php $ag_icons = array( 'sekolah' => 'school', 'surau' => 'home', 'masjid' => 'landmark', 'lain-lain' => 'building' ); ?>
<section class="section section--sand agihan-sec" aria-labelledby="agihan-title">
	<div class="container agihan">
		<div class="agihan__intro reveal">
			<p class="eyebrow">Sumbangan Fisabilillah <?php echo esc_html( $fis['year'] ); ?></p>
			<h2 id="agihan-title" class="section__title">Wakaf yang sampai kepada masyarakat</h2>
			<div class="agihan__hex" role="img" aria-label="Jumlah agihan <?php echo esc_attr( $fis['year'] ); ?>: RM<?php echo esc_attr( number_format( $fis['total'] ) ); ?>">
				<span class="hx__shape hx__shape--red"><span class="stat"><small>Jumlah agihan <?php echo esc_html( $fis['year'] ); ?></small><b>RM<?php echo esc_html( number_format( $fis['total'] ) ); ?></b><i>Fisabilillah</i></span></span>
			</div>
			<p class="muted">Disalurkan kepada masjid, surau, sekolah dan institusi di seluruh Selangor.</p>
		</div>

		<ul class="bars reveal">
			<?php foreach ( $agihan as $a ) :
				$ic = isset( $ag_icons[ strtolower( $a['label'] ) ] ) ? $ag_icons[ strtolower( $a['label'] ) ] : 'coins';
				?>
				<li class="bars__row">
					<span class="hexi hexi--sm"><?php echo pws_icon( $ic, 18 ); ?></span>
					<div class="bars__body">
						<div class="bars__meta">
							<span class="bars__label"><?php echo esc_html( $a['label'] ); ?> <small><?php echo esc_html( $a['count'] ); ?></small></span>
							<span class="bars__value">RM<?php echo esc_html( number_format( $a['amount'] ) ); ?></span>
						</div>
						<div class="bars__track"><div class="bars__fill" style="--w: <?php echo esc_attr( round( $a['amount'] / $agihan_max * 100, 1 ) ); ?>%"></div></div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<!-- Hartanah -->
<?php
// Taburan hartanah wakaf — data dari Dashboard > Data Wakaf.
// [no. pada peta, daerah, keluasan (ekar), pendaftaran, x% pada peta, y% pada peta]
$hart      = pws_hartanah();
$districts = $hart['rows'];
$max_ekar  = max( 0.01, max( wp_list_pluck( $districts, 2 ) ) );
$max_reg   = max( 1, max( wp_list_pluck( $districts, 3 ) ) );
$default_district = 7;
?>
<section class="section hart" data-hart data-metric="ekar" aria-labelledby="hartanah-title">
	<div class="hart__bg" aria-hidden="true">
		<span class="cf cf--pink"  style="--s:44px;--l:2%;--t:6%;--dur:12s;--dl:0s"></span>
		<span class="cf cf--solid" style="--s:12px;--l:5%;--t:52%;--dur:8s;--dl:1s"></span>
		<span class="cf cf--gold"  style="--s:24px;--l:3%;--t:86%;--dur:9s;--dl:.5s"></span>
		<span class="cf cf--pink"  style="--s:32px;--l:95%;--t:12%;--dur:10s;--dl:1s"></span>
		<span class="cf cf--line"  style="--s:38px;--l:96%;--t:48%;--dur:13s;--dl:2s"></span>
		<span class="cf cf--solid" style="--s:14px;--l:93%;--t:90%;--dur:9s;--dl:2s"></span>
	</div>

	<div class="container">
		<header class="hart__head reveal">
			<p class="eyebrow">Hartanah Wakaf</p>
			<h2 id="hartanah-title" class="hart__title"><span>Taburan hartanah wakaf</span> <strong>Tahun <?php echo esc_html( $hart['year'] ); ?></strong></h2>
			<p class="hart__lead">Keluasan dan bilangan pendaftaran hartanah wakaf di sembilan daerah Selangor, <?php echo esc_html( $hart['period'] ); ?>.</p>
		</header>

		<div class="hart__grid">
			<div class="hart__side reveal">
				<div class="hart__map">
					<div class="hart__mapwrap">
						<img src="<?php echo pws_img( 'tanah2023.png' ); ?>" alt="Peta Selangor menunjukkan sembilan daerah bernombor 1 hingga 9" width="700" height="700" loading="lazy">
						<?php foreach ( $districts as $d ) :
							$tip = $d[4] > 68 ? ' tip--end' : ( $d[4] < 36 ? ' tip--start' : '' );
							?>
							<button type="button" class="spot<?php echo $d[0] === $default_district ? ' is-active' : ''; ?>" data-i="<?php echo (int) $d[0]; ?>" style="--x:<?php echo (float) $d[4]; ?>%;--y:<?php echo (float) $d[5]; ?>%" tabindex="-1" aria-hidden="true">
								<span class="spot__ring"></span>
								<span class="tip<?php echo esc_attr( $tip ); ?>"><b><?php echo esc_html( $d[1] ); ?></b><span><?php echo esc_html( number_format( $d[2], 2 ) ); ?> ekar · <?php echo (int) $d[3]; ?> pendaftaran</span></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="hart__totals">
					<div class="tot tot--e">
						<span class="hexi"><?php echo pws_icon( 'pin', 20 ); ?></span>
						<div><span class="tot__label">Jumlah keluasan</span><b class="tot__num"><span class="count" data-to="<?php echo esc_attr( $hart['ekar'] ); ?>" data-dec="2"><?php echo esc_html( number_format( $hart['ekar'], 2 ) ); ?></span> <small>ekar</small></b></div>
					</div>
					<div class="tot tot--p">
						<span class="hexi"><?php echo pws_icon( 'search', 20 ); ?></span>
						<div><span class="tot__label">Jumlah pendaftaran</span><b class="tot__num"><span class="count" data-to="<?php echo esc_attr( $hart['daftar'] ); ?>" data-dec="0"><?php echo (int) $hart['daftar']; ?></span> <small>pendaftaran</small></b></div>
					</div>
				</div>

			</div>

			<div class="hart__panel reveal">
				<div class="seg" role="group" aria-label="Bandingkan daerah mengikut">
					<button type="button" data-metric="ekar" aria-pressed="true">Keluasan (ekar)</button>
					<button type="button" data-metric="pendaftaran" aria-pressed="false">Pendaftaran</button>
				</div>

				<ol class="hart__list">
					<?php foreach ( $districts as $d ) : ?>
						<li class="drow<?php echo $d[0] === $default_district ? ' is-active' : ''; ?>" data-i="<?php echo (int) $d[0]; ?>" style="--pe:<?php echo esc_attr( round( $d[2] / $max_ekar * 100, 1 ) ); ?>;--pp:<?php echo esc_attr( round( $d[3] / $max_reg * 100, 1 ) ); ?>">
							<button type="button" class="drow__btn" aria-label="<?php echo esc_attr( $d[0] . '. ' . $d[1] . ': ' . number_format( $d[2], 2 ) . ' ekar, ' . $d[3] . ' pendaftaran' ); ?>">
								<span class="hexi hexi--sm"><?php echo (int) $d[0]; ?></span>
								<span class="drow__main">
									<span class="drow__name"><?php echo esc_html( $d[1] ); ?></span>
									<span class="drow__track"><span class="drow__fill"></span></span>
								</span>
								<span class="drow__vals">
									<span class="drow__v drow__v--e"><b><?php echo esc_html( number_format( $d[2], 2 ) ); ?></b> ekar</span>
									<span class="drow__v drow__v--p"><b><?php echo (int) $d[3]; ?></b> pendaftaran</span>
								</span>
							</button>
						</li>
					<?php endforeach; ?>
				</ol>
				<p class="hart__note">Jumlah keseluruhan bagi tahun <?php echo esc_html( $hart['year'] ); ?> (<?php echo esc_html( $hart['period'] ); ?>).</p>
			</div>
		</div>

		<div class="hart__cta reveal">
			<p><strong>Hartanah wakaf yang sedang diduduki?</strong> PWS mengalu-alukan permohonan sewaan. Hasil sewaan disalurkan semula untuk kebajikan ummah.</p>
			<div class="hart__cta-btns">
				<a class="btn btn--primary" href="<?php echo pws_url( 'sewaan/' ); ?>">Mohon Sewaan</a>
				<a class="btn btn--outline" href="<?php echo pws_url( 'hartanah-wakaf/' ); ?>">Info Hartanah Wakaf</a>
			</div>
		</div>
	</div>
</section>

<?php if ( $news->have_posts() ) : ?>
<!-- Berita -->
<section class="section section--sand" aria-labelledby="berita-title">
	<div class="container">
		<header class="section__head reveal">
			<div>
				<p class="eyebrow">Berita Terkini</p>
				<h2 id="berita-title" class="section__title">Aktiviti dan perkembangan terbaru</h2>
			</div>
			<a class="link-arrow" href="<?php echo pws_news_url(); ?>">Semua berita <?php echo pws_icon( 'arrow', 16 ); ?></a>
		</header>
		<ul class="news-grid">
			<?php while ( $news->have_posts() ) : $news->the_post(); ?>
				<li class="reveal">
					<a class="news-card" href="<?php the_permalink(); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<span class="news-card__img"><?php the_post_thumbnail( 'pws-card', array( 'loading' => 'lazy', 'alt' => '' ) ); ?></span>
						<?php endif; ?>
						<span class="news-card__body">
							<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
							<h3><?php the_title(); ?></h3>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
						</span>
					</a>
				</li>
			<?php endwhile; wp_reset_postdata(); ?>
		</ul>
	</div>
</section>
<?php endif; ?>

<?php
get_footer();
