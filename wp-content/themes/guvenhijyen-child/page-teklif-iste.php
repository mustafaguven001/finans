<?php
/**
 * Template Name: Teklif İste
 */

defined('ABSPATH') || exit;

get_header();

?>
<div class="gh-teklif-page">
    <div class="gh-container">
        <?php gh_render_breadcrumb(); ?>

        <h1><?php esc_html_e('Teklif İste', 'guvenhijyen'); ?></h1>

        <p class="gh-teklif-page__intro">
            <?php esc_html_e('Fill in the form below to request a quote. You can request a general quote or add specific products to your list. Our team will review your request and get back to you as soon as possible.', 'guvenhijyen'); ?>
        </p>

        <div class="gh-teklif-page__sections">
            <div class="gh-teklif-section">
                <h2 class="gh-teklif-section__title"><?php esc_html_e('General Quote Request', 'guvenhijyen'); ?></h2>
                <p><?php esc_html_e('Describe the products or categories you need and we will prepare a custom quote for you.', 'guvenhijyen'); ?></p>
            </div>

            <div class="gh-teklif-section">
                <h2 class="gh-teklif-section__title"><?php esc_html_e('Quote from Product List', 'guvenhijyen'); ?></h2>
                <p><?php esc_html_e('Products you added to your quote list will be included automatically. You can also search and add more products below.', 'guvenhijyen'); ?></p>
            </div>
        </div>

        <div class="gh-rfq-form-wrapper" id="rfq-form-root">
            <?php
            if (shortcode_exists('guvenhijyen_rfq_form')) {
                echo do_shortcode('[guvenhijyen_rfq_form]');
            }
            ?>
        </div>
    </div>
</div>

<?php get_footer();
