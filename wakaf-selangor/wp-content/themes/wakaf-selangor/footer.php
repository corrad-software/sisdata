</main>

<?php
$pws_partners = array(
	array( 'name' => 'Majlis Agama Islam Selangor',      'img' => 'maiss.png' ),
	array( 'name' => 'Lembaga Zakat Selangor',           'img' => 'lzs.png' ),
	array( 'name' => 'Jabatan Agama Islam Selangor',     'img' => 'jais.png' ),
	array( 'name' => 'Jabatan Mufti Selangor',           'img' => 'mufti.png' ),
	array( 'name' => 'Kolej Universiti Islam Selangor',  'img' => 'kuiss.png' ),
);
?>
<footer class="site-footer">

	<!-- Jalur merah: seruan + rakan agensi -->
	<section class="fcta" aria-labelledby="fcta-title">
		<div class="fcta__art" aria-hidden="true">
		<svg class="fcta__hex " style="left:-4%;top:-30%;width:300px;--dur:16s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
		<svg class="fcta__hex " style="left:30%;top:58%;width:170px;--dur:12s;--dl:-3s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
		<svg class="fcta__hex " style="right:6%;top:-22%;width:240px;--dur:14s;--dl:-6s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
		<svg class="fcta__hex " style="right:-3%;top:52%;width:380px;--dur:18s;--dl:-2s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
		<svg class="fcta__hex fcta__hex--fill" style="left:46%;top:8%;width:58px;--dur:9s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
		<svg class="fcta__hex fcta__hex--fill" style="left:8%;top:70%;width:40px;--dur:10s;--dl:-4s" viewBox="0 0 100 115" fill="none" stroke="currentColor" stroke-width="1.2"><polygon points="50,2 98,29 98,86 50,113 2,86 2,29"/></svg>
		</div>

		<div class="container fcta__top">
			<h2 id="fcta-title" class="fcta__title"><span>Mulakan wakaf anda,</span> <strong>pahala berpanjangan.</strong></h2>
			<div class="fcta__actions">
				<a class="btn btn--gold btn--lg" href="<?php echo pws_donate_url(); ?>">Sumbang Wakaf <?php echo pws_icon( 'arrow', 18 ); ?></a>
				<a class="btn btn--outline-light btn--lg" href="<?php echo pws_url( 'hubungi-kami/' ); ?>">Hubungi Kami</a>
			</div>
		</div>

		<div class="container">
			<div class="fcta__divider" aria-hidden="true"><span class="fcta__badge"><img src="<?php echo pws_img( 'logoPWSlogo.png' ); ?>" alt="" width="40" height="35" loading="lazy"></span></div>
		</div>

		<div class="container fcta__partners">
			<h3 class="fcta__label">Pautan agensi MAIS dan anak syarikat</h3>
			<ul class="fcta__logos">
				<?php foreach ( $pws_partners as $pt ) : ?>
					<li><img src="<?php echo pws_img( $pt['img'] ); ?>" alt="<?php echo esc_attr( $pt['name'] ); ?>" title="<?php echo esc_attr( $pt['name'] ); ?>" width="76" height="76" loading="lazy"></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<!-- Bahagian putih -->
	<div class="fmain">
		<div class="container fmain__top">
			<div class="fmain__brand">
				<img src="<?php echo pws_img( 'logoPWShitam200.png' ); ?>" alt="Perbadanan Wakaf Selangor" width="175" height="60" loading="lazy">
				<p>Agensi di bawah Majlis Agama Islam Selangor (MAIS) yang mengurus dan membangunkan harta wakaf di negeri Selangor.</p>
			</div>

			<ul class="fmain__contact">
				<li>
					<span class="hexi"><?php echo pws_icon( 'pin', 20 ); ?></span>
					<div><span class="fmain__label">Alamat</span><address>Tingkat 10, Menara Selatan, Bangunan Sultan Idris Shah, 40000 Shah Alam, Selangor</address></div>
				</li>
				<li>
					<span class="hexi"><?php echo pws_icon( 'phone', 20 ); ?></span>
					<div><span class="fmain__label">Wakaf Hotline</span><a href="tel:+60355188001">03-5518 8001</a></div>
				</li>
				<li>
					<span class="hexi"><?php echo pws_icon( 'clock', 20 ); ?></span>
					<div><span class="fmain__label">Waktu Operasi</span><span class="fmain__val">Isnin–Jumaat<br>8:30 pagi – 4:30 petang</span></div>
				</li>
			</ul>
		</div>

		<div class="container fmain__links">
			<ul class="fmain__nav" aria-label="Pautan penting">
				<li><a href="<?php echo pws_url( 'penubuhan-pws/' ); ?>">Tentang PWS</a></li>
				<li><a href="<?php echo pws_url( 'wakaftunai/' ); ?>">Wakaf Tunai</a></li>
				<li><a href="<?php echo pws_url( 'hartanah-wakaf/' ); ?>">Hartanah Wakaf</a></li>
				<li><a href="<?php echo pws_url( 'statistik/' ); ?>">Statistik</a></li>
				<li><a href="<?php echo pws_url( 'berita/' ); ?>">Berita Terkini</a></li>
				<li><a href="<?php echo pws_url( 'kerjaya/' ); ?>">Kerjaya</a></li>
			</ul>
			<ul class="fmain__chips" aria-label="Pautan pantas">
				<li><a href="<?php echo pws_url( 'infowakaf/' ); ?>">Definisi Wakaf</a></li>
				<li><a href="<?php echo pws_url( 'produk-wakaf-selangor/' ); ?>">Produk Wakaf</a></li>
				<li><a href="<?php echo pws_url( 'prosedur/' ); ?>">Prosedur Penyertaan</a></li>
				<li><a href="<?php echo pws_url( 'soalan-lazim/' ); ?>">Soalan Lazim</a></li>
				<li><a href="https://fpx.wakafselangor.gov.my/ewo_login.asp" rel="noopener">Sistem EWO</a></li>
				<li><a href="https://bo.wakafselangor.gov.my/" rel="noopener">Borang Online</a></li>
				<li><a href="<?php echo pws_url( 'notis-privasi/' ); ?>">Notis Privasi</a></li>
			</ul>
		</div>

		<div class="fmain__bar">
			<div class="container fmain__bar-inner">
				<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Perbadanan Wakaf Selangor. Hak cipta terpelihara.</p>
				<a class="fmain__top-link" href="#top">Kembali ke atas <?php echo pws_icon( 'arrow', 14 ); ?></a>
			</div>
		</div>
	</div>
