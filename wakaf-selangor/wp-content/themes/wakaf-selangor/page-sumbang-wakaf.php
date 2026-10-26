<?php
/**
 * Halaman Sumbang Wakaf — wizard langkah demi langkah.
 * Dimuatkan automatik untuk halaman dengan slug "sumbang-wakaf".
 */
get_header();

$confetti = array(
	array( 'pink', 44, 3, 12, 12, 0 ),   array( 'solid', 12, 7, 56, 8, 1 ),  array( 'gold', 24, 4, 84, 9, .5 ),
	array( 'pink', 32, 94, 10, 10, 1 ),  array( 'line', 40, 96, 46, 13, 2 ),  array( 'solid', 14, 92, 88, 9, 2 ),
	array( 'line', 30, 62, 3, 12, 1 ),
);
$presets = array( 10, 30, 50, 100, 200, 500 );

$faqs = array(
	array( 'Bagaimana saya mendapat resit sumbangan?',
		'Resit wakaf boleh diperoleh dalam format digital melalui portal Wakaf Selangor Online atau Aplikasi Wakaf Selangor, bagi transaksi yang bermula 8 Mei 2024.' ),
	array( 'Perlukah saya mendaftar sebagai pewakaf?',
		'Pewakaf digalakkan mendaftar di portal Wakaf Selangor Online atau melalui Aplikasi Wakaf Selangor untuk menyemak rekod dan resit wakaf mereka.' ),
	array( 'Apakah syarat-syarat yang membolehkan seseorang berwakaf?',
		'Bagi menjadikan wakaf sah dari segi syarak, pewakaf disyaratkan: (1) merdeka, baligh dan berakal; (2) berkelayakan untuk bersedekah — orang yang dihalang berbelanja kerana tidak siuman atau muflis tidak sah mewakafkan harta; dan (3) berwakaf secara sukarela, kerana wakaf orang yang dipaksa tidak sah. (Rujukan: Al-Fiqh Al-Manhaj, Bab Wakaf)' ),
	array( 'Adakah sampai pahala wakaf kepada orang yang telah meninggal dunia?',
		'InsyaAllah sampai pahalanya dan diterima oleh Allah SWT. Amal soleh atau wakaf yang dilakukan oleh orang yang masih hidup boleh dihadiahkan pahalanya kepada orang yang sudah meninggal dunia. Dalam hadis riwayat Bukhari daripada Saidatina Aishah r.a., seorang lelaki bertanya kepada Rasulullah SAW sama ada ibunya yang telah meninggal mendapat pahala jika dia bersedekah bagi pihaknya, lalu baginda menjawab: “Ya.”' ),
	array( 'Bolehkah orang bukan Islam berwakaf?',
		'Hukum berwakaf bagi orang bukan Islam adalah harus dan dibenarkan. Para ulama As-Shafie berpendapat wakaf seseorang bukan Islam sah walaupun untuk masjid. Mereka diberi ganjaran atas sedekahnya di dunia, namun tidak di akhirat, sebagaimana dijelaskan dalam hadis riwayat Muslim.' ),
	array( 'Bolehkah wang insurans yang diterima waris diwakafkan untuk si mati?',
		'Jika wang tersebut telah difaraidkan kepada waris, waris-waris boleh berpakat untuk mewakafkan sebahagian daripada bahagian masing-masing bagi tujuan jariah si arwah. InsyaAllah berkat dan pahalanya sampai berdasarkan hadis daripada Abu Hurairah r.a. (HR Muslim No. 1630).' ),
	array( 'Apakah wasiat wakaf dan bagaimana membuatnya?',
		'Wasiat wakaf ialah wasiat harta berbentuk tunai, emas mahupun hartanah. Ia boleh dibuat melalui Bahagian Baitulmal, Majlis Agama Islam Selangor (MAIS) atau agensi wasiat berdaftar dengan menamakan Perbadanan Wakaf Selangor sebagai penerima wasiat wakaf.' ),
);
?>
<section class="dpage">
	<div class="dpage__bg" aria-hidden="true">
		<?php foreach ( $confetti as $c ) : ?>
			<span class="cf cf--<?php echo esc_attr( $c[0] ); ?>" style="--s:<?php echo (int) $c[1]; ?>px;--l:<?php echo (int) $c[2]; ?>%;--t:<?php echo (int) $c[3]; ?>%;--dur:<?php echo (float) $c[4]; ?>s;--dl:<?php echo (float) $c[5]; ?>s"></span>
		<?php endforeach; ?>
	</div>

	<div class="container">
		<header class="dpage__head">
			<p class="eyebrow">Sumbang Wakaf</p>
			<h1 class="dpage__title"><span>Mulakan sumbangan</span> <strong>wakaf anda.</strong></h1>
			<p class="dpage__lead">Ikuti beberapa langkah mudah. Anda boleh menyemak semula maklumat sebelum sumbangan dihantar.</p>
		</header>

		<div class="dpage__grid">

			<div class="wiz" data-wizard>
				<ol class="wiz__steps" style="--p:0">
					<li class="is-current" aria-current="step"><button type="button" class="wnode" data-goto="1" disabled><span class="wnode__hex"><b>1</b></span><span class="wnode__label">Cara</span></button></li>
					<li><button type="button" class="wnode" data-goto="2" disabled><span class="wnode__hex"><b>2</b></span><span class="wnode__label">Jumlah</span></button></li>
					<li><button type="button" class="wnode" data-goto="3" disabled><span class="wnode__hex"><b>3</b></span><span class="wnode__label">Maklumat</span></button></li>
					<li><button type="button" class="wnode" data-goto="4" disabled><span class="wnode__hex"><b>4</b></span><span class="wnode__label">Semak</span></button></li>
				</ol>
				<p class="screen-reader-text" role="status" aria-live="polite" data-wiz-status></p>

				<noscript><p class="wiz__noscript">Wizard ini memerlukan JavaScript. Sila teruskan terus ke <a href="https://fpx.wakafselangor.gov.my/" rel="noopener">portal Wakaf Selangor Online</a> untuk menyumbang.</p></noscript>

				<div class="wiz__panels">

					<!-- Langkah 1: Cara -->
					<section class="wstep" data-step="1" aria-labelledby="ws1-title">
						<h2 id="ws1-title" class="wstep__title" tabindex="-1">Pilih cara sumbangan</h2>
						<p class="wstep__lead">Bagaimana anda ingin berwakaf?</p>

						<fieldset class="methods">
							<legend class="screen-reader-text">Cara sumbangan</legend>
							<label class="method">
								<input type="radio" name="cara" value="fpx" checked>
								<span class="method__card">
									<span class="hexi"><?php echo pws_icon( 'coins', 22 ); ?></span>
									<span class="method__body"><b>Sumbangan sekali</b><span>Dalam talian melalui portal Wakaf Selangor Online.</span></span>
									<span class="method__tick" aria-hidden="true"><?php echo pws_icon( 'arrow', 14 ); ?></span>
								</span>
							</label>
							<label class="method">
								<input type="radio" name="cara" value="dd">
								<span class="method__card">
									<span class="hexi"><?php echo pws_icon( 'wallet', 22 ); ?></span>
									<span class="method__body"><b>Direct Debit bulanan</b><span>Berwakaf secara bulanan melalui InfaqPay.</span></span>
									<span class="method__tick" aria-hidden="true"><?php echo pws_icon( 'arrow', 14 ); ?></span>
								</span>
							</label>
							<label class="method">
								<input type="radio" name="cara" value="jompay">
								<span class="method__card">
									<span class="hexi"><?php echo pws_icon( 'landmark', 22 ); ?></span>
									<span class="method__body"><b>JomPAY</b><span>Melalui perbankan internet atau aplikasi bank anda.</span></span>
									<span class="method__tick" aria-hidden="true"><?php echo pws_icon( 'arrow', 14 ); ?></span>
								</span>
							</label>
						</fieldset>

						<div class="method-extra" data-extra="dd" hidden>
							<p><strong>Direct Debit</strong> membolehkan sumbangan dipotong secara bulanan melalui InfaqPay — mudah, pantas dan selamat. Anda akan dibawa ke laman InfaqPay untuk mendaftar.</p>
						</div>
						<div class="method-extra" data-extra="jompay" hidden>
							<p>Buat sumbangan melalui <strong>JomPAY</strong> di perbankan internet atau aplikasi bank anda dengan butiran berikut:</p>
							<dl class="way__ref">
								<div><dt>Biller Code</dt><dd><b id="wiz-biller">233064</b> <button type="button" class="copy" data-copy-target="wiz-biller" aria-label="Salin Biller Code 233064">Salin</button></dd></div>
								<div><dt>Ref-1</dt><dd>No. Kad Pengenalan</dd></div>
								<div><dt>Ref-2</dt><dd>No. Telefon</dd></div>
							</dl>
						</div>

						<div class="wstep__actions">
							<span></span>
							<button type="button" class="btn btn--primary btn--lg" data-next data-for-method="fpx">Seterusnya <?php echo pws_icon( 'arrow', 18 ); ?></button>
							<a class="btn btn--primary btn--lg" data-for-method="dd" href="https://infaqpay.my/go/wakafselangor" rel="noopener" hidden>Teruskan ke InfaqPay <?php echo pws_icon( 'arrow', 18 ); ?></a>
						</div>
					</section>

					<form class="wiz__form" action="https://fpx.wakafselangor.gov.my/fpxForm.asp?action=addnew" method="post" data-wiz-form novalidate>

						<!-- Langkah 2: Jumlah -->
						<section class="wstep" data-step="2" aria-labelledby="ws2-title" hidden>
							<h2 id="ws2-title" class="wstep__title" tabindex="-1">Berapakah jumlah sumbangan anda?</h2>
							<p class="wstep__lead">Pilih jumlah, atau masukkan jumlah yang anda kehendaki.</p>

							<div class="chips chips--3">
								<?php foreach ( $presets as $i => $amt ) : ?>
									<button type="button" class="chip-btn" data-amount="<?php echo (int) $amt; ?>" aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>">RM<?php echo (int) $amt; ?></button>
								<?php endforeach; ?>
							</div>

							<label class="field field--big">
								<span class="field__label">Jumlah lain (RM)</span>
								<span class="field__wrap"><span class="field__prefix">RM</span>
									<input type="text" name="amount" value="10" inputmode="decimal" autocomplete="off" required pattern="[0-9]+(\.[0-9]{1,2})?" data-msg="Sila masukkan jumlah yang sah, contohnya 50 atau 50.00.">
								</span>
							</label>

							<div class="wstep__actions">
								<button type="button" class="btn btn--ghost-dark" data-prev><?php echo pws_icon( 'arrow', 18 ); ?> Kembali</button>
								<button type="button" class="btn btn--primary btn--lg" data-next>Seterusnya <?php echo pws_icon( 'arrow', 18 ); ?></button>
							</div>
						</section>

						<!-- Langkah 3: Maklumat -->
						<section class="wstep" data-step="3" aria-labelledby="ws3-title" hidden>
							<h2 id="ws3-title" class="wstep__title" tabindex="-1">Maklumat pewakaf</h2>
							<p class="wstep__lead">Maklumat ini diperlukan untuk merekodkan sumbangan wakaf anda.</p>

							<label class="field">
								<span class="field__label">Nama pewakaf</span>
								<input type="text" name="nama" autocomplete="name" required minlength="3" data-msg="Sila masukkan nama pewakaf.">
							</label>
							<label class="field">
								<span class="field__label">No. kad pengenalan</span>
								<input type="text" name="icno" inputmode="numeric" maxlength="12" autocomplete="off" required pattern="[0-9]{12}" placeholder="12 digit tanpa tanda ‘-’" data-msg="Sila masukkan 12 digit No. Kad Pengenalan tanpa tanda ‘-’.">
							</label>
							<p class="field__note">Maklumat anda digunakan untuk memproses sumbangan wakaf ini. Rujuk <a href="<?php echo pws_url( 'notis-privasi/' ); ?>" rel="noopener">Notis Privasi</a> PWS untuk butiran lanjut.</p>

							<div class="wstep__actions">
								<button type="button" class="btn btn--ghost-dark" data-prev><?php echo pws_icon( 'arrow', 18 ); ?> Kembali</button>
								<button type="button" class="btn btn--primary btn--lg" data-next>Semak Sumbangan <?php echo pws_icon( 'arrow', 18 ); ?></button>
							</div>
						</section>

						<!-- Langkah 4: Semak -->
						<section class="wstep" data-step="4" aria-labelledby="ws4-title" hidden>
							<h2 id="ws4-title" class="wstep__title" tabindex="-1">Semak sumbangan anda</h2>
							<p class="wstep__lead">Pastikan semua maklumat betul sebelum meneruskan.</p>

							<dl class="summary">
								<div class="summary__row"><dt>Cara</dt><dd data-sum="cara">Sumbangan sekali (dalam talian)</dd><button type="button" class="summary__edit" data-goto="1">Ubah</button></div>
								<div class="summary__row summary__row--amount"><dt>Jumlah</dt><dd data-sum="amount">RM 10.00</dd><button type="button" class="summary__edit" data-goto="2">Ubah</button></div>
								<div class="summary__row"><dt>Nama pewakaf</dt><dd data-sum="nama">—</dd><button type="button" class="summary__edit" data-goto="3">Ubah</button></div>
								<div class="summary__row"><dt>No. kad pengenalan</dt><dd data-sum="icno">—</dd><button type="button" class="summary__edit" data-goto="3">Ubah</button></div>
							</dl>

							<label class="consent">
								<input type="checkbox" required data-msg="Sila sahkan maklumat anda untuk meneruskan.">
								<span>Saya mengesahkan maklumat di atas adalah betul.</span>
							</label>

							<div class="wstep__actions">
								<button type="button" class="btn btn--ghost-dark" data-prev><?php echo pws_icon( 'arrow', 18 ); ?> Kembali</button>
								<button type="submit" class="btn btn--primary btn--lg" data-submit>Sumbang Wakaf Sekarang <?php echo pws_icon( 'arrow', 18 ); ?></button>
							</div>
							<p class="give__fine">Anda akan dialihkan ke portal rasmi Wakaf Selangor Online untuk melengkapkan sumbangan. Resit digital boleh diperoleh melalui portal tersebut.</p>
						</section>
					</form>
				</div>
			</div>

			<aside class="dpage__aside">
				<figure class="aside-quote">
					<blockquote>“Apabila seseorang manusia meninggal dunia, terputuslah amalnya kecuali tiga perkara: sedekah jariah, ilmu yang bermanfaat, dan anak soleh yang mendoakannya.”</blockquote>
					<figcaption>— Hadis riwayat Muslim</figcaption>
				</figure>
				<div class="aside-help">
					<span class="hexi"><?php echo pws_icon( 'phone', 20 ); ?></span>
					<div>
						<b>Perlukan bantuan?</b>
						<p>Hubungi Wakaf Hotline <a href="tel:+60355188001">03-5518 8001</a><br>Isnin–Jumaat, 8:30 pagi – 4:30 petang.</p>
					</div>
				</div>
			</aside>
		</div>

		<section class="faq" aria-labelledby="faq-title">
			<h2 id="faq-title" class="faq__title"><span>Soalan</span> <strong>lazim</strong></h2>
			<div class="faq__list">
				<?php foreach ( $faqs as $i => $f ) : ?>
					<details class="faq__item"<?php echo 0 === $i ? ' open' : ''; ?>>
						<summary><span><?php echo esc_html( $f[0] ); ?></span><span class="faq__chev" aria-hidden="true"><?php echo pws_icon( 'chevron', 18 ); ?></span></summary>
						<p><?php echo esc_html( $f[1] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
			<p class="faq__more">Ada soalan lain? <a href="<?php echo pws_url( 'soalan-lazim/' ); ?>" rel="noopener">Lihat semua soalan lazim</a> atau hubungi Wakaf Hotline <a href="tel:+60355188001">03-5518 8001</a>.</p>
		</section>
	</div>
</section>

<script type="application/ld+json"><?php
echo wp_json_encode( array(
	'@context'   => 'https://schema.org',
	'@type'      => 'FAQPage',
	'mainEntity' => array_map( function ( $f ) {
		return array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ) );
	}, $faqs ),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
?></script>
<?php
get_footer();
