<?php

defined('ABSPATH') || exit;

global $product;

if (!$product || !is_a($product, 'WC_Product')) {
    return;
}

$product_id = $product->get_id();
$permalink = get_permalink($product_id);
$sku = $product->get_sku();
$image_id = $product->get_image_id();

$procurement_status = '';
if (class_exists('GH_Procurement')) {
    $procurement_status = GH_Procurement::get_status($product_id);
}

$brand = null;
$brand_logo_url = '';
$brands = wp_get_post_terms($product_id, 'product_brand');
if ($brands && !is_wp_error($brands)) {
    $brand = $brands[0];
    $brand_logo_id = get_term_meta($brand->term_id, 'gh_brand_logo', true);
    if ($brand_logo_id) {
        $brand_logo_url = wp_get_attachment_image_url($brand_logo_id, 'thumbnail');
    }
}

$categories = wc_get_product_terms($product_id, 'product_cat', ['orderby' => 'parent', 'order' => 'ASC']);

$sales_unit = '';
if (class_exists('GH_Sales_Unit')) {
    $sales_unit = GH_Sales_Unit::get_label($product_id);
}
if (!$sales_unit) {
    $sales_unit = get_post_meta($product_id, '_gh_sales_unit', true);
}

$highlight_attributes = [];
$attributes = $product->get_attributes();
$attr_count = 0;
foreach ($attributes as $attribute) {
    if ($attr_count >= 3) {
        break;
    }
    if ($attribute->get_visible()) {
        $values = [];
        if ($attribute->is_taxonomy()) {
            $terms = wc_get_product_terms($product_id, $attribute->get_name(), ['fields' => 'names']);
            $values = $terms;
        } else {
            $values = $attribute->get_options();
        }
        if ($values) {
            $highlight_attributes[] = implode(', ', array_slice($values, 0, 2));
        }
        $attr_count++;
    }
}

$whatsapp_url = gh_whatsapp_url(
    sprintf(
        '%s (%s) hakkında bilgi almak istiyorum. %s',
        $product->get_name(),
        $sku,
        $permalink
    )
);

?>
<article class="gh-product-card" data-product-id="<?php echo esc_attr($product_id); ?>">
    <a href="<?php echo esc_url($permalink); ?>" class="gh-product-card__image" aria-hidden="true" tabindex="-1">
        <?php if ($image_id) : ?>
            <?php echo wp_get_attachment_image($image_id, 'product-thumb', false, [
                'loading' => 'lazy',
                'decoding' => 'async',
            ]); ?>
        <?php else : ?>
            <?php echo wc_placeholder_img('product-thumb'); ?>
        <?php endif; ?>

        <?php if ($brand && $brand_logo_url) : ?>
            <div class="gh-product-card__badge">
                <span class="gh-product-card__brand-badge">
                    <img src="<?php echo esc_url($brand_logo_url); ?>" alt="<?php echo esc_attr($brand->name); ?>" width="20" height="20" loading="lazy">
                    <?php echo esc_html($brand->name); ?>
                </span>
            </div>
        <?php endif; ?>
    </a>

    <div class="gh-product-card__body">
        <?php if ($categories) : ?>
            <div class="gh-product-card__category">
                <?php
                $crumbs = [];
                foreach ($categories as $cat) {
                    $crumbs[] = '<a href="' . esc_url(get_term_link($cat)) . '">' . esc_html($cat->name) . '</a>';
                }
                echo wp_kses_post(implode(' / ', $crumbs));
                ?>
            </div>
        <?php endif; ?>

        <h2 class="gh-product-card__title">
            <a href="<?php echo esc_url($permalink); ?>">
                <?php echo esc_html($product->get_name()); ?>
            </a>
        </h2>

        <?php if ($sku) : ?>
            <div class="gh-product-card__sku">
                <?php echo esc_html($sku); ?>
            </div>
        <?php endif; ?>

        <?php if ($highlight_attributes) : ?>
            <div class="gh-product-card__attributes">
                <?php foreach ($highlight_attributes as $attr_val) : ?>
                    <span class="gh-product-card__attribute"><?php echo esc_html($attr_val); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($sales_unit) : ?>
            <div class="gh-product-card__sales-unit">
                <?php
                printf(
                    esc_html__('Sales Unit: %s', 'guvenhijyen'),
                    esc_html($sales_unit)
                );
                ?>
            </div>
        <?php endif; ?>

        <?php if ($procurement_status && $procurement_status !== GH_Procurement::STATUS_ACTIVE) : ?>
            <?php
            $status_labels = GH_Procurement::get_statuses();
            $status_class = $procurement_status === GH_Procurement::STATUS_TEMPORARILY_UNAVAILABLE
                ? 'unavailable' : 'discontinued';
            ?>
            <div class="gh-product-card__status gh-product-card__status--<?php echo esc_attr($status_class); ?>">
                <?php echo esc_html($status_labels[$procurement_status] ?? $procurement_status); ?>
            </div>
        <?php endif; ?>

        <div class="gh-product-card__actions">
            <button
                type="button"
                class="gh-btn gh-btn--primary gh-btn--sm gh-btn--full js-add-to-quote"
                data-product-id="<?php echo esc_attr($product_id); ?>"
                data-product-name="<?php echo esc_attr($product->get_name()); ?>"
                data-sku="<?php echo esc_attr($sku); ?>"
            >
                <?php esc_html_e('Teklif Listesine Ekle', 'guvenhijyen'); ?>
            </button>

            <a
                href="<?php echo esc_url(add_query_arg('product_id', $product_id, get_permalink(get_page_by_path('teklif-iste')))); ?>"
                class="gh-btn gh-btn--secondary gh-btn--sm gh-btn--full"
            >
                <?php esc_html_e('Hızlı Teklif', 'guvenhijyen'); ?>
            </a>

            <?php if ($whatsapp_url) : ?>
                <a
                    href="<?php echo esc_url($whatsapp_url); ?>"
                    class="gh-btn gh-btn--whatsapp gh-btn--sm gh-btn--full"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <?php esc_html_e('WhatsApp ile Sor', 'guvenhijyen'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</article>
