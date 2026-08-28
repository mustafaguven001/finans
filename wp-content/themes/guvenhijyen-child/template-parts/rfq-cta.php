<?php

defined('ABSPATH') || exit;

$teklif_page = get_page_by_path('teklif-iste');
$teklif_url = $teklif_page ? get_permalink($teklif_page) : '#';
$phone = gh_get_company('phone');
$whatsapp_url = gh_whatsapp_url();

?>
<section class="gh-rfq-cta" aria-label="<?php esc_attr_e('Request for quote', 'guvenhijyen'); ?>">
    <div class="gh-container">
        <h2 class="gh-rfq-cta__title">
            <?php esc_html_e('Can\'t find the product you\'re looking for?', 'guvenhijyen'); ?>
        </h2>
        <p class="gh-rfq-cta__text">
            <?php esc_html_e('Send us your product list for a personalized quote. Our team will get back to you within 24 hours.', 'guvenhijyen'); ?>
        </p>
        <div class="gh-rfq-cta__actions">
            <a href="<?php echo esc_url($teklif_url); ?>" class="gh-btn gh-btn--primary gh-btn--lg">
                <?php esc_html_e('Teklif İste', 'guvenhijyen'); ?>
            </a>

            <?php if ($whatsapp_url) : ?>
                <a href="<?php echo esc_url($whatsapp_url); ?>" class="gh-btn gh-btn--whatsapp gh-btn--lg" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('WhatsApp ile Sor', 'guvenhijyen'); ?>
                </a>
            <?php endif; ?>

            <?php if ($phone) : ?>
                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>" class="gh-btn gh-btn--outline gh-btn--lg">
                    <?php echo esc_html($phone); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
