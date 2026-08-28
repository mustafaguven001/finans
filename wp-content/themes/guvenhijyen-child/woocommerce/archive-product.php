<?php

defined('ABSPATH') || exit;

get_header();

$current_term = get_queried_object();
$is_taxonomy = is_product_category() || is_product_taxonomy();
$subcategories = [];

if ($is_taxonomy && $current_term) {
    $subcategories = get_terms([
        'taxonomy'   => $current_term->taxonomy,
        'parent'     => $current_term->term_id,
        'hide_empty' => true,
    ]);
    if (is_wp_error($subcategories)) {
        $subcategories = [];
    }
}

$category_description = '';
if ($is_taxonomy && $current_term) {
    $category_description = term_description($current_term->term_id);
}

$extended_content = '';
if ($is_taxonomy && $current_term) {
    $extended_content = get_term_meta($current_term->term_id, 'gh_extended_content', true);
}

?>
<div class="gh-archive">
    <div class="gh-container">
        <?php gh_render_breadcrumb(); ?>

        <div class="gh-archive__header">
            <h1 class="gh-archive__title">
                <?php
                if ($is_taxonomy && $current_term) {
                    echo esc_html($current_term->name);
                } else {
                    esc_html_e('Products', 'guvenhijyen');
                }
                ?>
            </h1>

            <?php if ($category_description) : ?>
                <div class="gh-archive__description">
                    <?php echo wp_kses_post($category_description); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($subcategories) : ?>
            <div class="gh-archive__subcategories">
                <?php foreach ($subcategories as $subcat) :
                    $thumb_id = get_term_meta($subcat->term_id, 'thumbnail_id', true);
                    ?>
                    <a href="<?php echo esc_url(get_term_link($subcat)); ?>" class="gh-subcat-card">
                        <?php if ($thumb_id) : ?>
                            <?php echo wp_get_attachment_image($thumb_id, 'thumbnail', false, [
                                'loading' => 'lazy',
                                'decoding' => 'async',
                            ]); ?>
                        <?php endif; ?>
                        <span class="gh-subcat-card__name"><?php echo esc_html($subcat->name); ?></span>
                        <span class="gh-subcat-card__count">
                            <?php printf(
                                esc_html(_n('%d product', '%d products', $subcat->count, 'guvenhijyen')),
                                (int) $subcat->count
                            ); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="gh-archive__layout">
            <aside class="gh-archive__sidebar" aria-label="<?php esc_attr_e('Product filters', 'guvenhijyen'); ?>">
                <?php
                $filter_attributes = wc_get_attribute_taxonomies();
                foreach ($filter_attributes as $attribute) :
                    $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
                    if (!taxonomy_exists($taxonomy)) {
                        continue;
                    }

                    $terms = get_terms([
                        'taxonomy'   => $taxonomy,
                        'hide_empty' => true,
                    ]);

                    if (!$terms || is_wp_error($terms)) {
                        continue;
                    }
                    ?>
                    <div class="gh-filter-group">
                        <h3 class="gh-filter-group__title"><?php echo esc_html($attribute->attribute_label); ?></h3>
                        <ul class="gh-filter-group__list">
                            <?php foreach ($terms as $term) :
                                $filter_url = add_query_arg('filter_' . $attribute->attribute_name, $term->slug);
                                $is_active = isset($_GET['filter_' . $attribute->attribute_name])
                                    && sanitize_text_field(wp_unslash($_GET['filter_' . $attribute->attribute_name])) === $term->slug;
                                ?>
                                <li>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="filter_<?php echo esc_attr($attribute->attribute_name); ?>"
                                            value="<?php echo esc_attr($term->slug); ?>"
                                            <?php checked($is_active); ?>
                                        >
                                        <?php echo esc_html($term->name); ?>
                                        <span>(<?php echo esc_html($term->count); ?>)</span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>

                <?php
                $brand_terms = get_terms([
                    'taxonomy'   => 'product_brand',
                    'hide_empty' => true,
                ]);
                if ($brand_terms && !is_wp_error($brand_terms)) : ?>
                    <div class="gh-filter-group">
                        <h3 class="gh-filter-group__title"><?php esc_html_e('Brands', 'guvenhijyen'); ?></h3>
                        <ul class="gh-filter-group__list">
                            <?php foreach ($brand_terms as $bt) :
                                $is_active_brand = isset($_GET['filter_brand'])
                                    && sanitize_text_field(wp_unslash($_GET['filter_brand'])) === $bt->slug;
                                ?>
                                <li>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="filter_brand"
                                            value="<?php echo esc_attr($bt->slug); ?>"
                                            <?php checked($is_active_brand); ?>
                                        >
                                        <?php echo esc_html($bt->name); ?>
                                        <span>(<?php echo esc_html($bt->count); ?>)</span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php
                $sector_terms = get_terms([
                    'taxonomy'   => 'product_sector',
                    'hide_empty' => true,
                ]);
                if ($sector_terms && !is_wp_error($sector_terms)) : ?>
                    <div class="gh-filter-group">
                        <h3 class="gh-filter-group__title"><?php esc_html_e('Sectors', 'guvenhijyen'); ?></h3>
                        <ul class="gh-filter-group__list">
                            <?php foreach ($sector_terms as $st) :
                                $is_active_sector = isset($_GET['filter_sector'])
                                    && sanitize_text_field(wp_unslash($_GET['filter_sector'])) === $st->slug;
                                ?>
                                <li>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="filter_sector"
                                            value="<?php echo esc_attr($st->slug); ?>"
                                            <?php checked($is_active_sector); ?>
                                        >
                                        <?php echo esc_html($st->name); ?>
                                        <span>(<?php echo esc_html($st->count); ?>)</span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </aside>

            <div class="gh-archive__main">
                <?php if (woocommerce_product_loop()) : ?>
                    <div class="gh-product-grid">
                        <?php while (have_posts()) : the_post();
                            global $product;
                            get_template_part('template-parts/product-card');
                        endwhile; ?>
                    </div>

                    <nav class="gh-pagination" aria-label="<?php esc_attr_e('Product pagination', 'guvenhijyen'); ?>">
                        <?php
                        echo wp_kses_post(paginate_links([
                            'total'     => $wp_query->max_num_pages,
                            'current'   => max(1, get_query_var('paged')),
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ]));
                        ?>
                    </nav>

                <?php else : ?>
                    <p><?php esc_html_e('No products found.', 'guvenhijyen'); ?></p>
                <?php endif; ?>

                <?php if ($extended_content) : ?>
                    <div class="gh-archive__extended">
                        <?php echo wp_kses_post($extended_content); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php get_template_part('template-parts/rfq-cta'); ?>
    </div>
</div>

<?php get_footer();