</footer>

<?php if ( ! is_page( 'sumbang-wakaf' ) ) : ?>
<!-- Pautan pantas terapung: Sumbang Wakaf -->
<aside class="fab" data-fab aria-label="Sumbangan pantas">
	<div class="fab__inner">
		<div class="fab__bubble" id="fab-bubble" data-fab-bubble>
			<button type="button" class="fab__close" data-fab-close aria-label="Tutup"><?php echo pws_icon( 'close', 16 ); ?></button>
			<p class="fab__title">Mulakan sumbangan anda</p>
			<p class="fab__text">Pilih jumlah — anda terus ke langkah seterusnya.</p>
			<div class="fab__amounts">
				<a href="<?php echo pws_donate_url( array( 'amount' => 10 ) ); ?>">RM10</a>
				<a href="<?php echo pws_donate_url( array( 'amount' => 50 ) ); ?>">RM50</a>
				<a href="<?php echo pws_donate_url( array( 'amount' => 100 ) ); ?>">RM100</a>
			</div>
			<a class="fab__more" href="<?php echo pws_donate_url(); ?>">Pilih jumlah lain <?php echo pws_icon( 'arrow', 14 ); ?></a>
		</div>

		<a class="fab__pill" href="<?php echo pws_donate_url(); ?>">
			<span class="fab__icon" aria-hidden="true">
				<span class="fab__ring"></span>
				<span class="fab__ring fab__ring--2"></span>
				<span class="fab__hex"><?php echo pws_icon( 'heart', 22 ); ?></span>
			</span>
			<span class="fab__label">Sumbang Wakaf</span>
		</a>
	</div>
</aside>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
