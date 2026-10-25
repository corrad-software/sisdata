<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
</main>
<footer class="hws-footer">
    <div class="hws-wrap hws-grid hws-grid--3">
        <section>
            <h2 class="hws-footer__title"><?php echo esc_html(hws_t('nama_rasmi')); ?></h2>
            <p><?php echo esc_html(hws_t('kaki_keterangan')); ?></p>
        </section>
        <section>
            <h2 class="hws-footer__title"><?php echo esc_html(hws_t('pautan')); ?></h2>
            <ul class="hws-list-reset">
                <li><a href="<?php echo esc_url(hws_url('notis-privasi')); ?>"><?php echo esc_html(hws_t('notis_privasi')); ?></a></li>
                <li><a href="<?php echo esc_url(hws_url('soalan-lazim')); ?>"><?php echo esc_html(hws_t('nav_soalan')); ?></a></li>
                <li><a href="<?php echo esc_url(hws_url('hubungi')); ?>"><?php echo esc_html(hws_t('nav_hubungi')); ?></a></li>
            </ul>
        </section>
        <section>
            <h2 class="hws-footer__title"><?php echo esc_html(hws_t('hubungi_kami')); ?></h2>
            <p>Majlis Agama Islam Selangor<br>40000 Shah Alam, Selangor</p>
        </section>
    </div>
    <div class="hws-wrap hws-footer__legal">
        <p>&copy; <?php echo esc_html(wp_date('Y')); ?> Majlis Agama Islam Selangor. <?php echo esc_html(hws_t('hak_cipta')); ?></p>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
